<?php

namespace Mobbex\Webpay\Model;

use Magento\Sales\Model\Order\Payment\Transaction;

class OrderUpdate
{
    /** 
     * @var array 
     * List of statuses that cancel the order
     */
    public $cancelStatuses = [
        'canceled',
        'cancelled',
        'mobbex_failed',
        'mobbex_rejected',
        'mobbex_refunded',
        'complete'
    ];

    /** @var \Mobbex\Webpay\Helper\Config */
    public $config;

    /** @var \Mobbex\Webpay\Helper\Logger */
    public $logger;

    /** @var \Mobbex\Webpay\Helper\Mobbex */
    public $helper;

    /** @var \Magento\Sales\Model\Order */
    public $_order;

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

    /** @var \Magento\Framework\ObjectManagerInterface */
    protected $_objectManager;

    /** @var \Mobbex\Webpay\Model\CustomField */
    public $customField;

    /** @var \Magento\Sales\Api\OrderManagementInterface */
    public $orderManagement;

    /** @var \Magento\CatalogInventory\Api\StockManagementInterface */
    protected $stockManagement;

    /** @var \Magento\CatalogInventory\Model\Indexer\Stock\Processor */
    protected $stockIndexerProcessor;

    public function __construct(
        \Mobbex\Webpay\Helper\Config $config,
        \Mobbex\Webpay\Helper\Mobbex $helper,
        \Mobbex\Webpay\Helper\Logger $logger,
        \Mobbex\Webpay\Model\CustomFieldFactory $customFieldFactory,
        \Magento\Sales\Model\Order $order,
        \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender,
        \Magento\Sales\Model\Order\Email\Sender\InvoiceSender $invoiceSender,
        \Magento\Sales\Model\Order\Email\Sender\OrderCommentSender $orderCommentSender,
        \Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface $transactionBuilder,
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \Magento\Framework\Module\Manager $moduleManager,
        \Magento\Sales\Model\Order\Invoice $invoice,
        \Magento\Sales\Model\Order\CreditmemoFactory $creditmemoFactory,
        \Magento\Sales\Model\Service\CreditmemoService $creditmemoService,
        \Magento\Framework\ObjectManagerInterface $_objectManager,
        \Magento\Sales\Api\OrderManagementInterface $orderManagement,
        \Magento\CatalogInventory\Api\StockManagementInterface $stockManagement,
        \Magento\CatalogInventory\Model\Indexer\Stock\Processor $stockIndexerProcessor
    ) {
        $this->config                = $config;
        $this->helper                = $helper;
        $this->logger                = $logger;
        $this->_order                = $order;
        $this->orderSender           = $orderSender;
        $this->invoiceSender         = $invoiceSender;
        $this->orderCommentSender    = $orderCommentSender;
        $this->transactionBuilder    = $transactionBuilder;
        $this->resourceConnection    = $resourceConnection;
        $this->moduleManager         = $moduleManager;
        $this->_objectManager        = $_objectManager;
        $this->customField           = $customFieldFactory->create();
        $this->invoice               = $invoice;
        $this->creditmemoFactory     = $creditmemoFactory;
        $this->creditmemoService     = $creditmemoService;
        $this->orderManagement       = $orderManagement;
        $this->stockManagement       = $stockManagement;
        $this->stockIndexerProcessor = $stockIndexerProcessor;
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

        // Uncancel the order first if it was cancelled & new status isnt a cancel status 
        if (in_array($order->getStatus(), $this->cancelStatuses) && !in_array($orderStatus, $this->cancelStatuses))
            $this->uncancell($order);

        if ($orderStatus == 'canceled')
            $this->cancelOrder($order);

        //Set order status
        $order->setState($orderStatus)->setStatus($orderStatus);

        //Update stock reservations
        $refunded = $this->customField->getCustomField($order->getIncrementId(), 'order', 'refunded') === 'yes' ? true : false;

        if ($refunded && $statusName == 'order_status_approved')
            $this->updateStock($order, false);
        else if ($this->config->get('memo_stock') && !$refunded && in_array($order->getStatus(), ['mobbex_failed', 'mobbex_refunded', 'mobbex_rejected']))
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
        //Reset fee & discount
        $order->setFee(0);
        $order->setDiscountAmount(0);

        //Get order total
        $orderTotal  = $order->getGrandTotal();

        //Calculate total paid
        $totalPaid   = isset($data['total']) ? $data['total'] : $orderTotal;

        //Calculate diference between totals
        $paidDiff    = $totalPaid - $orderTotal;
        $paidDiffDes = isset($data['installment_name']) ? $data['installment_name'] : '';

        //Add discount/fee
        if ($paidDiff > 0) {
            $order->setFee($paidDiff);
        } elseif ($paidDiff < 0) {
            $order->setDiscountAmount($order->getDiscountAmount() + $paidDiff);
            $order->setDiscountDescription($paidDiffDes);
        }

        //Update totals
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
        //Return if the mail was sended during order creation
        if($this->config->get('email_before_payment'))
            return;

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
        $stockId    = $this->_objectManager->get('\Magento\InventoryCatalog\Model\GetStockIdForCurrentWebsite');

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

        return $this->customField->saveCustomField($order->getIncrementId(), 'order', 'refunded', $restoreStock ? 'yes' : 'no');
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
     * @param bool $memo for avoid create a credit memo
     * 
     * @return \Magento\Sales\Model\Order|\Magento\Sales\Model\Order\Creditmemo|null
     */
    public function cancelOrder($order, $memo = true)
    {
        // Hook for cancel suborders first
        $this->helper->executeHook('mobbexCancelSubOrder', false, $order->getId());

        // First, try to cancel
        if ($order->canCancel())
            return $this->orderManagement->cancel($order->getId());

        // Exit if it is not refundable
        if (!$order->canCreditmemo() || !$memo )
            return;

        return $this->createCreditMemo($order);
    }

    /**
     * Creates a Magento Credit Memo for a given order.
     * 
     * @param Order $order
     * 
     * @return \Magento\Sales\Model\Order\Creditmemo
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

        //Check if order was refunded previously
        $refunded = $this->customField->getCustomField($order->getIncrementId(), 'order', 'refunded') === 'yes' ? true : false;
        
        //Delete the restored stock to avoid duplicated stock restoration
        if($refunded)
            $this->updateStock($order, false);

        // Back to stock all the items
        foreach ($creditmemo->getAllItems() as $item)
            $item->setBackToStock((bool) $this->config->get('memo_stock'));

        //Set order as refunded
        if($this->config->get('memo_stock'))
            $this->customField->saveCustomField($order->getIncrementId(), 'order', 'refunded', 'yes');

        // Try to refund and return credit memo
        return $this->creditmemoService->refund($creditmemo);
    }

    /**
     * Uncancel an order.
     * 
     * @param \Magento\Sales\Model\Order $order
     */
    public function uncancell($order)
    {
        $productStockQty = $productIds = [];
        $invoiced        = $order->hasInvoices();

        /** Uncancel items */
        foreach ($order->getAllVisibleItems() as $item) {
            $productStockQty[$item->getProductId()] = $item->getQtyCanceled();
            foreach ($item->getChildrenItems() as $child) {
                $productStockQty[$child->getProductId()] = $item->getQtyCanceled();
                $child->setQtyCanceled(0);
                $child->setTaxCanceled(0);
                $child->setDiscountTaxCompensationCanceled(0);

                if($invoiced)
                    $child->setQtyInvoiced($child->getQtyOrdered());
            }
            $item->setQtyCanceled(0);
            $item->setTaxCanceled(0);
            $item->setDiscountTaxCompensationCanceled(0);
            if ($invoiced)
                $item->setQtyInvoiced($item->getQtyOrdered());
        }

        /** Uncancel order data */
        $order->setSubtotalCanceled(0);
        $order->setBaseSubtotalCanceled(0);
        $order->setTaxCanceled(0);
        $order->setBaseTaxCanceled(0);
        $order->setShippingCanceled(0);
        $order->setBaseShippingCanceled(0);
        $order->setDiscountCanceled(0);
        $order->setBaseDiscountCanceled(0);
        $order->setTotalCanceled(0);
        $order->setBaseTotalCanceled(0);

        /** Set status as pending */
        $order->setState('pending')->setStatus('pending');


        /* try revert inventory */
        try {
            $itemsForReindex = $this->stockManagement->registerProductsSale(
                $productStockQty,
                $order->getStore()->getWebsiteId()
            );

            foreach ($itemsForReindex as $item) {
                $item->save();
                $productIds[] = $item->getProductId();
            }

            if (!empty($productIds)) {
                $this->stockIndexerProcessor->reindexList($productIds);
            }
        } catch (\Exception $e) {
            $this->logger->log('error', 'failed to reindex items :' . $e->getMessage());
        }

        $order->setInventoryProcessed(true);

        $order->save();
    }
}
