<?php

namespace Mobbex\Webpay\Controller\Payment;

use Exception;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;

/**
 * Class PaymentReturn
 * @package Mobbex\Webpay\Controller\Payment
 */
class PaymentReturn implements \Magento\Framework\App\Action\HttpGetActionInterface
{
    /** @var \Mobbex\Webpay\Helper\Sdk */
    public $sdk;

    /** @var \Mobbex\Webpay\Helper\Config */
    public $config;
    
    /** @var \Mobbex\Webpay\Helper\Mobbex */
    public $helper;

    /** @var \Mobbex\Webpay\Helper\Logger */
    public $logger;

    /** @var \Magento\Quote\Model\QuoteFactory */
    public $quoteFactory;

    /** @var \Magento\Framework\Controller\Result\RedirectFactory */
    public $redirectFactory;

    /** @var \Magento\Framework\App\RequestInterface */
    public $_request;

    /** @var \Magento\Checkout\Model\Cart */
    public $_cart;

    /** @var \Magento\Checkout\Model\Session */
    public $_checkoutSession;

    /** @var \Magento\Sales\Model\Order */
    public $_order;

    /** @var Magento\Sales\Model\Service\InvoiceService */
    public $_invoiceService;

    /** @var Magento\Framework\DB\Transaction */
    protected $_transaction;

    /** @var \Magento\Framework\Message\ManagerInterface */
    protected $_messageManager;

    public function __construct(
        \Mobbex\Webpay\Helper\Sdk $sdk,
        \Mobbex\Webpay\Helper\Config $config,
        \Mobbex\Webpay\Helper\Mobbex $helper,
        \Mobbex\Webpay\Helper\Logger $logger,
        \Magento\Quote\Model\QuoteFactory $quoteFactory,
        \Magento\Framework\Controller\Result\RedirectFactory $redirectFactory,
        \Magento\Framework\App\RequestInterface $request,
        \Magento\Checkout\Model\Cart $cart,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Sales\Model\Order $order,
        \Magento\Sales\Model\Service\InvoiceService $_invoiceService,
        \Magento\Framework\DB\Transaction $_transaction,
        \Magento\Framework\Message\ManagerInterface $messageManager
    ) {
        $this->sdk              = $sdk;
        $this->config           = $config;
        $this->helper           = $helper;
        $this->logger           = $logger;
        $this->quoteFactory     = $quoteFactory;
        $this->redirectFactory  = $redirectFactory;
        $this->_request         = $request;
        $this->_cart            = $cart;
        $this->_checkoutSession = $checkoutSession;
        $this->_order           = $order;
        $this->_invoiceService  = $_invoiceService;
        $this->_transaction     = $_transaction;
        $this->_messageManager  = $messageManager;

        //Init mobbex php plugins sdk
        $this->sdk->init();
    }

    /**
     * @return ResponseInterface|ResultInterface|void
     * @throws Exception
     */
    public function execute()
    {
        try {
            //Debug Params
            $this->logger->log('debug', 'PaymentReturn Controller > Request', ["params" => $this->_request->getParams()]);

            // Get data
            $status  = $this->_request->getParam('status');
            $quoteId = $this->_request->getParam('quote_id');
            $orderId = $this->_request->getParam('order_id');

            if ($quoteId && !$orderId) {
                $quote    = $this->quoteFactory->create()->load($quoteId);
                $orderId = $quote->getReservedOrderId();
            }

            // if data looks fine
            if ($orderId) {
                // Get Order
                $this->_order->loadByIncrementId($orderId);

                $this->logger->log('debug', 'PaymentReturn > execute', $this->_order->debug());
                $this->helper->executeHook('mobbexPaymentReturn', false, $status, $quoteId, $this->_order->getId());

                if ($status > 1 && $status < 400) {
                    return $this->redirectFactory->create()->setPath('checkout/onepage/success');
                } else {
                    //If there are a quote id restore the cart
                    if($quoteId)
                        $this->restoreCart($quoteId);
                        
                    return $this->redirectFactory->create()->setPath('checkout/');
                }


            } else {
                $this->_messageManager->addError(__("Invalid order number"));
                $this->logger->log('error', 'PaymentReturn > execute | Called without order id');
                return $this->redirectFactory->create()->setPath('home');
            }

        } catch (Exception $e) {
            return $this->logger->createJsonResponse('error', 'PaymentReturn Controller > Error: ' . $e->getMessage());
        }
    }

    /**
     * Restore the customer selected items in the cart.
     * @param string $quote_id
     */
    private function restoreCart($quote_id)
    {
        //First cancel the order
        $this->cancelOrder();
        //Get Quote
        $quote = $this->quoteFactory->create()->load($quote_id);
        //Debug data
        $this->logger->log('debug', 'PaymentReturn > restoreCart', $quote->debug());
        //Restore cart
        $quote->setReservedOrderId(null);
        $quote->setIsActive(true);
        $quote->removePayment();
        $quote->save();

        $this->_checkoutSession->replaceQuote($quote);
        $this->_cart->setQuote($quote);
        $this->_checkoutSession->restoreQuote();
    }

    /**
     * Cancel orders
     */
    private function cancelOrder()
    {
        $this->_order->cancel();
        $this->_order->setState(\Magento\Sales\Model\Order::STATE_CANCELED)->setStatus(\Magento\Sales\Model\Order::STATE_CANCELED);
        $this->_order->addStatusToHistory(\Magento\Sales\Model\Order::STATE_CANCELED, 'Orden cancelada', false);
        $this->_order->save();
    }
}
