<?php

namespace Mobbex\Webpay\Model;

use Magento\Sales\Model\Order\Payment\Transaction;

class OrderUpdate
{
    /** @var Mobbex\Webpay\Helper\Instantiator */
    protected $instantiator;

    /** @var OrderSender */
    protected $orderSender;

    /** @var InvoiceSender */
    protected $invoiceSender;

    /** @var OrderCommentSender */
    protected $orderCommentSender;

    /** @var BuilderInterface */
    protected $transactionBuilder;

    /** @var ResourceConnection */
    protected $resourceConnection;

    /** @var \Magento\Framework\Module\Manager */
    protected $moduleManager;

    /** @var \Magento\Sales\Model\Order\Invoice */
    protected $invoice;

    /** @var \Magento\Sales\Model\Order\CreditmemoFactory */
    protected $creditmemoFactory;

    /** @var \Magento\Sales\Model\Service\CreditmemoService */
    protected $creditmemoService;

    public function __construct(
        \Mobbex\Webpay\Helper\Instantiator $instantiator,
        \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender,
        \Magento\Sales\Model\Order\Email\Sender\InvoiceSender $invoiceSender,
        \Magento\Sales\Model\Order\Email\Sender\OrderCommentSender $orderCommentSender,
        \Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface $transactionBuilder,
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \Magento\Framework\Module\Manager $moduleManager,
        \Magento\Sales\Model\Order\Invoice $invoice,
        \Magento\Sales\Model\Order\CreditmemoFactory $creditmemoFactory,
        \Magento\Sales\Model\Service\CreditmemoService $creditmemoService
    ) {
        $instantiator->setProperties($this, ['config', 'logger', 'customFieldFactory', '_order']);
        $this->orderSender        = $orderSender;
        $this->invoiceSender      = $invoiceSender;
        $this->orderCommentSender = $orderCommentSender;
        $this->transactionBuilder = $transactionBuilder;
        $this->resourceConnection = $resourceConnection;
        $this->moduleManager      = $moduleManager;
        $this->objectManager      = $instantiator->_objectManager;
        $this->customFields       = $this->customFieldFactory->create();
        $this->invoice            = $invoice;
        $this->creditmemoFactory  = $creditmemoFactory;
        $this->creditmemoService  = $creditmemoService;
    }

