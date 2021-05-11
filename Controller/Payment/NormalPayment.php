<?php

namespace Mobbex\Webpay\Controller\Payment;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Mobbex\Webpay\Helper\Data;

/**
 * Class NormalPayment
 * @package Mobbex\Webpay\Controller\Payment
 */

class NormalPayment extends Action
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

        $dni_key = ($this->getRequest()->getParam('dni_key'));
        $checkout = $this->_helper->getCheckout($dni_key);
        $vac = [ 
            'paymentUrl' => $checkout['url'],
        ];

        $resultJson->setData($vac);

        return $resultJson;
    }
}
