<?php

namespace Mobbex\Webpay\Controller\Payment;

use Exception;

/**
 * Class Webhook
 * @package Mobbex\Webpay\Controller\Payment
 */
class Webhook extends \Mobbex\Webpay\Controller\Payment\WebhookBase
{
    /** @var \Mobbex\Webpay\Model\OrderUpdate */
    protected $orderUpdate;

    /** @var \Mobbex\Webpay\Helper\Logger */
    public $logger;

    public function __construct(
        \Mobbex\Webpay\Helper\Instantiator $instantiator,
        \Magento\Framework\App\Action\Context $context,
        \Mobbex\Webpay\Model\TransactionFactory $transactionFactory,
        \Mobbex\Webpay\Model\OrderUpdate $orderUpdate
    ) {
        parent::__construct($context);
        $instantiator->setProperties($this, ['config', 'logger', 'helper', 'quoteFactory', 'customFieldFactory', '_order']);
        $this->orderUpdate       = $orderUpdate;
        $this->_request          = $this->getRequest();
        $this->mobbexTransaction = $transactionFactory->create();
        $this->customField       = $this->customFieldFactory->create();
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
            $postData = isset($_SERVER['CONTENT_TYPE']) && $_SERVER['CONTENT_TYPE'] == 'application/json' ? json_decode(file_get_contents('php://input'), true) : $this->_request->getPostValue();
            $orderId  = $this->_request->getParam('order_id');
            $quoteId  = $this->_request->getParam('quote_id');
            $data     = $this->mobbexTransaction->formatWebhookData($postData['data'], $orderId);

            // If order ID is empty, try to load from quote id
            if (empty($orderId) && !empty($quoteId)) {
                $quote = $this->quoteFactory->create()->load($quoteId);
                $orderId = $quote->getReservedOrderId();
            }

            $this->logger->log('debug', "WebHook Controller > execute", compact('orderId', 'data'));

            //Avoid duplicated child webhooks
            if (!$data['parent'] && $data['payment_id'] && $this->mobbexTransaction->getTransactions(['payment_id' => $data['payment_id']]))
                return $this->logger->createJsonResponse('debug', 'Webhook > execute | WebHook Received OK: ', $data);

            if (empty($orderId) || empty($data['status_code']))
                throw new \Exception('Empty Order ID or payment status', 1);

            // Save transaction to db
            $trx = $this->mobbexTransaction->saveTransaction($data);

            if(in_array($data['status_code'], ['601', '602', '603', '604', '605', '610']))
                return $this->processRefund($data);

            if (!$data['parent'])
                return;

            $order = $this->_order->loadByIncrementId($orderId);

            // Execute own hook to extend functionalities
            $this->helper->executeHook('mobbexWebhookReceived', false, $postData['data'], $order);

            // Exit if it is a expired operation and the order has already been paid
            if ($data['status_code'] == 401 && $order->getTotalPaid() > 0)
                return $this->logger->createJsonResponse('debug', 'Expired operation webhook received after payment', [$orderId, $trx->getId()]);

            // Save payment data on additional information
            $order->getPayment()->setAdditionalInformation('paymentResponse', $data);

            // Update order data
            $this->orderUpdate->updateTotals($order, $data);
            $this->orderUpdate->updateStatus($order, $data);

            // Redirect to sucess page
            $response['result'] = true;
            
        } catch (\Exception $e) {
            $this->logger->createJsonResponse('error', 'WebHook Controller > Error Paynment Data: ' . $e->getMessage());
        }

        return $this->logger->createJsonResponse('debug', 'WebHook Received OK: ', $response);
    }

    public function processRefund($data)
    {
        //Load Order
        $this->_order->loadByIncrementId($data['order_id']);

        //Get previous refunds
        $totalRefunded = (float) $this->customField->getCustomField($data['order_id'], 'order', 'total_refunded') + $data['total'];
        $totalPaid     = $this->_order->getGrandTotal() - $totalRefunded;

        //Save total refunded
        $this->customField->saveCustomField($data['order_id'], 'order', 'total_refunded', $totalRefunded);

        if ($data['parent'] || $totalPaid <= 0){
            $this->orderUpdate->cancelOrder($this->_order);
            $this->orderUpdate->updateStatus($this->_order, $data);
        }

        return $this->logger->createJsonResponse('debug', 'Webhook > processRefund | WebHook Received OK: ', $data);
    }
}