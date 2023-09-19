<?php

namespace Mobbex\Webpay\Helper;

/**
 * Class Instantiator
 * @package Mobbex\Webpay\Helper
 */
class Instantiator extends \Magento\Framework\App\Helper\AbstractHelper
{
    /** @var \Magento\Framework\ObjectManagerInterface */
    public $_objectManager;

    public $classes = [
        'sdk'                      => '\Mobbex\Webpay\Helper\Sdk',
        'db'                       => '\Mobbex\Webpay\Helper\Db',
        'repository'               => '\Mobbex\Repository',
        'config'                   => '\Mobbex\Webpay\Helper\Config',
        'helper'                   => '\Mobbex\Webpay\Helper\Mobbex',
        'logger'                   => '\Mobbex\Webpay\Helper\Logger',
        'customFieldFactory'       => '\Mobbex\Webpay\Model\CustomFieldFactory',
        'mobbexTransactionFactory' => '\Mobbex\Webpay\Model\TransactionFactory',
        'quoteFactory'             => '\Magento\Quote\Model\QuoteFactory',
        'redirectFactory'          => '\Magento\Framework\Controller\Result\RedirectFactory',
        'orderUpdate'              => '\Mobbex\Webpay\Model\OrderUpdate',
        '_cart'                    => '\Magento\Checkout\Model\Cart',
        '_checkoutSession'         => '\Magento\Checkout\Model\Session',
        '_order'                   => '\Magento\Sales\Model\Order',
        '_request'                 => '\Magento\Framework\App\RequestInterface',
        '_urlBuilder'              => '\Magento\Framework\UrlInterface'
    ];

    public function __construct(\Magento\Framework\ObjectManagerInterface $_objectManager)
    {
        $this->_objectManager = $_objectManager;
    }

    /**
     * Set properties from instantiator
     * @param Object $object
     * @param array $properties
     */
    public function setProperties($object, $properties)
    {
        foreach ($properties as $property){
            $object->$property = $this->_objectManager->get($this->classes[$property]);
            if($property === 'sdk')
                $object->sdk->init();
        }
    }
}


