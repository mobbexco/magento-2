<?php

namespace Mobbex\Webpay\Controller\Payment;

use Exception;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Sales\Model\Order;
use Mobbex\Webpay\Helper\Data;
use Mobbex\Webpay\Model\Mobbex;
use Mobbex\Webpay\Model\OrderUpdate;
use Psr\Log\LoggerInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\InventoryCatalog\Model\GetStockIdForCurrentWebsite;

/**
 * Class Webhook
 * @package Mobbex\Webpay\Controller\Payment
 */
class Webhook extends WebhookBase
{
    
    /**
     * @var Context
     */
    public $context;

    /**
     * @var Order
     */
    protected $_order;

    /**
     * @var JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * @var LoggerInterface
     */
    protected $log;

    /**
     * @var OrderUpdate
     */
    protected $_orderUpdate;

    /**
     *
     * @var \Magento\Quote\Model\QuoteFactory
     */
    protected $quoteFactory;

    /**
     * 
     * @var ResourceConnection
     */
    protected $resourceConnection;

    /**
     * 
     * @var GetStockIdForCurrentWebsite
     */
    protected $stockId;

    /**
     * Webhook constructor.
     * @param Context $context
     * @param Order $_order
     * @param OrderUpdate $orderUpdate
     * @param JsonFactory $resultJsonFactory
     * @param LoggerInterface $logger
     * @param CartRepositoryInterface $quoteRepository,
     */

    public function __construct(
        Context $context,
        Order $_order,
        OrderUpdate $orderUpdate,
        JsonFactory $resultJsonFactory,
        LoggerInterface $logger,
        \Mobbex\Webpay\Helper\Config $config,
        \Magento\Quote\Model\QuoteFactory $quoteFactory,
        \Mobbex\Webpay\Helper\Data $helper,
        \Mobbex\Webpay\Model\MobbexTransactionFactory $mobbexTransactionFactory,
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \Magento\InventoryCatalog\Model\GetStockIdForCurrentWebsite $stockId
    ) {

        $this->_order             = $_order;
        $this->context            = $context;
        $this->resultJsonFactory  = $resultJsonFactory;
        $this->_orderUpdate       = $orderUpdate;
        $this->log                = $logger;
        $this->quoteFactory       = $quoteFactory;
        $this->helper             = $helper;
        $this->mobbexTransaction  = $mobbexTransactionFactory->create();
        $this->config             = $config;
        $this->resourceConnection = $resourceConnection;
        $this->stockId            = $stockId;

        parent::__construct($context);
    }

