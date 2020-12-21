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
        \Magento\Quote\Model\QuoteFactory $quoteFactory
    ) {
        $this->_order = $_order;
        $this->context = $context;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->_orderUpdate = $orderUpdate;
        $this->log = $logger;
        $this->quoteFactory = $quoteFactory;

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

                $order = $this->_order->loadByIncrementId($orderId);
                $paymentOrder = $order->getPayment();
                
                $mobbexPaymentId    = $data['payment']['id'];
                $paymentMethod      = isset($data['payment']['source']['name']) ? $data['payment']['source']['name'] : '';
                $mobbexRiskAnalysis = $data['payment']['riskAnalysis']['level'];

                $totalPaid = $data['payment']['total'];
                $this->addFeeOrDiscount($totalPaid, $order);
                $formatedPrice = $order->getBaseCurrency()->formatTxt($order->getGrandTotal());
                
                $source         = $data['payment']['source'];
                $mainMobbexNote = 'ID de Operación Mobbex: ' . $mobbexPaymentId . '. ';
            
                // Save order url
                if (!empty($data['entity']['uid'])) {
                    $mobbexOrderUrl = 'https://mobbex.com/console/' . $data['entity']['uid'] . '/operations/?oid=' . $mobbexPaymentId;
        
                    $paymentOrder->setAdditionalInformation('mobbex_order_url', $mobbexOrderUrl);
                    $order->addStatusHistoryComment('URL al Cupón: ' . $mobbexOrderUrl);
                }
                
                // Save payment info
                if ($source['type'] == 'card') {
                    $mobbexCardPaymentInfo = $paymentMethod . ' ( ' . $source['number'] . ' )';
                    $mobbexCardPlan = $source['installment']['description'] . '. ' . $source['installment']['count'] . ' Cuota/s' . ' de ' . $source['installment']['amount'];
                    
                    $paymentOrder->setAdditionalInformation('mobbex_card_info', $mobbexCardPaymentInfo);
                    $paymentOrder->setAdditionalInformation('mobbex_card_plan', $mobbexCardPlan);
                    
                    $mainMobbexNote .= 'Pago realizado con ' . $mobbexCardPaymentInfo . '. ' . $mobbexCardPlan . '. ';
                } else {
                    $mainMobbexNote .= 'Pago realizado con ' . $paymentMethod . '. ';
                }
                
                // Save risk analysis
                if (!empty($mobbexRiskAnalysis)) {
                    $order->addStatusHistoryComment('El riesgo de la operación fue evaluado en: ' . $mobbexRiskAnalysis);
                }

                $order->addStatusHistoryComment($mainMobbexNote);
                $order->save();
                $paymentOrder->setAdditionalInformation('mobbex_data', $data);
                $paymentOrder->save();

                if ($status == 2 || $status == 3 || $status == 100) {
                    // Set payment status
                    $order->setState(Order::STATE_PENDING_PAYMENT)->setStatus(Order::STATE_PENDING_PAYMENT)->save();
                    // Add History Data
                    $order->addStatusToHistory($order->getStatus(), __('Transacción En Progreso por %1. Medio de Pago: %2. Id de pago Mobbex: %3', $formatedPrice, $paymentMethod, $mobbexPaymentId))
                        ->save();
                } else if ($status == 4 || $status >= 200 && $status < 400) {
                    $message = __('Transacción aprobada por %1. Medio de Pago: %2. Id de pago Mobbex: %3', $formatedPrice, $paymentMethod, $mobbexPaymentId);
                    $this->_orderUpdate->approvePayment($order, $message);
                } else {
                    $message = __('Transacción cancelada por %1. Medio de Pago: %2. Id de pago Mobbex: %3', $formatedPrice, $paymentMethod, $mobbexPaymentId);

                    if ($order->getStatus() == 'pending') {
                        $this->_orderUpdate->cancelPayment($order, $message);
                    } else {
                        $this->_orderUpdate->refundPayment($order, $message);
                    }
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

    /**
     * Add fee or discount to order & quote
     * 
     * @param int $totalPaid
     * @param Order $order
     * 
     * @return bool
     */
    public function addFeeOrDiscount($totalPaid, $order)
    {
        $orderTotal = $order->getGrandTotal();
        $quote = $this->quoteFactory->create()->load($order->getQuoteId());

        if ($orderTotal == $totalPaid) {
            return false;
        }

        $fee = $totalPaid - $orderTotal;

        $quote->setFee($fee);
        $order->setFee($fee);

        $order->setGrandTotal($totalPaid);
        $quote->setGrandTotal($totalPaid);

        $quote->save();
    }
}