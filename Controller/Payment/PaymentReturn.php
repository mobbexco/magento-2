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

    /**
     * Constructor.
     * 
     * @param \Mobbex\Webpay\Helper\Sdk $sdk
     * @param \Mobbex\Webpay\Helper\Config $config
     * @param \Mobbex\Webpay\Helper\Mobbex $helper
     * @param \Mobbex\Webpay\Helper\Logger $logger
     * @param \Magento\Quote\Model\QuoteFactory $quoteFactory
     * @param \Magento\Framework\Controller\Result\RedirectFactory$redirectFactory,
     * @param \Magento\Framework\App\RequestInterface $request
     * @param \Magento\Checkout\Model\Cart $cart
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Sales\Model\Order $order
     * @param \Magento\Sales\Model\Service\InvoiceService$_invoiceService,
     * @param \Magento\Framework\DB\Transaction $_transaction
     * @param \Magento\Framework\Message\ManagerInterface $messageManager
     * 
     */
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
            extract($this->_request->getParams());

            if (empty($order_id) && !empty($quote_id)) {
                $quote    = $this->quoteFactory->create()->load($quote_id);
                $order_id = $quote->getReservedOrderId();
            }

            // if data looks fine
            if (isset($order_id)) {
                // Get Order
                $this->_order->loadByIncrementId($order_id);

                $this->logger->log('debug', 'PaymentReturn > execute', $this->_order->debug());

                if ($status > 1 && $status < 400) {
                    return $this->redirectFactory->create()->setPath('checkout/onepage/success');
                } else {
                    //If there are a quote id restore the cart
                    if(isset($quote_id))
                        $this->restoreCart($quote_id);
                        
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
