<?php

namespace Mobbex\Webpay\Helper;

class Data extends \Magento\Framework\App\Helper\AbstractHelper
{
    public $scopeConfig;
    public $order;
    public $modelOrder;
    public $cart;
    public $mobbex;

    protected $_objectManager;
    protected $log;

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Sales\Api\Data\OrderInterface $order,
        \Magento\Sales\Model\Order $modelOrder,
        \Magento\Checkout\Model\Cart $cart,
        \Magento\Framework\ObjectManagerInterface $_objectManager,
        \Psr\Log\LoggerInterface $logger,
        \Mobbex\Webpay\Helper\Mobbex $mobbex
    ) {
        $this->order = $order;
        $this->modelOrder = $modelOrder;
        $this->cart = $cart;
        $this->scopeConfig = $scopeConfig;
        $this->mobbex = $mobbex;

        $this->_objectManager = $_objectManager;
        $this->log = $logger;
    }

    public function getCheckout()
    {
        // get checkout object
        $checkout = $this->mobbex->createCheckout();

        $this->log->debug("Checkout => " . $checkout);

        if($checkout != false) {
            return $checkout;
        }  else {
            // Error?
        }
    }
}
