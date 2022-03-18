<?php

namespace Mobbex\Webpay\Block\Payment;

use Magento\Customer\Model\Session;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Mobbex\Webpay\Helper\Data;
use Psr\Log\LoggerInterface;

/**
 * Class Redirect
 * @package Mobbex\Webpay\Block\Payment
 */
class Redirect extends Template
{
    /**
     * @var Session
     */
    public $customerSession;

    /**
     * @var LoggerInterface
     */
    public $logger;

    /**
     * @var ObjectManagerInterface
     */
    protected $_objectManager;

    /**
     * @var Data
     */
    protected $_helper;

    /**
     * Redirect constructor.
     * @param Context $context
     * @param Session $customerSession
     * @param ObjectManagerInterface $_objectManager
     * @param Data $_helper
     * @param LoggerInterface $logger
     * @param array $data
     */
    public function __construct(
        Context $context,
        Session $customerSession,
        ObjectManagerInterface $_objectManager,
        Data $_helper,
        LoggerInterface $logger,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->customerSession = $customerSession;
        $this->_objectManager = $_objectManager;
        $this->_helper = $_helper;
        $this->logger = $logger;
    }

    /**
     * @return array
     */
    public function getCheckoutUrl()
    {
        return ['url' => isset($_GET['checkoutUrl']) ? htmlspecialchars(urldecode($_GET['checkoutUrl'])) : '', 'method' => isset($_GET['paymentMethod']) ? $_GET['paymentMethod'] : ''];
    }
}
