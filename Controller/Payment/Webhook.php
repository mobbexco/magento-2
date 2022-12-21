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
    protected $_orderUpdate;

    /** @var \Mobbex\Webpay\Helper\Logger */
    public $logger;

    public function __construct(
        \Mobbex\Webpay\Helper\Instantiator $instantiator,
        \Magento\Framework\App\Action\Context $context,
        \Mobbex\Webpay\Model\TransactionFactory $transactionFactory,
        \Mobbex\Webpay\Model\OrderUpdate $orderUpdate
    ) {
        parent::__construct($context);
        $instantiator->setProperties($this, ['config', 'logger', 'helper', 'quoteFactory', '_order']);
        $this->orderUpdate       = $orderUpdate;
        $this->_request          = $this->getRequest();
        $this->mobbexTransaction = $transactionFactory->create();
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
            // Getrequest data
            $postData = isset($_SERVER['CONTENT_TYPE']) && $_SERVER['CONTENT_TYPE'] == 'application/json' ? json_decode(file_get_contents('php://input'), true) : $this->_request->getPostValue();
            $orderId  = $this->_request->getParam('order_id');
            $quoteId  = $this->_request->getParam('quote_id');
            $data     = $this->formatWebhookData($postData['data'], $orderId);
            
            // If order ID is empty, try to load from quote id
            if (empty($orderId) && !empty($quoteId)) {
                $quote = $this->quoteFactory->create()->load($quoteId);
                $orderId = $quote->getReservedOrderId();
            }
            
            $this->logger->debug('debug', "WebHook Controller > ", compact('orderId', 'data'));

            //Save webhook data en database
            $this->mobbexTransaction->saveTransaction($data);

            if($data['parent'] == false) {
                return;
            }

            if (empty($orderId) || empty($data['status_code']))
                throw new Exception('Empty Order ID or payment status', 1);

            $order = $this->_order->loadByIncrementId($orderId);

            // Execute own hook to extend functionalities
            $this->helper->executeHook('mobbexWebhookReceived', false, $postData['data'], $order);

            // Update order data
            $this->orderUpdate->updateTotals($order, $data);
            $this->orderUpdate->updateStatus($order, $data);

            // Redirect to sucess page
            $response['result'] = true;
            
        } catch (\Exception $e) {
            $this->logger->createJsonResponse('err', 'WebHook Controller > Error Paynment Data: ' . $e->getMessage());
        }

        return $this->logger->createJsonResponse('debug', 'WebHook Received OK: ', $response);
    }

    /**
     * Format the webhook data to save in db.
     * 
     * @param array $webhookData
     * @param int $orderId
     * 
     * @return array
     */
    public function formatWebhookData($webhookData, $orderId)
    {
        $data = [
            'order_id'           => $orderId,
            'parent'             => isset($webhookData['payment']['id']) ? $this->isParent($webhookData['payment']['id']) : false,
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
     * Check if webhook is parent type using him payment id.
     * 
     * @param string $paymentId
     * 
     * @return bool
     */
    public function isParent($paymentId)
    {
        return strpos($paymentId, 'CHD-') !== 0;
    }
}