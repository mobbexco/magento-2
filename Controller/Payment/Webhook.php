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
        \Magento\Quote\Model\QuoteFactory $quoteFactory,
        \Mobbex\Webpay\Helper\Data $helper
    ) {
        $this->_order = $_order;
        $this->context = $context;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->_orderUpdate = $orderUpdate;
        $this->log = $logger;
        $this->quoteFactory = $quoteFactory;
        $this->helper = $helper;

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
            $postData = $request->getPostValue();
            $data     = $postData['data'];
            $orderId  = $request->getParam('order_id');
            $quoteId  = $request->getParam('quote_id');

            // If order ID is empty, try to load from quote id
            if (empty($orderId) && !empty($quoteId)) {
                $quote = $this->quoteFactory->create()->load($quoteId);
                $orderId = $quote->getReservedOrderId();
            }

            Data::log(
                "WebHook Controller > Data:" . json_encode(compact('orderId', 'data'), JSON_PRETTY_PRINT),
                "mobbex_" . date('m_Y') . ".log"
            );

            if (empty($orderId) || empty($data['payment']['status']['code']))
                throw new Exception('Empty Order ID or payment status', 1);

            $order = $this->_order->loadByIncrementId($orderId);

            // Execute own hook to extend functionalities
            $this->helper->mobbex->executeHook('mobbex_webhook_received', [
                'order'   => $order,
                'webhook' => $data,
            ]);

            // Update order data
            $this->_orderUpdate->updateTotals($order, $data);
            $this->_orderUpdate->updateStatus($order, $data);
            $this->_orderUpdate->saveWebhookData($order, $data);

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
}