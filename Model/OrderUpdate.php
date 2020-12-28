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

        $statusMessage  = __('Payment status') . ': ' . __($statusDetail);

        $orderPayment->addTransactionCommentsToOrder($transaction, $statusMessage);
        $order->setState($this->config->getOrderStatusApproved());
        $order->addStatusToHistory($this->config->getOrderStatusApproved(), $statusDetail);
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
        $order->setState($this->config->getOrderStatusInProcess());
        $order->setStatus(Order::STATE_PENDING_PAYMENT);
        $order->addStatusToHistory($this->config->getOrderStatusInProcess(), $statusDetail);
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
        $order->setStatus('cancelled');
        $order->setState($this->config->getOrderStatusCancelled());
        $order->addStatusToHistory($this->config->getOrderStatusCancelled(), $statusMessage, true);
        $order->cancel();
        $order->save();

        $this->sendOrderEmail($order, $statusMessage);
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

        $orderPayment->setAdditionalInformation('error_card', $statusDescription);

        $order->setStatus('cancelled');
        $order->setState($this->config->getOrderStatusRefunded());
        $order->addStatusToHistory($this->config->getOrderStatusRefunded(), $statusDescription, true);
        $order->cancel();
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
        $canSentCreation = $this->config->getCreateInvoiceEmail();

        if (!$emailSent && $canSentCreation) {
            $this->invoiceSender->send($invoice);
            $invoice->setIsCustomerNotified(true)
                ->save();
        }

        return $invoice;
    }

    /**
     * @param $order
     * @param $message
     */
    public function sendOrderEmail($order ,$message = null)
    {
        $emailSent = $order->getEmailSent();
        $canSentCreation = $this->config->getCreateOrderEmail();
        $canSentUpdate = $this->config->getUpdateOrderEmail();

        if (!$emailSent) {
            if ($canSentCreation) {
                $this->orderSender->send($order);
                $order->setIsCustomerNotified(true);
            }
        } else if ($canSentUpdate) {
            $this->orderCommentSender->send($order, $notify = '1', str_replace("<br/>", "", $message));
        }
    }
}