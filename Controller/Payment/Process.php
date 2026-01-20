<?php

namespace Mobbex\Webpay\Controller\Payment;

/**
 * Class Process.
 * 
 * Called on checkout transparent operation process try.
 *
 * @package Mobbex\Webpay\Controller\Payment
 */
class Process extends \Magento\Framework\App\Action\Action
{
    /** @var \Mobbex\Webpay\Helper\Sdk */
    public $sdk;

    /** @var \Mobbex\Webpay\Helper\Logger */
    public $logger;

    /** @var \Mobbex\Webpay\Helper\Mobbex */
    public $helper;

    public function __construct(
        \Mobbex\Webpay\Helper\Sdk $sdk,
        \Mobbex\Webpay\Helper\Logger $logger,
        \Mobbex\Webpay\Helper\Mobbex $helper,
        \Magento\Framework\App\Action\Context $context
    ) {
        parent::__construct($context);

        $this->sdk = $sdk;
        $this->logger = $logger;
        $this->helper = $helper;
        

        $this->sdk->init();
    }

    public function execute()
    {
        try {
            $postData = json_decode(file_get_contents('php://input'), true);

            // Validate body
            $this->validateBody($postData);

            // Create checkout from order
            $checkout = $this->helper->getCheckout();

            if (empty($checkout) || empty($checkout['intent']['token']))
                throw new \Exception('Error on checkout creation.');

            // Tokenize
            $card = $this->cardToken(
                $checkout['intent']['token'],
                $postData['number'],
                $postData['expiry'],
                $postData['cvv'],
                $postData['name'],
                $postData['identification']
            );

            if (empty($card['token']))
                throw new \Mobbex\Exception('Error on token creation');

            // Process operation
            $opRes = $this->processOp(
                $checkout['intent']['token'],
                $card['token'],
                $postData['installments']
            );

            if (empty($opRes) || empty($opRes['status']['code']))
                throw new \Mobbex\Exception('Error on operation process. Empty response', 0, $opRes);

            if (!in_array($opRes['status']['code'], ['3', '100', '200']))
                throw new \Mobbex\Exception('Operation process with error code', 0, $opRes);

            die(json_encode(['result' => 'success', 'code' => $opRes['status']['code']]));
        } catch (\Exception $e) {
            $data = $e instanceof \Mobbex\Exception ? $e->data : [];

            if (isset($checkout['intent']['token']))
                $data['it'] = $checkout['intent']['token'];

            return $this->logger->createJsonResponse(
                'error',
                'Transparent process error: ' . $e->getMessage(),
                $data
            );
        }
    }

    /**
     * Validate request body
     * 
     * @param array $body Request body
     * 
     * @throws \Exception
     */
    private function validateBody($body)
    {
        if (empty($body) || !is_array($body))
            throw new \Exception('Invalid request body.');

        $number         = isset($body['number']) ? $body['number'] : null;
        $expiry         = isset($body['expiry']) ? $body['expiry'] : null;
        $cvv            = isset($body['cvv']) ? $body['cvv'] : null;
        $name           = isset($body['name']) ? $body['name'] : null;
        $identification = isset($body['identification']) ? $body['identification'] : null;
        $installments   = isset($body['installments']) ? $body['installments'] : null;

        // Falsy check
        if (!$number || !$expiry || !$cvv || !$name || !$identification || !$installments)
            throw new \Exception('Missing required fields.');

        // Type check
        if (!is_string($number) || !is_string($expiry) || !is_string($cvv) || !is_string($name) || !is_string($identification) || !is_string($installments))
            throw new \Exception('All fields must be strings.');

        // Card number
        if (strlen($number) < 15 || strlen($number) > 19)
            throw new \Exception('Number must be at least 15 and not more than 19 characters long.');

        if (!preg_match('/^[0-9]+$/', $number))
            throw new \Exception('Number must contain only numbers.');

        // Card expiry
        if (strlen($expiry) < 4 || strlen($expiry) > 5)
            throw new \Exception('Expiry must be at least 4 and not more than 5 characters long.');

        if (!preg_match('/^(0[1-9]|1[0-2])\/?([0-9]{2})$/', $expiry))
            throw new \Exception('Expiry must be in MM/YY format.');

        // Card CVV
        if (strlen($cvv) < 3 || strlen($cvv) > 4)
            throw new \Exception('CVV must be at least 3 and not more than 4 characters long.');

        if (!preg_match('/^[0-9]+$/', $cvv))
            throw new \Exception('CVV must contain only numbers.');

        // Holder name
        if (strlen($name) < 3 || strlen($name) > 50)
            throw new \Exception('Name must be at least 3 and not more than 50 characters long.');

        if (strlen($identification) < 5 || strlen($identification) > 9)
            throw new \Exception('Identification must be at least 5 and not more than 9 characters long.');

        if (!preg_match('/^[0-9]+$/', $identification))
            throw new \Exception('Identification must contain only numbers.');
    }

    /**
     * Tokenize card number with Mobbex API.
     * 
     * @param string $it Checkout token
     * @param string $number Card number
     * @param string $expiry Card expiry in MM/YY format
     * @param string $cvv Card CVV
     * @param string $name Card holder name
     * @param string $identification Card holder identification
     * 
     * @return array Token response data.
     * 
     * @throws \Exception
     */
    private function cardToken($it, $number, $expiry, $cvv, $name, $identification)
    {
        $response = \Mobbex\Api::request([
            'method' => 'POST',
            'uri'    => "sources/token/$it",
            'raw'    => true,
            'body'   => [
                'source' => [
                    'card' => [
                        'number' => $number,
                        'identification' => $identification,
                        'cvv' => $cvv,
                        'name' => $name,
                        'month' => explode('/', $expiry)[0],
                        'year' => explode('/', $expiry)[1],
                    ],
                ],
            ]
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

    /**
     * Process payment operation.
     * 
     * @param string $it Checkout token
     * @param string $cardToken Card token
     * @param string $installment Installment number (reference)
     * 
     * @return array Process response data.
     * 
     * @throws \Exception
     */
    private function processOp($it, $cardToken, $installment)
    {
        $response = \Mobbex\Api::request([
            'method' => 'POST',
            'uri'    => "operations/$it",
            'raw'    => true,
            'body'   => [
                'source' => $cardToken,
                'installment' => $installment,
            ]
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
