<?php

namespace Mobbex\Webpay\Controller\Payment;

use \Magento\Framework\App\ObjectManager;
use \Magento\Sales\Model\Order;
use \Magento\Sales\Model\Order\Payment\Transaction;
use \Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use \Magento\Framework\DB\TransactionFactory;

use \Mobbex\Webpay\Model\Mobbex;

class Webhook extends WebhookBase
{
    public $context;

    protected $_invoiceService;
    protected $_order;
    protected $_transaction;

    protected $transactionBuilder;

    protected $transactionFactory;
    protected $invoiceSender;

    protected $resultJsonFactory;

    protected $log;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Sales\Model\Service\InvoiceService $_invoiceService,
        \Magento\Sales\Model\Order $_order,
        \Magento\Framework\DB\Transaction $_transaction,
        \Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface $transactionBuilder,
        TransactionFactory $transactionFactory,
        InvoiceSender $invoiceSender,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->_invoiceService = $_invoiceService;
        $this->_transaction = $_transaction;
        $this->_order = $_order;
        $this->context = $context;

        $this->transactionBuilder = $transactionBuilder;

        $this->transactionFactory = $transactionFactory;
        $this->invoiceSender = $invoiceSender;

        $this->resultJsonFactory = $resultJsonFactory;

        $this->log = $logger;

        parent::__construct($context);
    }

    public function execute()
    {
        $response = [
            "result" => false,
        ];

        try {
            // get post data
            $postData = $this->getRequest()->getPostValue();
            $orderId = $this->getRequest()->getParam('order_id');

            $this->log->debug('WebHook Controller > Data', [
                "id" => $orderId,
                "data" => $postData,
            ]);

            $data = $postData['data'];

            $status = $data['payment']['status']['code'];

            $this->log->debug('WebHook Controller > Data', [
                "id" => $orderId,
                "status" => $status,
                "data" => $data,
            ]);

            $resultJson = $this->resultJsonFactory->create();

            // if data looks fine
            if (isset($orderId) && !empty($status)) {
                // Get Payment ID from Mobbex
                $paymentId = $data['payment']['id'];
                // Get Method name from Mobbex
                $paymentMethod = $data['payment']['source']['name'];

                // Just a check ;)
                if(!isset($paymentMethod)) {
                    $paymentMethod = '';
                }

                // get object manager
                $objectManager = ObjectManager::getInstance();

                // set order status
                $this->_order->loadByIncrementId($orderId);

                // Formatted price
                $formatedPrice = $this->_order->getBaseCurrency()->formatTxt($this->_order->getGrandTotal());

                if ($status == "200") { // Paid State handling
                    $message = __('Transacción aprobada por %1. Medio de Pago: %2', $formatedPrice, $paymentMethod);

                    // Set State
                    $this->_order->setState(Order::STATE_PROCESSING)->setStatus(Order::STATE_PROCESSING)->save();
                    // Add History Data
                    $this->_order->addStatusToHistory($this->_order->getStatus(), $message)->save();

                    $this->addPaymentInformation($this->_order, $data);

                    // send order email
                    $emailSender = $objectManager->create('\Magento\Sales\Model\Order\Email\Sender\OrderSender');
                    $emailSender->send($this->_order);

                    $this->createInvoice($this->_order, $message);
                } else if($status == "2") { // In progress for Cash Payments only
                    // Set State
                    $this->_order->setState(Order::STATE_PENDING_PAYMENT)->setStatus(Order::STATE_PENDING_PAYMENT)->save();
                    // Add History Data
                    $this->_order->addStatusToHistory($this->_order->getStatus(), ___('Transacción En Progreso por %1. Medio de Pago: %2', $formatedPrice, $paymentMethod))->save();
                } else { // All the other states
                    $this->_order->setState(Order::STATE_NEW)->setStatus(Order::STATE_NEW)->save();
                    $this->_order->addStatusToHistory($this->_order->getStatus(), __('Transacción denegada por %1. Medio de Pago: %2', $formatedPrice, $paymentMethod));
                    $this->_order->save();
                }

                // redirect to success page
                $response['result'] = true;
            }

        } catch (Exception $e) {
            $this->log->debug('WebHook Controller > Error Paynment Data', [
                "message" => $e->getMessage(),
            ]);
        }

        // Reply with json
        $resultJson->setData($response);

        return $resultJson;
    }

    private function addPaymentInformation($order, $data)
    {
        try {
            // Grab some info from the WebHook information
            $paymentId = $data['payment']['id'];
            $paymentMethod = $data['payment']['source']['name'];
            $status = $data['payment']['status']['code'];
            $statusText = $data['payment']['status']['text'];
            $paymentRef = $data['payment']['reference'];

            // Prepare payment object
            $payment = $order->getPayment();

            $payment->setMethod(Mobbex::CODE);

            $payment->setLastTransId($paymentId);
            $payment->setTransactionId($paymentId);

            // Info in the Transaction
            $additionalInfo = [
                "ID Transacción" => $paymentId,
                "Método de Pago" => $paymentMethod,
                "Estado Actual" => $statusText,
                "Referencia de Pago" => $paymentRef,
            ];

            // Custom Data to Show on Info
            $payment->setAdditionalInformation([
                "id" => $paymentId,
                "paymentMethod" => $paymentMethod,
                "status" => $status,
                "statusText" => $statusText,
                "reference" => $paymentRef,
                Transaction::RAW_DETAILS => (array) $data,
            ]);

            // Formatted price
            $formatedPrice = $order->getBaseCurrency()->formatTxt($order->getGrandTotal());

            // Prepare transaction
            $transaction = $this->transactionBuilder->setPayment($payment)
                ->setOrder($order)
                ->setTransactionId($paymentId)
                ->setAdditionalInformation([
                    Transaction::RAW_DETAILS => (array) $additionalInfo,
                ])
                ->setFailSafe(true)
                ->build(Transaction::TYPE_CAPTURE);

            // Add transaction to payment
            $payment->addTransactionCommentsToOrder($transaction, __('Transacción autorizada por %1. Medio de Pago: %2', $formatedPrice, $paymentMethod));
            $payment->setParentTransactionId(null);

            // Save payment, transaction and order
            $payment->save();
            $order->save();
            $transaction->save();

            return $transaction->getTransactionId();
        } catch (Exception $e) {
            $this->log->debug('WebHook Controller > Error Paynment Data', [
                "message" => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function createInvoice($order, $message = '')
    {
        if (!$order->hasInvoices()) {
            $invoice = $order->prepareInvoice();
            $invoice->register();
            $invoice->pay();
            $invoice->addComment(str_replace("<br/>", "", $message), false, true);

            $transaction = $this->transactionFactory->create();
            $transaction->addObject($invoice);
            $transaction->addObject($invoice->getOrder());
            $transaction->save();
            $this->invoiceSender->send($invoice, true, $message);

            return true;
        }

        return false;
    }
}
