<?php

namespace Mobbex\Webpay\Controller\Payment;

use Exception;
use Magento\Checkout\Model\Cart;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\DB\Transaction;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Service\InvoiceService;
use Mobbex\Webpay\Helper\Data;
use Mobbex\Webpay\Model\OrderUpdate;
use Psr\Log\LoggerInterface;
use \Magento\Framework\Message\ManagerInterface;

/**
 * Class PaymentReturn
 * @package Mobbex\Webpay\Controller\Payment
 */
class PaymentReturn extends Action
{
    /**
     * @var Context
     */
    public $context;

    /**
     * @var InvoiceService
     */
    protected $_invoiceService;

    /**
     * @var Order
     */
    protected $_order;

    /**
     * @var Transaction
     */
    protected $_transaction;

    /**
     * @var Cart
     */
    protected $cart;

    /**
     * @var Session
     */
    protected $checkoutSession;

    /**
     * @var LoggerInterface
     */
    protected $log;

    /**
     * @var OrderUpdate
     */
    protected $_orderUpdate;


    protected $quoteFactory;

    /**
     * PaymentReturn constructor.
     * @param Context $context
     * @param InvoiceService $_invoiceService
     * @param Order $_order
     * @param Transaction $_transaction
     * @param Cart $cart
     * @param Session $checkoutSession
     * @param LoggerInterface $logger
     * @param OrderUpdate $orderUpdate
     */
    public function __construct(
        Context $context,
        InvoiceService $_invoiceService,
        Order $_order,
        Transaction $_transaction,
        Cart $cart,
        Session $checkoutSession,
        LoggerInterface $logger,
        OrderUpdate $orderUpdate,
        ManagerInterface $messageManager,
        \Magento\Quote\Model\QuoteFactory $quoteFactory
    ) {
        $this->_invoiceService = $_invoiceService;
        $this->_transaction = $_transaction;
        $this->_order = $_order;
        $this->context = $context;
        $this->_orderUpdate = $orderUpdate;
        $this->messageManager = $messageManager;

        $this->cart = $cart;
        $this->checkoutSession = $checkoutSession;

        $this->log = $logger;
        $this->quoteFactory = $quoteFactory;

        parent::__construct($context);
    }

    /**
     * @return ResponseInterface|ResultInterface|void
     * @throws Exception
     */
    public function execute()
    {
        try {
            // Get data
            $quoteId = $this->getRequest()->getParam('quote_id');
            $orderId = $this->getRequest()->getParam('order_id');
            $status = $this->getRequest()->getParam('status');

            $this->log->debug('Return Controller > Data', [
                "id" => $orderId,
                "status" => $status,
            ]);

            
            $quote = $this->quoteFactory->create()->load($quoteId);

            error_log(" [quote!". $quoteId ."] { ".$quote->getId()." } ", 3, "/var/www/html/magento2.2/vendor/mobbexco/magento-2/walletReturn.log");

            // if data looks fine
            if (isset($orderId)) {
                // Get Order
                $order = $this->_order->loadByIncrementId($orderId);

                $this->log->debug('Return Controller > Order', $this->_order->debug());

                if ($status > 1 && $status < 400) {
                    $this->_redirect('checkout/onepage/success');
                } else {
                    $this->restoreCart($order);
                    $this->_redirect('checkout',['_fragment' => 'payment']);
                }

                // Cancel order to return stock when payment is not attempted
                if (empty($status)) {
                    $order->cancel();
                    $order->save();
                }
            } else {
                $this->messageManager->addError(__("Invalid order number"));
                $this->_redirect('home');
                Data::log('Payment Return called without order id', "mobbex_error_" . date('m_Y') . ".log");
            }
        } catch (Exception $e) {
            Data::log($e->getMessage(), "mobbex_error_" . date('m_Y') . ".log");
        }
    }

    /**
     * @param $order
     */
    private function restoreCart($order)
    {
        //Get Object Manager Instance
        $objectManager = ObjectManager::getInstance();

        $quote = $objectManager->create('\Magento\Quote\Model\QuoteFactory')->create()->load($order->getQuoteId());

        $this->log->debug('Return Controller > Quote', $quote->debug());

        $quote->setReservedOrderId(null);
        $quote->setIsActive(true);
        $quote->removePayment();
        $quote->save();

        $this->checkoutSession->replaceQuote($quote);
        $this->cart->setQuote($quote);

        $this->checkoutSession->restoreQuote();
    }
}
