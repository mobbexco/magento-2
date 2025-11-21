<?php

namespace Mobbex\Webpay\Controller\Payment;

/**
 * Class Checkout
 * @package Mobbex\Webpay\Controller\Payment
 */

class Checkout implements \Magento\Framework\App\Action\HttpGetActionInterface
{
    /** @var \Mobbex\Webpay\Helper\Sdk */
    public $sdk;

    /** @var \Mobbex\Webpay\Helper\Checkout */
    public $helper;

    /** @var \Mobbex\Webpay\Helper\Logger */
    public $logger;

    public function __construct(
        \Mobbex\Webpay\Helper\Sdk $sdk,
        \Mobbex\Webpay\Helper\Checkout $helper,
        \Mobbex\Webpay\Helper\Logger $logger
    )
    {
        $this->sdk    = $sdk;
        $this->helper = $helper;
        $this->logger = $logger;

        //Init mobbex php plugins sdk
        $this->sdk->init();
    }

    public function execute()
    {
        try {
            $checkoutData = $this->helper->createPaymentIntent();
            return $this->logger->createJsonResponse('debug', 'Checkout created OK:', $checkoutData);
        } catch (\Exception $e) {
            return $this->logger->createJsonResponse('error', $e->getMessage(), isset($e->data) ? $e->data : []);
        }
    }
}
