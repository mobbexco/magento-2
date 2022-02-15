<?php

namespace Mobbex\Webpay\Model;

use Magento\Sales\Model\Order\Payment\Transaction;

class OrderUpdate
{
    /** @var Mobbex\Webpay\Helper\Config */
    protected $config;

    /** @var OrderSender */
    protected $orderSender;

    /** @var InvoiceSender */
    protected $invoiceSender;

    /** @var OrderCommentSender */
    protected $orderCommentSender;

    /** @var BuilderInterface */
    protected $transactionBuilder;

    public function __construct(
        \Mobbex\Webpay\Helper\Config $config,
        \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender,
        \Magento\Sales\Model\Order\Email\Sender\InvoiceSender $invoiceSender,
        \Magento\Sales\Model\Order\Email\Sender\OrderCommentSender $orderCommentSender,
        \Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface $transactionBuilder
    ) {
        $this->config             = $config;
        $this->orderSender        = $orderSender;
        $this->invoiceSender      = $invoiceSender;
        $this->orderCommentSender = $orderCommentSender;
        $this->transactionBuilder = $transactionBuilder;
    }

    /**
     * Update order status from webhook data.
     * 
     * @param OrderInterface $order
     * @param int|string $data
     */
    public function updateStatus($order, $data)
    {
        $statusName  = $this->getStatusConfigName($order, $data['status_code']);
        $orderStatus = $this->config->{"getOrderStatus$statusName"}();

        if ($orderStatus == $order->getStatus())
            return;

        $order->setState($orderStatus)->setStatus($orderStatus);

        if ($order->getStatus() == 'canceled')
            $order->cancel();

        // Notify the customer
        $notified = $this->sendOrderEmail($order, $data['status_message']);

        if ($statusName == 'Approved') {
            $this->generateTransaction($order, $data['status_message'], $notified);
            $this->generateInvoice($order, $data['status_message']);
        }

        $order->save();
    }

    /**
     * Update order totals from webhook data.
     * 
     * @param OrderInterface $order
     * @param array $data
     */
    public function updateTotals($order, $data)
    {
        $orderTotal = $order->getGrandTotal();
        $totalPaid  = isset($data['total_webhook']) ? $data['total_webhook'] : $orderTotal;
        $paidDiff   = $totalPaid - $orderTotal;

        if ($paidDiff > 0) {
            $order->setFee($paidDiff);
        } elseif ($paidDiff < 0) {
            $order->setDiscountAmount($order->getDiscountAmount() + $paidDiff);
        }

        $order->setGrandTotal($totalPaid);
        $order->setTotalPaid($totalPaid);

        $order->save();
    }

    /**
     * Generate an order transaction.
     * 
     * @param OrderInterface $order
     * @param string $message
     */
    public function generateTransaction($order, $message, $notified)
    {
        $orderPayment = $order->getPayment();
        $orderPayment->setTransactionId($order->getIncrementId());

        $transaction = $this->transactionBuilder
            ->setPayment($orderPayment)
            ->setOrder($order)
            ->setTransactionId($orderPayment->getTransactionId())
            ->build(Transaction::TYPE_AUTH);

        $order->addStatusToHistory(false, $message . '. ID de la transacción: ' . $transaction->getHtmlTxnId(), $notified);

        $order->save();
    }

    /**
     * Generate an order invoice if possible.
     * 
     * @param OrderInterface $order
     * @param string $message
     */
    public function generateInvoice($order, $message)
    {
        if ($order->hasInvoices() || $this->config->getDisableInvoices())
            return false;

        $payment = $order->getPayment();
        $invoice = $order->prepareInvoice();

        $invoice->register();

        if ($payment->getMethodInstance()->canCapture())
            $invoice->capture();

        $order->addRelatedObject($invoice);
        $invoice->addComment($message, true, true);

        if (!$invoice->getEmailSent() && $this->config->getCreateInvoiceEmail()) {
            $this->invoiceSender->send($invoice);
        }

        $invoice->save();
    }

    /**
     * Send an email to customer with the Order information.
     * 
     * @param OrderInterface $order
     * @param string $message
     */
    public function sendOrderEmail($order, $message)
    {
        $emailSent       = $order->getEmailSent();
        $canSendCreation = $this->config->getCreateOrderEmail();
        $canSendUpdate   = $this->config->getUpdateOrderEmail();

        if (!$emailSent) {
            if ($canSendCreation) {
                return $this->orderSender->send($order);
            }
        } else if ($canSendUpdate) {
            return $this->orderCommentSender->send($order, '1', str_replace("<br/>", "", $message));
        }
    }

    /**
     * Get the status config name from transaction status code.
     * 
     * @param OrderInterface $order
     * @param int $statusCode
     * 
     * @return string 
     */
    public function getStatusConfigName($order, $statusCode)
    {
        if ($statusCode == 2 || $statusCode == 3 || $statusCode == 100 || $statusCode == 201) {
            $name = 'InProcess';
        } else if ($statusCode == 4 || $statusCode >= 200 && $statusCode < 400) {
            $name = 'Approved';
        } else {
            $name = $order->getStatus() != 'pending' ? 'Cancelled' : 'Refunded';
        }

        return $name;
    }
}