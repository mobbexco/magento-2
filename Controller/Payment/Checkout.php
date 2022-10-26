<?php

namespace Mobbex\Webpay\Controller\Payment;

/**
 * Class Checkout
 * @package Mobbex\Webpay\Controller\Payment
 */

class Checkout implements \Magento\Framework\App\Action\HttpGetActionInterface
{
    /** @var \Mobbex\Webpay\Helper\Instantiator */
    public $instantiator;

    public function __construct(\Mobbex\Webpay\Helper\Instantiator $instantiator)
    {
        $instantiator->setProperties($this, ['sdk', 'helper', 'logger']);
    }

    public function execute()
    {
        try {
            $checkoutData = $this->helper->getCheckout();
            return $this->logger->createJsonResponse('debug', 'Checkout created OK:', $checkoutData);
        } catch (\Exception $e) {
            return $this->logger->createJsonResponse('err', $e->getMessage(), isset($e->data) ? $e->data : []);
        }
    }
}
