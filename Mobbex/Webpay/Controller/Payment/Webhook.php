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
     * Webhook constructor.
     * @param Context $context
     * @param Order $_order
     * @param OrderUpdate $orderUpdate
     * @param JsonFactory $resultJsonFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        Order $_order,
        OrderUpdate $orderUpdate,
        JsonFactory $resultJsonFactory,
        LoggerInterface $logger
    ) {
        $this->_order = $_order;
        $this->context = $context;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->_orderUpdate = $orderUpdate;
        $this->log = $logger;

        parent::__construct($context);
    }

    /**
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\Result\Json|\Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $response = [
            "result" => false,
        ];
        $resultJson = $this->resultJsonFactory->create();

        try {
            // get post data
            $postData = $this->getRequest()->getPostValue();
            $orderId = $this->getRequest()->getParam('order_id');

            $data = $postData['data'];

            Data::log(
                "WebHook Controller > Data:" . print_r([
                    "id" => $orderId,
                    "data" => $data,
                ], true),
                "mobbex_" . date('m_Y') . ".log"
            );

            $status = $data['payment']['status']['code'];

            // if data looks fine
            if (isset($orderId) && !empty($status)) {
                // Get Payment ID from Mobbex
                $paymentIdMobbex = $data['payment']['id'];
                // Get Method name from Mobbex
                $paymentMethod = $data['payment']['source']['name'];

                // Just a check ;)
                if (!isset($paymentMethod)) {
                    $paymentMethod = '';
                }

                $order = $this->_order->loadByIncrementId($orderId);
                $formatedPrice = $order->getBaseCurrency()->formatTxt($order->getGrandTotal());

                switch ($status) {
                    case '200':
                        $message = __('Transacción aprobada por %1. Medio de Pago: %2. Id de pago Mobbex: %3', $formatedPrice, $paymentMethod, $paymentIdMobbex);
                        $this->_orderUpdate->approvePayment($order, $message);
                        break;
                    case '2':
                        // Add History Data
                        $order->addStatusToHistory($order->getStatus(), __('Transacción En Progreso por %1. Medio de Pago: %2. Id de pago Mobbex: %3', $formatedPrice, $paymentMethod, $paymentIdMobbex))
                            ->save();
                        break;
                    case '401':
                        $message = __('Transacción cancelada por %1. Medio de Pago: %2. Id de pago Mobbex: %3', $formatedPrice, $paymentMethod, $paymentIdMobbex);

                        if ($order->getStatus() == 'pending') {
                            $this->_orderUpdate->cancelPayment($order, $message);
                        } else {
                            $this->_orderUpdate->refundPayment($order, $message);
                        }
                        break;
                    default:

                        break;

                }
                // redirect to success page
                $response['result'] = true;
            }
        } catch (Exception $e) {
            Data::log('WebHook Controller > Error Paynment Data: ' . $e->getMessage(), "mobbex_error_" . date('m_Y') . ".log");
        }

        // Reply with json
        $resultJson->setData($response);

        return $resultJson;
    }
}
