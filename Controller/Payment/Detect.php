<?php

namespace Mobbex\Webpay\Controller\Payment;

/**
 * Class Detect.
 * 
 * Called on checkout BIN change to detect payment method and return installments.
 *
 * @package Mobbex\Webpay\Controller\Payment
 */
class Detect extends \Magento\Framework\App\Action\Action
{
    /** @var \Mobbex\Webpay\Helper\Sdk */
    public $sdk;

    /** @var \Mobbex\Webpay\Helper\Logger */
    public $logger;

    /** @var \Mobbex\Webpay\Helper\Config */
    public $config;

    public function __construct(
        \Mobbex\Webpay\Helper\Sdk $sdk,
        \Mobbex\Webpay\Helper\Logger $logger,
        \Mobbex\Webpay\Helper\Config $config,
        \Magento\Framework\App\Action\Context $context
    ) {
        parent::__construct($context);

        $this->sdk = $sdk;
        $this->logger = $logger;
        $this->config = $config;

        $this->sdk->init();
    }

    public function execute()
    {
        try {
            $postData = json_decode(file_get_contents('php://input'), true);

            $bin = isset($postData['bin']) ? $postData['bin'] : null;
            $token = isset($postData['token']) ? $postData['token'] : null;

            if (!$bin || !$token)
                throw new \Exception('Missing bin or token.');

            if (!is_string($bin) || !is_string($token))
                throw new \Exception('Bin and token must be strings.');

            if (strlen($bin) < 4 || strlen($bin) > 8)
                throw new \Exception('Bin must be at least 4 and not more than 8 characters long.');

            if (!preg_match('/^[0-9]+$/', $bin))
                throw new \Exception('Bin must contain only numbers.');

            if (strlen($token) < 10 || strlen($token) > 50)
                throw new \Exception('Invalid token.');

            $card = $this->detectCard($bin, $token);

            die(json_encode($card));
        } catch (\Exception $e) {
            return $this->logger->createJsonResponse('error', 'Transparent detection error: ' . $e->getMessage());
        }
    }

    /**
     * Get installments and card brand from Mobbex API
     * 
     * @param string $bin Card BIN
     * @param string $token Checkout token
     * 
     * @return array Installments data
     * 
     * @throws \Exception
     */
    private function detectCard($bin, $token)
    {
        $response = \Mobbex\Api::request([
            'method' => 'POST',
            'uri'    => "sources/detect/$token",
            'raw'    => true,
            'body'   => [
                'type' => 'card',
                'data' => ['bin' => $bin],
                'options' => [
                    'installments' => true,
                    'multivendor' => $this->config->get('multivendor') ?: null,
                    'filter' => null,
                    'brand' => true,
                    'brands' => true,
                ],
            ],
        ]);

        if (empty($response['data']))
            throw new \Mobbex\Exception(sprintf(
                'Mobbex request error #%s: %s %s',
                isset($response['code']) ? $response['code'] : 'NOCODE',
                isset($response['error']) ? $response['error'] : 'NOERROR',
                isset($response['status_message']) ? $response['status_message'] : 'NOMESSAGE'
            ));

        return $response['data'];
    }
}
