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
class Pos extends \Magento\Framework\App\Action\Action
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
        if ($this->request->isPost())
            return $this->createIntent();

        if ($this->request->isGet())
            return $this->getIntentStatus();

        if ($this->request->isDelete())
            return $this->deteleIntent();
    }

    public function createIntent()
    {
        $result = $this->jsonFactory->create();

        try {
            $posUid = $this->request->getParam('uid');

            if (empty($posUid))
                return $result->setData([
                    'result' => 'error',
                    'message' => 'POS UID is required'
                ])->setHttpResponseCode(400);

            return $result->setData([
                'result' => 'success',
                'data' => $this->posHelper->createPaymentIntent($posUid)
            ]);
        } catch (\Exception $e) {
            $this->logger->log('error', 'Pos intent error: ', $e);

            return $result->setData([
                'result' => 'error',
                'message' => 'Error creating POS intent: ' . $e->getMessage()
            ])->setHttpResponseCode(500);
        }
    }

    public function deteleIntent()
    {
        $result = $this->jsonFactory->create();

        try {
            $posUid = $this->request->getParam('uid');

            if (empty($posUid))
                return $result->setData([
                    'result' => 'error',
                    'message' => 'POS UID is required'
                ])->setHttpResponseCode(400);

            return $result->setData([
                'result' => 'success',
                'data' => $this->posHelper->deletePaymentIntent($posUid)
            ]);
        } catch (\Exception $e) {
            $this->logger->log('error', 'Pos intent deletion error: ', $e);

            return $result->setData([
                'result' => 'error',
                'message' => 'Error deleting POS intent: ' . $e->getMessage()
            ])->setHttpResponseCode(500);
        }
    }

    public function getIntentStatus()
    {
        $result = $this->jsonFactory->create();

        try {
            return $result->setData([
                'result' => 'success',
                'data' => $this->posHelper->getPaymentIntentStatus()
            ]);
        } catch (\Exception $e) {
            $this->logger->log('error', 'Pos intent obtaining error: ', $e);

            return $result->setData([
                'result' => 'error',
                'message' => 'Error obtaining POS intent: ' . $e->getMessage()
            ])->setHttpResponseCode(500);
        }
    }
}