    /**
     * Update order status from webhook data.
     * 
     * @param OrderInterface $order
     * @param int|string $data
     */
    public function updateStatus($order, $data)
    {
        $statusName  = $this->getStatusConfigName($data['status_code']);
        $orderStatus = $this->config->get($statusName);

        if ($orderStatus == $order->getStatus())
            return;

        if ($orderStatus == 'canceled')
            $this->cancelOrder($order);

        //Set order status
        $order->setState($orderStatus)->setStatus($orderStatus);

        //Update stock reservations
        $refunded = $this->customFields->getCustomField($order->getIncrementId(), 'order', 'refunded') === 'yes' ? true : false;

        if ($refunded && $statusName == 'order_status_approved')
            $this->updateStock($order, false);
        else if (!$refunded && in_array($order->getStatus(), ['mobbex_failed', 'mobbex_refunded', 'mobbex_rejected']))
            $this->updateStock($order);

        // Notify the customer
        $notified = $this->sendOrderEmail($order, $data['status_message']);

        if ($statusName == 'order_status_approved') {
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
        $totalPaid  = isset($data['total']) ? $data['total'] : $orderTotal;
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
        if ($order->hasInvoices() || $this->config->get('disable_invoices'))
            return false;

        $payment = $order->getPayment();
        $invoice = $order->prepareInvoice();

        $invoice->register();

        if ($payment->getMethodInstance()->canCapture())
            $invoice->capture();

        $order->addRelatedObject($invoice);
        $invoice->addComment($message, true, true);

        if (!$invoice->getEmailSent() && $this->config->get('create_invoice_email')) {
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
        $canSendCreation = $this->config->get('create_order_email');
        $canSendUpdate   = $this->config->get('update_order_email');

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
     * @param int $statusCode
     * 
     * @return string 
     */
    public function getStatusConfigName($statusCode)
    {
        if ($statusCode == 2 || $statusCode == 201) {
            $name = 'order_status_in_process';
        } else if ($statusCode == 3) {
            $name = 'order_status_authorized';
        } else if ($statusCode == 100) {
            $name = 'order_status_revision';
        } else if ($statusCode == 602 || $statusCode == 605) {
            $name = 'order_status_refunded';
        } else if ($statusCode == 604) {
            $name = 'order_status_rejected';
        } else if ($statusCode == 4 || $statusCode >= 200 && $statusCode < 400) {
            $name = 'order_status_approved';
        } else {
            $name = 'order_status_cancelled';
        }

        return $name;
    }

    /**
     * Update item stock based in the mobbex order status.
     * 
     * @param string $orderId 
     * @param bool $restoreStock 
     * 
     */
    public function updateStock($order, $restoreStock = true)
    {
        // Only execute if inventory is enabled
        if (!$this->isInventoryEnabled())
            return;

        $connection = $this->resourceConnection->getConnection();
        $stockId    = $this->objectManager->get('\Magento\InventoryCatalog\Model\GetStockIdForCurrentWebsite');

        foreach ($order->getAllVisibleItems() as $item) {
            $product = $item->getProduct();
            
            $quantity = $restoreStock ? $item->getQtyOrdered() : '-'.$item->getQtyOrdered();
            $metadata = [
                'event_type'          => $restoreStock ? "back_item_qty" : "order_placed",
                "object_type"         => $restoreStock ? "legacy_stock_management_api" : "order",
                "object_id"           => "",
                "object_increment_id" => $order->getIncrementId()
            ];

            $query = "INSERT INTO inventory_reservation (stock_id, sku, quantity, metadata)
                VALUES (".$stockId->execute().", '".$product->getSku()."', ".$quantity.", '".json_encode($metadata)."');"; 

            //Insert data in db
            $connection->query($query);
        }

        return $this->customFields->saveCustomField($order->getIncrementId(), 'order', 'refunded', $restoreStock ? 'yes' : 'no');
    }

    /**
     * Check if Magento inventory feature is enabled.
     * 
     * @return bool
     */
    public function isInventoryEnabled()
    {
        $requiredModules = [
            'Magento_Inventory',
            'Magento_InventoryApi',
            'Magento_InventoryCatalog',
            'Magento_InventorySalesApi',
            'Magento_InventorySalesApi',
        ];

        // Check if each required module is enabled
        foreach ($requiredModules as $module)
            if (!$this->moduleManager->isEnabled($module))
                return false;

        return true;
    }

    /**
     * Try to cancel or refund an order.
     * 
     * @param \Magento\Sales\Model\Order $order
     * 
     * @return \Magento\Sales\Model\Order|\Magento\Sales\Model\Order\Creditmemo|null
     */
    public function cancelOrder($order)
    {
        // First, try to cancel
        if ($order->canCancel())
            return $order->cancel();

        // Exit if it is not refundable
        if (!$order->canCreditmemo())
            return;
        
        return $this->createCreditMemo($order);
    }

    /**
     * Creates a Magento Credit Memo for a given order
     * @param Order $order
     */
    public function createCreditMemo($order)
    {
        $invoices = $order->getInvoiceCollection() ?: [];

        foreach ($invoices as $invoiceResource)
            $invoiceId = $invoiceResource->getIncrementId();

        if (empty($invoiceId))
            return;

        // Instance invoice and create credit memo
        $invoice    = $this->invoice->loadByIncrementId($invoiceId);
        $creditmemo = $this->creditmemoFactory->createByOrder($order)->setInvoice($invoice);

        // Back to stock all the items
        foreach ($creditmemo->getAllItems() as $item)
            $item->setBackToStock(true);

        // Try to refund and return credit memo
        return $this->creditmemoService->refund($creditmemo);
    }
}