<?php

namespace Mobbex\Webpay\Controller\Payment;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
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
        Data $_helper
    ) {
        $this->_helper = $_helper;
        parent::__construct($context);
    }

    public function execute()
    {
        
        $checkout = $this->_helper->getCheckout();
        $vac = [ 
            'returnUrl' => $checkout['return_url'], 
            'checkoutId' => $checkout['id']
        ];

        echo json_encode($vac);
    }
}
