<?php

namespace Mobbex\Webpay\Controller\Payment;

/**
 * Class MobbexPos
 * @package Mobbex\Webpay\Controller\Payment
 */

class MobbexPos implements \Magento\Framework\App\Action\HttpGetActionInterface
{
    /** @var \Mobbex\Webpay\Helper\Sdk */
    public $sdk;

    /** @var \Mobbex\Webpay\Helper\Pos */
    public $helper;

    /** @var \Mobbex\Webpay\Helper\Logger */
    public $logger;

    public function __construct(
        \Mobbex\Webpay\Helper\Sdk $sdk,
        \Mobbex\Webpay\Helper\Pos $helper,
        \Mobbex\Webpay\Helper\Logger $logger,
        \Magento\Framework\App\RequestInterface $request
    ) {
        $this->sdk      = $sdk;
        $this->helper   = $helper;
        $this->logger   = $logger;
        $this->_request = $request;

        //Init mobbex php plugins sdk
        $this->sdk->init();
    }

    public function execute()
    {
        try {
            $posId  = $this->_request->getParam('pos_id');

            $intent = $this->helper->createPaymentIntent($posId);

            return $this->logger->createJsonResponse('debug', 'POS connection obtained OK:', $intent);
        } catch (\Exception $e) {
            return $this->logger->createJsonResponse('error', $e->getMessage(), isset($e->data) ? $e->data : []);
        }
    }
}