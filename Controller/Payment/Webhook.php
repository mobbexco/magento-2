<?php

namespace Mobbex\Webpay\Controller\Payment;

// Set parent class
class_alias(
    interface_exists('\Magento\Framework\App\CsrfAwareActionInterface')
        ? 'Mobbex\Webpay\Controller\Payment\WebhookBase'
        : 'Magento\Framework\App\Action\Action', 
    'WebhookBase'
);

/**
 * Class Webhook
 * @package Mobbex\Webpay\Controller\Payment
 */
class Webhook extends WebhookBase
{
    /** @var \Magento\Framework\App\Action\Context */
    public $context;

    /** @var \Mobbex\Webpay\Helper\Config */
    public $config;

    /** @var \Mobbex\Webpay\Helper\Mobbex */
    public $helper;

    /** @var \Mobbex\Webpay\Helper\Logger */
    public $logger;

    /** @var \Magento\Quote\Model\QuoteFactory */
    public $quoteFactory;

    /** @var \Mobbex\Webpay\Model\CustomField */
    public $customField;

    /** @var \Mobbex\Webpay\Model\Transaction */
    public $mobbexTransaction;

    /** @var \Mobbex\Webpay\Model\OrderUpdate */
    protected $orderUpdate;

    /** @var \Magento\Sales\Model\Order */
    public $_order;

    public $_request;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Mobbex\Webpay\Helper\Config $config,
        \Mobbex\Webpay\Helper\Mobbex $helper,
        \Mobbex\Webpay\Helper\Logger $logger,
        \Magento\Quote\Model\QuoteFactory $quoteFactory,
        \Mobbex\Webpay\Model\CustomFieldFactory $customFieldFactory,
        \Mobbex\Webpay\Model\TransactionFactory $transactionFactory,
        \Magento\Sales\Model\Order $order,
        \Mobbex\Webpay\Model\OrderUpdate $orderUpdate
    ) {
        parent::__construct($context);

        $this->config            = $config;
        $this->helper            = $helper;
        $this->logger            = $logger;
        $this->quoteFactory      = $quoteFactory;
        $this->orderUpdate       = $orderUpdate;
        $this->mobbexTransaction = $transactionFactory->create();
        $this->customField       = $customFieldFactory->create();
        $this->_order            = $order;
        $this->_request          = $this->getRequest();
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
            $postData = $this->_request->getHeader('Content-Type') == 'application/json' 
                ? json_decode(file_get_contents('php://input'), true) 
                : $this->_request->getPostValue();

            $orderId  = $this->_request->getParam('order_id');
            $quoteId  = $this->_request->getParam('quote_id');
            $token    = $this->_request->getParam('mbbx_token');
            $data     = $this->mobbexTransaction->formatWebhookData($postData['data'], $orderId);

            // If order ID is empty, try to load from quote id
            if (empty($orderId) && !empty($quoteId)) {
                $quote = $this->quoteFactory->create()->load($quoteId);
                $orderId = $quote->getReservedOrderId();
            }

            $this->logger->log('debug', "WebHook Controller > execute", compact('orderId', 'data', 'token'));

            // Validate token
            if (!$this->config->validateToken($token))
                throw new \Exception("Invalid Token: $token", 1);

            if (empty($orderId) || empty($data['status_code']))
                throw new \Exception('Empty Order ID or payment status', 1);

            if (strpos($data['payment_id'], 'GRP-') !== 0)
                return $this->logger->createJsonResponse('debug', 'Ignored GRP webhook', $data['order_id']);

            // Save transaction to db and load order
            $trx = $this->mobbexTransaction->saveTransaction($data);
            $order = $this->_order->loadByIncrementId($orderId);

            if (!$data['parent'])
                return $this->helper->executeHook('mobbexChildWebhookReceived', false, $postData['data'], $order);

            // Get the order status
            $statusName  = $this->orderUpdate->getStatusConfigName($data['status_code']);
            $orderStatus = $this->config->get($statusName);

            if (in_array($orderStatus, $this->orderUpdate->cancelStatuses))
                return $this->processRefund($data, $orderStatus);

            // Exit if it is a expired operation and the order has already been paid
            if ($data['status_code'] == 401 && $order->getTotalPaid() > 0)
                return $this->logger->createJsonResponse('debug', 'Expired operation webhook received after payment', [$orderId, $trx->getId()]);

            // Save payment data on additional information
            $order->getPayment()->setAdditionalInformation('paymentResponse', $data);

            // Update order data
            $this->orderUpdate->updateTotals($order, $data);
            $this->orderUpdate->updateStatus($order, $data);

            // Execute own hook to extend functionalities
            $this->helper->executeHook('mobbexWebhookReceived', false, $postData['data'], $order);

            // Redirect to sucess page
            $response['result'] = true;
            
        } catch (\Exception $e) {
            $this->logger->createJsonResponse('error', 'WebHook Controller > Error Payment Data: ' . $e->getMessage());
        }

        return $this->logger->createJsonResponse('debug', 'WebHook Received OK: ', $response);
    }

    public function processRefund($data, $orderStatus)
    {
        //Load Order
        $this->_order->loadByIncrementId($data['order_id']);

        //Get previous refunds
        $totalRefunded = (float) $this->customField->getCustomField($data['order_id'], 'order', 'total_refunded') + $data['total'];
        $totalPaid     = $this->_order->getGrandTotal() - $totalRefunded;

        //Save total refunded
        $this->customField->saveCustomField($data['order_id'], 'order', 'total_refunded', $totalRefunded);
        
        if ($data['parent'] || $totalPaid <= 0){
            $this->orderUpdate->cancelOrder($this->_order, $orderStatus !== 'mobbex_failed');
            $this->orderUpdate->updateStatus($this->_order, $data);
        }

        // Execute own hook to extend functionalities
        $this->helper->executeHook('mobbexWebhookReceived', false, json_decode($data['data'], true), $this->_order);
        
        return $this->logger->createJsonResponse('debug', 'Webhook > processRefund | WebHook Received OK: ', $data);
    }
}