    /**
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\Result\Json|\Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $response = [
            'result' => false,
        ];

        try {
            // Get request data
            $request  = $this->getRequest();
            $postData = isset($_SERVER['CONTENT_TYPE']) && $_SERVER['CONTENT_TYPE'] == 'application/json' ? json_decode(file_get_contents('php://input'), true) : $request->getPostValue();
            $orderId  = $request->getParam('order_id');
            $quoteId  = $request->getParam('quote_id');
            $data     = $this->formatWebhookData($postData['data'], $orderId, $this->config->getMulticard(), $this->config->getMultivendor());
            
            // If order ID is empty, try to load from quote id
            if (empty($orderId) && !empty($quoteId)) {
                $quote = $this->quoteFactory->create()->load($quoteId);
                $orderId = $quote->getReservedOrderId();
            }
            
            Data::log(
                "WebHook Controller > Data:" . json_encode(compact('orderId', 'data'), JSON_PRETTY_PRINT),
                "mobbex_" . date('m_Y') . ".log"
            );

            //Save webhook data en database
            $this->mobbexTransaction->saveTransaction($data);

            if($data['parent'] == false) {
                return;
            }

            if (empty($orderId) || empty($data['status_code']))
                throw new Exception('Empty Order ID or payment status', 1);

            $order = $this->_order->loadByIncrementId($orderId);

            // Execute own hook to extend functionalities
            $this->helper->mobbex->executeHook('mobbexWebhookReceived', false, $postData['data'], $order);

            //Update items stock
            if($data['status_code'] >= 400)
                $this->updateStock($orderId);
            if($data['status_code'] < 400 && $order->getStatus() === $this->config->getOrderStatusCancelled() || $order->getStatus() === $this->config->getOrderStatusRefunded())
                $this->updateStock($orderId, false);
            
            // Update order data
            $this->_orderUpdate->updateTotals($order, $data);
            $this->_orderUpdate->updateStatus($order, $data);

            // Redirect to sucess page
            $response['result'] = true;
        } catch (\Exception $e) {
            Data::log('WebHook Controller > Error Paynment Data: ' . $e->getMessage(), "mobbex_error_" . date('m_Y') . ".log");
        }

        // Reply with json
        $resultJson = $this->resultJsonFactory->create();
        $resultJson->setData($response);

        return $resultJson;
    }

    /**
     * Format the webhook data in an array.
     * 
     * @param array $webhook_data
     * @param int $order_id
     * @param bool $multicard
     * @param bool $multivendor
     * @return array $data
     * 
     */
    public function formatWebhookData($webhookData, $orderId, $multicard, $multivendor)
    {
        $data = [
            'order_id'           => $orderId,
            'parent'             => $this->isParent($webhookData['payment']['operation']['type'], $multicard, $multivendor) ? true : false,
            'operation_type'     => isset($webhookData['payment']['operation']['type']) ? $webhookData['payment']['operation']['type'] : '',
            'payment_id'         => isset($webhookData['payment']['id']) ? $webhookData['payment']['id'] : '',
            'description'        => isset($webhookData['payment']['description']) ? $webhookData['payment']['description'] : '',
            'status_code'        => isset($webhookData['payment']['status']['code']) ? $webhookData['payment']['status']['code'] : '',
            'status_message'     => isset($webhookData['payment']['status']['message']) ? $webhookData['payment']['status']['message'] : '',
            'source_name'        => isset($webhookData['payment']['source']['name']) ? $webhookData['payment']['source']['name'] : 'Mobbex',
            'source_type'        => isset($webhookData['payment']['source']['type']) ? $webhookData['payment']['source']['type'] : 'Mobbex',
            'source_reference'   => isset($webhookData['payment']['source']['reference']) ? $webhookData['payment']['source']['reference'] : '',
            'source_number'      => isset($webhookData['payment']['source']['number']) ? $webhookData['payment']['source']['number'] : '',
            'source_expiration'  => isset($webhookData['payment']['source']['expiration']) ? json_encode($webhookData['payment']['source']['expiration']) : '',
            'source_installment' => isset($webhookData['payment']['source']['installment']) ? json_encode($webhookData['payment']['source']['installment']) : '',
            'installment_name'   => isset($webhookData['payment']['source']['installment']['description']) ? json_encode($webhookData['payment']['source']['installment']['description']) : '',
            'installment_amount' => isset($webhookData['payment']['source']['installment']['amount']) ? $webhookData['payment']['source']['installment']['amount'] : '',
            'installment_count'  => isset($webhookData['payment']['source']['installment']['count']) ? $webhookData['payment']['source']['installment']['count'] : '',
            'source_url'         => isset($webhookData['payment']['source']['url']) ? json_encode($webhookData['payment']['source']['url']) : '',
            'cardholder'         => isset($webhookData['payment']['source']['cardholder']) ? json_encode(($webhookData['payment']['source']['cardholder'])) : '',
            'entity_name'        => isset($webhookData['entity']['name']) ? $webhookData['entity']['name'] : '',
            'entity_uid'         => isset($webhookData['entity']['uid']) ? $webhookData['entity']['uid'] : '',
            'customer'           => isset($webhookData['customer']) ? json_encode($webhookData['customer']) : '',
            'checkout_uid'       => isset($webhookData['checkout']['uid']) ? $webhookData['checkout']['uid'] : '',
            'total'              => isset($webhookData['payment']['total']) ? $webhookData['payment']['total'] : '',
            'currency'           => isset($webhookData['checkout']['currency']) ? $webhookData['checkout']['currency'] : '',
            'risk_analysis'      => isset($webhookData['payment']['riskAnalysis']['level']) ? $webhookData['payment']['riskAnalysis']['level'] : '',
            'data'               => json_encode($webhookData),
            'created'            => isset($webhookData['payment']['created']) ? $webhookData['payment']['created'] : '',
            'updated'            => isset($webhookData['payment']['updated']) ? $webhookData['payment']['created'] : '',
        ];

        return $data;
    }

    /**
     * Receives the webhook "operation type" and return true if the webhook is parent and false if not
     * 
     * @param string $operationType
     * @param bool $multicard
     * @param bool $multivendor
     * @return bool true|false
     * 
     */
    public function isParent($operationType, $multicard, $multivendor)
    {
        if ($operationType === "payment.v2") {
            if ($multicard || $multivendor !== 'disable')
                return false;
        }
        return true;
    }

    /**
     * Update item stock based in the mobbex order status.
     * 
     * @param string $orderId 
     * @param bool $restoreStock 
     * 
     */
    private function updateStock($orderId, $restoreStock = true)
    {
        $connection = $this->resourceConnection->getConnection();
        $order = $this->_order->loadByIncrementId($orderId);

        foreach ($order->getAllVisibleItems() as $item) {
            $product = $item->getProduct();
            
            $quantity = $restoreStock ? $item->getQtyOrdered() : '-'.$item->getQtyOrdered();
            $metadata = [
                'event_type'          => $restoreStock ? "back_item_qty" : "order_placed",
                "object_type"         => $restoreStock ? "legacy_stock_management_api" : "order",
                "object_id"           => "",
                "object_increment_id" => $orderId  
            ];

            $data[] = [
                $item->getStockId(),
                $product->getSku(),
                $quantity,
                json_encode($metadata)
            ];

            $query = "INSERT INTO inventory_reservation (stock_id, sku, quantity, metadata)
                VALUES (".$this->stockId->execute().", '".$product->getSku()."', ".$quantity.", '".json_encode($metadata)."');"; 

            //Insert data in db
            $connection->query($query);
        }

        return;
    }
}