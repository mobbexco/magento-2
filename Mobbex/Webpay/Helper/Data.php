<?php

namespace Mobbex\Webpay\Helper;

use Magento\Checkout\Model\Cart;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\ObjectManagerInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;
use Zend\Log\Logger;
use Zend\Log\Writer\Stream;

/**
 * Class Data
 * @package Mobbex\Webpay\Helper
 */
class Data extends AbstractHelper
{
    /**
     * @var ScopeConfigInterface
     */
    public $scopeConfig;

    /**
     * @var OrderInterface
     */
    public $order;

    /**
     * @var Order
     */
    public $modelOrder;

    /**
     * @var Cart
     */
    public $cart;

    /**
     * @var Mobbex
     */
    public $mobbex;

    /**
     * @var ObjectManagerInterface
     */
    protected $_objectManager;

    /**
     * @var LoggerInterface
     */
    protected $log;

    /**
     * Data constructor.
     * @param ScopeConfigInterface $scopeConfig
     * @param OrderInterface $order
     * @param Order $modelOrder
     * @param Cart $cart
     * @param ObjectManagerInterface $_objectManager
     * @param LoggerInterface $logger
     * @param Mobbex $mobbex
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        OrderInterface $order,
        Order $modelOrder,
        Cart $cart,
        ObjectManagerInterface $_objectManager,
        LoggerInterface $logger,
        Mobbex $mobbex
    ) {
        $this->order = $order;
        $this->modelOrder = $modelOrder;
        $this->cart = $cart;
        $this->scopeConfig = $scopeConfig;
        $this->mobbex = $mobbex;

        $this->_objectManager = $_objectManager;
        $this->log = $logger;
    }

    /**
     * @param $mensaje String
     * @param $archivo String
     */
    public static function log($mensaje, $archivo)
    {
        $writer = new Stream(BP . '/var/log/' . $archivo);
        $logger = new Logger();
        $logger->addWriter($writer);
        $logger->info($mensaje);
    }

    /**
     * @return bool
     */
    public function getCheckout()
    {
        // get checkout object
        $checkout = $this->mobbex->createCheckout();

        $this->log->debug("Checkout => " . $checkout);

        if ($checkout != false) {
            return $checkout;
        } else {
            // Error?
        }
    }
}
