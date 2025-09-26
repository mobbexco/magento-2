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

    /** @var \Mobbex\Webpay\Model\CustomFieldFactory */
    public $cf;

    /** @var \Mobbex\Webpay\Model\Transaction */
    public $mobbexTransaction;

    /** @var \Magento\Sales\Api\OrderRepositoryInterface */
    public $orderRepository;

    /** @var \Magento\Quote\Api\CartManagementInterface */
    public $cartManagement;

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
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Quote\Api\CartManagementInterface $cartManagement,
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
        $this->cf                = $customFieldFactory;
        $this->orderRepository   = $orderRepository;
        $this->cartManagement    = $cartManagement;
        $this->_order            = $order;
        $this->_request          = $this->getRequest();
    }

    /**
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        try {
            $token = $this->_request->getParam('mbbx_token');
            $orderId = $this->_request->getParam('order_id');
            $postData = json_decode(file_get_contents('php://input'), true);

            $this->logger->log('debug', "WebHook Controller > execute", compact('token', 'orderId', 'postData'));

            if (!$this->config->validateToken($token))
                throw new \Exception('Invalid Token');

            if (empty($postData['type']) || empty($postData['data']))
                throw new \Exception('Invalid Webhook Data');

            $order = $this->orderRepository->get($orderId);

            switch ($postData['type']) {
                case 'checkout':
                    return $this->processCheckout($order, $postData);
                case 'checkout:expired':
                    return $this->processExpiredCheckout($order, $postData);
                default:
                    return $this->logger->createJsonResponse('debug', 'Webhook type not supported', $postData['type']);
            }
        } catch (\Exception $e) {
            return $this->logger->createJsonResponse('error', 'WebHook Controller > Error Payment Data: ' . $e->getMessage());
        }
    }

    public function processExpiredCheckout($order, $postData)
    {
        $status = isset($postData['data']['status']['code']) ? $postData['data']['status']['code'] : null;

        if (empty($status))
            throw new \Exception('Empty status code for expired checkout');

        // Exit if it is a expired operation and the order has already been paid
        if ($order->getTotalPaid() > 0)
            return $this->logger->createJsonResponse('debug', 'Expired operation webhook received after payment');

        // Update order status to failed
        $this->orderUpdate->updateStatus($order, [
            'status_code' => $status,
            'status_message' => 'Checkout expirado'
        ]);

        // Remove checkout uid custom field
        $this->cf->create()->deleteCustomField(
            $order->getId(),
            'order',
            'checkout_uid'
        );

        return $this->logger->createJsonResponse(
            'debug',
            'Expired Checkout Webhook Processed'
        );
    }

    public function processCheckout($order, $postData)
    {
        $data = $this->mobbexTransaction->formatWebhookData($postData['data']);

        if (empty($data['status_code']))
            throw new \Exception('Empty payment status');

        if (strpos($data['payment_id'], 'GRP-') !== false)
            return $this->logger->createJsonResponse('debug', 'Ignored GRP webhook', $data);

        // Save transaction to db
        $data['order_id'] = $order->getIncrementId();
        $trx = $this->mobbexTransaction->saveTransaction($data);

        // Get the order status
        $statusName  = $this->orderUpdate->getStatusConfigName($data['status_code']);
        $orderStatus = $this->config->get($statusName);

        // Ignore refund webhook if online refunds is active
        if ($statusName == 'order_status_refunded' && $this->config->get('online_refund'))
            return $this->logger->createJsonResponse('debug', 'Ignored Refund Webhook (online refunds)');

        // Ignore 3xx status codes
        if ($data['status_code'] > 299 && $data['status_code'] < 400)
            return $this->logger->createJsonResponse('debug', 'Webhook > execute | WebHook Received OK: ', $data);

        // Execute hook on child webhooks and return
        if (!$data['parent']) {
            $this->helper->executeHook('mobbexChildWebhookReceived', false, $postData['data'], $order);

            return $this->logger->createJsonResponse('debug', 'Child Webhook Received');
        }

        if (in_array($orderStatus, $this->orderUpdate->cancelStatuses))
            return $this->processRefund($data, $orderStatus);

        // Save payment data on additional information
        $order->getPayment()->setAdditionalInformation('paymentResponse', $data);

        // Update order data
        $this->orderUpdate->updateTotals($order, $data);
        $this->orderUpdate->updateStatus($order, $data);

        // Execute own hook to extend functionalities
        $this->helper->executeHook('mobbexWebhookReceived', false, $postData['data'], $order);

        return $this->logger->createJsonResponse('debug', 'WebHook Received OK');
    }

    public function processRefund($data, $orderStatus)
    {
        //Load Order
        $this->_order->loadByIncrementId($data['order_id']);

        $this->orderUpdate->cancelOrder($this->_order, $orderStatus !== 'mobbex_failed');
        $this->orderUpdate->updateStatus($this->_order, $data);

        // Execute own hook to extend functionalities
        $this->helper->executeHook('mobbexWebhookReceived', false, json_decode($data['data'], true), $this->_order);

        return $this->logger->createJsonResponse('debug', 'Webhook > processRefund | WebHook Received OK');
    }
}