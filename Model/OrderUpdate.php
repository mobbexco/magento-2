<?php
namespace Mobbex\Webpay\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
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
     * OrderUpdate constructor.
     * @param OrderInterface $order
     * @param BuilderInterface $transactionBuilder
     * @param OrderSender $orderSender
     */
    public function __construct(
        OrderInterface $order,
        BuilderInterface $transactionBuilder,
        OrderSender $orderSender
    ) {
        $this->_order = $order;
        $this->transactionBuilder = $transactionBuilder;
        $this->orderSender = $orderSender;
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
        $order->save();

        $this->invoice($order, $orderPayment, $statusMessage);
        $order->save();
    }

    /**
     * @param $order
     * @param $statusMessage
     */
    public function cancelPayment($order, $statusMessage)
    {
        $order->setStatus('cancelled');
        $order->cancel();
        $order->addStatusToHistory($order->getStatus(), $statusMessage, true);
        $order->save();
    }

    /**
     * @param $order
     * @param $statusDetail
     */
    public function refundPayment($order, $statusDetail)
    {
        $statusDescription = __($statusDetail);
        $orderPayment = $order->getPayment();

        $orderPayment->setAdditionalInformation('error_card', $statusDescription);

        $order->setStatus('cancelled');
        $order->cancel();
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

        if ($invoice && !$order->getEmailSent()) {
            $this->orderSender->send($order);
            $order->setIsCustomerNotified(true)
                ->save();
        }

        return $invoice;
    }
}
