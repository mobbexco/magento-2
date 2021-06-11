<?php
namespace Mobbex\Webpay\Model;

use Mobbex\Webpay\Helper\Config;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\Order\Email\Sender\OrderCommentSender;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface;

/**
 * Class OrderUpdate
 * @package Improntus\Dlocal\Model
 */
class OrderUpdate
{
    /**
     * @var Config
     */
    protected $config;

    /**
     * @var OrderInterface
     */
    protected $_order;

    /**
     * @var BuilderInterface
     */
    protected $transactionBuilder;

    /**
     * @var OrderSender
     */
    protected $orderSender;

    /**
     * @var OrderCommentSender
     */
    protected $orderCommentSender;

    /**
     * OrderUpdate constructor.
     * @param OrderInterface $order
     * @param BuilderInterface $transactionBuilder
     * @param OrderSender $orderSender
     * @param OrderCommentSender $orderCommentSender
     * @param InvoiceSender $invoiceSender
     */
    public function __construct(
        Config $config,
        OrderInterface $order,
        BuilderInterface $transactionBuilder,
        OrderSender $orderSender,
        OrderCommentSender $orderCommentSender,
        InvoiceSender $invoiceSender
    ) {
        $this->config = $config;
        $this->_order = $order;
        $this->transactionBuilder = $transactionBuilder;
        $this->orderSender = $orderSender;
        $this->orderCommentSender = $orderCommentSender;
        $this->invoiceSender = $invoiceSender;
    }

    /**
     * @param $order
     * @param $statusDetail
     * @throws LocalizedException
     */
    public function approvePayment($order, $statusDetail)
    {
        $orderPayment = $order->getPayment();

        $orderPayment->setTransactionId($order->getIncrementId());

        $transaction = $this->transactionBuilder
            ->setPayment($orderPayment)
            ->setOrder($order)
            ->setTransactionId($orderPayment->getTransactionId())
            ->build(Transaction::TYPE_AUTH);

        $orderStatus = $this->config->getOrderStatusApproved();
        $statusMessage = __('Payment status') . ': ' . __($statusDetail);

        $orderPayment->addTransactionCommentsToOrder($transaction, $statusMessage);
        $order->setState($orderStatus);
        $order->addStatusToHistory($orderStatus, $statusDetail);
        $order->save();

        $this->sendOrderEmail($order, $statusMessage);
        $this->invoice($order, $orderPayment, $statusMessage);
        $order->save();
    }

    /**
     * @param $order
     * @param $statusDetail
     */
    public function holdPayment($order, $statusDetail)
    {
        $orderStatus = $this->config->getOrderStatusInProcess();

        $order->setState($orderStatus);
        $order->addStatusToHistory($orderStatus, $statusDetail);
        $order->save();

        $this->sendOrderEmail($order, $statusDetail);
        $order->save();
    }

    /**
     * @param $order
     * @param $statusMessage
     */
    public function cancelPayment($order, $statusMessage)
    {
        $orderStatus = $this->config->getOrderStatusCancelled();

        $order->setState($orderStatus);
        $order->addStatusToHistory($orderStatus, $statusMessage, true);

        if ($orderStatus === 'canceled') {
            $order->cancel();
        }
        $order->save();
    }

    /**
     * @param $order
     * @param $statusDetail
     */
    public function refundPayment($order, $statusDetail)
    {
        $statusDescription = __($statusDetail)->render();
        $orderPayment = $order->getPayment();
        $orderStatus = $this->config->getOrderStatusRefunded();

        $orderPayment->setAdditionalInformation('error_card', $statusDescription);

        $order->setState($orderStatus);
        $order->addStatusToHistory($orderStatus, $statusDescription, true);

        if ($orderStatus === 'canceled') {
            $order->cancel();
        }
        $order->save();

        $this->sendOrderEmail($order, $statusDescription);
        $order->save();
    }

    /**
     * @param $order
     * @param $orderPayment
     * @param $message
     * @return bool|Invoice
     * @throws LocalizedException
     */
    public function invoice($order, $orderPayment, $message)
    {
        if ($order->hasInvoices()) {
            return false;
        }

        $transaction = $this->transactionBuilder
            ->setPayment($orderPayment)
            ->setOrder($order)
            ->setTransactionId($orderPayment->getTransactionId())
            ->build(Transaction::TYPE_AUTH);

        $orderPayment->addTransactionCommentsToOrder($transaction, $message);

        /** @var Invoice $invoice */
        $invoice = $orderPayment->getOrder()->prepareInvoice();

        $invoice->register();
        if ($orderPayment->getMethodInstance()->canCapture()) {
            $invoice->capture();
        }

        $orderPayment->getOrder()->addRelatedObject($invoice);

        $invoice->addComment(
            $message,
            true,
            true
        );

        $emailSent = $invoice->getEmailSent();
        $canSendInvoice = $this->config->getCreateInvoiceEmail();

        if (!$emailSent && $canSendInvoice) {
            $this->invoiceSender->send($invoice);
            $invoice->setIsCustomerNotified(true)
                ->save();
        }

        return $invoice;
    }

    /**
     * Send an email to customer with the Order information.
     * 
     * @param Order $order
     * @param string $message
     */
    public function sendOrderEmail($order ,$message = null)
    {
        $emailSent       = $order->getEmailSent();
        $canSendCreation = $this->config->getCreateOrderEmail();
        $canSendUpdate   = $this->config->getUpdateOrderEmail();

        if (!$emailSent) {
            if ($canSendCreation) {
                $this->orderSender->send($order);
                $order->setIsCustomerNotified(true);
            }
        } else if ($canSendUpdate) {
            $this->orderCommentSender->send($order, $notify = '1', str_replace("<br/>", "", $message));
        }
    }
}