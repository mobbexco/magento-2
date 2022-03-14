<?php

namespace Mobbex\Webpay\Controller\Payment;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Mobbex\Webpay\Helper\Data;

/**
 * Class EmbedPayment
 * @package Mobbex\Webpay\Controller\Payment
 */

class EmbedPayment extends Action
{

    protected $resultJsonFactory;

    protected $_helper;

    public function __construct(
        Context $context,
        Data $_helper,
        JsonFactory $resultJsonFactory
    ) {
        $this->_helper = $_helper;
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
    }

    public function execute()
    {
        $resultJson = $this->resultJsonFactory->create();

        $checkout = $this->_helper->getCheckout();

        $resultJson->setData($checkout);
    
        return $resultJson;
    }
}
