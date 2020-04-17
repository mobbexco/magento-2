<?php

namespace Mobbex\Webpay\Controller\Payment;

use \Magento\Framework\App\ObjectManager;
use Magento\Sales\Model\Order;

class PaymentReturn extends \Magento\Framework\App\Action\Action
{
    public $context;
    protected $_invoiceService;
    protected $_order;
    protected $_transaction;

    protected $cart;
    protected $checkoutSession;

    protected $log;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Sales\Model\Service\InvoiceService $_invoiceService,
        \Magento\Sales\Model\Order $_order,
        \Magento\Framework\DB\Transaction $_transaction,
        \Magento\Checkout\Model\Cart $cart,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->_invoiceService = $_invoiceService;
        $this->_transaction = $_transaction;
        $this->_order = $_order;
        $this->context = $context;

        $this->cart = $cart;
        $this->checkoutSession = $checkoutSession;

        $this->log = $logger;

        parent::__construct($context);
    }

    public function execute()
    {

        try {
            // get post data
            $orderId = $this->getRequest()->getParam('order_id');
            $status = $this->getRequest()->getParam('status');
            $type = $this->getRequest()->getParam('type');

            $this->log->debug('Return Controller > Data', [
                "id" => $orderId,
                "status" => $status,
                "type" => $type,
            ]);

            // if data looks fine
            if (isset($orderId)) {
                // set order status
                $this->_order->loadByIncrementId($orderId);

                $this->log->debug('Return Controller > Order', $this->_order->debug());

                if ($status == "2" || $status == "200") {
                    $this->_redirect('checkout/onepage/success');
                } else {
                    $this->_order->setState(Order::STATE_PENDING_PAYMENT)->setStatus(Order::STATE_PENDING_PAYMENT)->save();
                    $this->_order->addStatusToHistory($this->_order->getStatus(), __("Customer was redirected back. Cancelled payment."));
                    $this->_order->save();

                    $this->restoreCart($this->_order);

                    $this->_redirect('checkout/cart');
                }
            } else {
                $this->_redirect('/');
            }
        } catch (Exception $e) {
            echo $e;
        }
    }

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

        // Replace the quote to the checkout session
        $this->checkoutSession->replaceQuote($quote);

        //OR add quote to cart
        $this->cart->setQuote($quote);

        //if your last order is still in the session (getLastRealOrder() returns order data) you can achieve what you need with this one line without loading the order:
        $this->checkoutSession->restoreQuote();
    }
}
