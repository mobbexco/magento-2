<?php

namespace Mobbex\Webpay\Controller\Payment;

/**
 * Class Pos.
 * 
 * Called when the admin user selects a POS on checkout and presses "Confirm".
 * Creates a payment intent for the selected POS terminal.
 *
 * @package Mobbex\Webpay\Controller\Payment
 */
class Pos implements \Magento\Framework\App\Action\HttpGetActionInterface
{
    /** @var \Mobbex\Webpay\Helper\Sdk */
    private $sdk;

    /** @var \Mobbex\Webpay\Helper\Pos */
    private $posHelper;

    /** @var \Mobbex\Webpay\Helper\Logger */
    private $logger;

    /** @var \Magento\Framework\App\RequestInterface */
    private $request;

    /** @var \Magento\Framework\Controller\Result\JsonFactory */
    private $jsonFactory;

    public function __construct(
        \Mobbex\Webpay\Helper\Sdk $sdk,
        \Mobbex\Webpay\Helper\Pos $helper,
        \Mobbex\Webpay\Helper\Logger $logger,
        \Magento\Framework\App\RequestInterface $request,
        \Magento\Framework\Controller\Result\JsonFactory $jsonFactory
    ) {
        $this->sdk = $sdk;
        $this->posHelper = $helper;
        $this->logger = $logger;
        $this->request = $request;
        $this->jsonFactory = $jsonFactory;

        $this->sdk->init();
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();

        try {
            $posId = $this->request->getParam('pos_id');

            if (empty($posId))
                return $result->setData([
                    'result' => 'error',
                    'message' => 'POS ID is required'
                ])->setHttpResponseCode(400);

            return $result->setData([
                'result' => 'success',
                'data' => $this->posHelper->createPaymentIntent($posId)
            ]);
        } catch (\Exception $e) {
            $this->logger->log('error', 'Pos process error: ', $e);

            return $result->setData([
                'result' => 'error',
                'message' => 'Error processing POS payment: ' . $e->getMessage()
            ])->setHttpResponseCode(500);
        }
    }
}
