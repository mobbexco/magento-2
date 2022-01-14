<?php

namespace Mobbex\Webpay\Helper;

use Magento\Checkout\Model\Cart;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\ObjectManagerInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;
use Zend\Log\Logger;
use Zend\Log\Writer\Stream;

/**
 * Class Data
 * @package Mobbex\Webpay\Helper
 */
class Data extends AbstractHelper
{
    /**
     * @var ScopeConfigInterface
     */
    public $scopeConfig;

    /**
     * @var OrderInterface
     */
    public $order;

    /**
     * @var Order
     */
    public $modelOrder;

    /**
     * @var Cart
     */
    public $cart;

    /**
     * @var Mobbex
     */
    public $mobbex;

    /**
     * @var ObjectManagerInterface
     */
    protected $_objectManager;

    /**
     * @var LoggerInterface
     */
    protected $log;

    /**
     * Data constructor.
     * @param ScopeConfigInterface $scopeConfig
     * @param OrderInterface $order
     * @param Order $modelOrder
     * @param Cart $cart
     * @param ObjectManagerInterface $_objectManager
     * @param LoggerInterface $logger
     * @param Mobbex $mobbex
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        OrderInterface $order,
        Order $modelOrder,
        Cart $cart,
        ObjectManagerInterface $_objectManager,
        LoggerInterface $logger,
        Mobbex $mobbex,
        \Mobbex\Webpay\Model\CustomFieldFactory $customFieldFactory
    ) {
        $this->order = $order;
        $this->modelOrder = $modelOrder;
        $this->cart = $cart;
        $this->scopeConfig = $scopeConfig;
        $this->mobbex = $mobbex;

        $this->_objectManager = $_objectManager;
        $this->log = $logger;
        $this->customFields = $customFieldFactory->create();
    }

    /**
     * @param $mensaje String
     * @param $archivo String
     */
    public static function log($mensaje, $archivo)
    {
        $writer = new Stream(BP . '/var/log/' . $archivo);
        $logger = new Logger();
        $logger->addWriter($writer);
        $logger->info($mensaje);
    }

    /**
     * @return bool
     */
    public function getCheckout()
    {
        // get checkout object
        $checkout = $this->mobbex->createCheckout();
        

        if ($checkout != false) {
            return $checkout;
        } else {
            // Error?
        }
    }

    /**
     * @return bool
     */
    public function getCheckoutWallet($quoteData)
    {
        // get checkout object
        $checkout = $this->mobbex->createCheckoutFromQuote($quoteData);

        if ($checkout != false) {
            return $checkout;
        } else {
            // Error?
        }
    }
    

    /**
     * Get sources with common plans and advanced plans filtered from mobbex.
     * @param integer|null $total
     * @param array|null $inactivePLans
     * @param array|null $activePlans
     */
    public function getSources($total = null, $inactivePlans = null, $activePlans = null)
    {
        $curl = curl_init();

        $data = $total ? '?total=' . $total : null;

        $data .= self::getInstallmentsQuery($inactivePlans, $activePlans);

        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://api.mobbex.com/p/sources' . $data,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => $this->mobbex->getHeaders(),
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            d("cURL Error #:" . $err);
        } else {
            $response = json_decode($response, true);
            $data = (isset($response['data'])?$response['data']:'');

            if (!empty($data)) {
                return $data;
            }
        }

        return [];
    }

    /**
     * Get sources with advanced rule plans from mobbex.
     * @param string $rule
     */
    public function getSourcesAdvanced($rule = 'externalMatch')
    {
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => str_replace('{rule}', $rule, 'https://api.mobbex.com/p/sources/rules/{rule}/installments'),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => $this->mobbex->getHeaders(),
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            d("cURL Error #:" . $err);
        } else {
            $response = json_decode($response, true);
            $data = (isset($response['data'])?$response['data']:'');

            if (!empty($data)) {
                return $data;
            }
        }

        return [];
    }

    /**
     * Returns a query param with the installments of the product.
     * @param array $inactivePlans
     * @param array $activePlans
     */
    public static function getInstallmentsQuery($inactivePlans = null, $activePlans = null ) {
        
        $installments = [];
        
        //get plans
        if($inactivePlans) {
            foreach ($inactivePlans as $plan) {
                $installments[] = "-$plan";
            }
        }

        if($activePlans) {
            foreach ($activePlans as $plan) {
                $installments[] = "+uid:$plan";
            } 
        }

        //Build query param
        $query = http_build_query(['installments' => $installments]);
        $query = preg_replace('/%5B[0-9]+%5D/simU', '%5B%5D', $query);
        
        return $query;
    }   

    /**
     * Retrieve plans filter fields data for product/category settings.
     * 
     * @param int|string $id
     * @param string $catalogType
     * 
     * @return array
     */
    public function getPlansFilterFields($id, $catalogType = 'product')
    {
        $commonFields = $advancedFields = $sourceNames = [];

        // Get saved values from database
        $checkedCommonPlans   = unserialize($this->customFields->getCustomField($id, $catalogType, 'common_plans')) ?: [];
        $checkedAdvancedPlans = unserialize($this->customFields->getCustomField($id, $catalogType, 'advanced_plans')) ?: [];

        // Create common plan fields
        foreach ($this->getSources() as $source) {
            // Only if have installments
            if (empty($source['installments']['list']))
                continue;

            // Create field array data
            foreach ($source['installments']['list'] as $plan) {
                $commonFields[$plan['reference']] = [
                    'id'    => 'common_plan_' . $plan['reference'],
                    'value' => !in_array($plan['reference'], $checkedCommonPlans),
                    'label' => $plan['description'] ?: $plan['name'],
                ];
            }
        }

        // Create plan with advanced rules fields
        foreach ($this->getSourcesAdvanced() as $source) {
            // Only if have installments
            if (empty($source['installments']))
                continue;

            // Save source name
            $sourceNames[$source['source']['reference']] = $source['source']['name'];

            // Create field array data
            foreach ($source['installments'] as $plan) {
                $advancedFields[$source['source']['reference']][] = [
                    'id'      => 'advanced_plan_' . $plan['uid'],
                    'value'   => in_array($plan['uid'], $checkedAdvancedPlans),
                    'label'   => $plan['description'] ?: $plan['name'],
                ];
            }
        }

        return compact('commonFields', 'advancedFields', 'sourceNames');
    }

    /**
     * Return a mockup checkout.
     * 
     * 
     * @return array
     */
    public function getCheckoutMockup()
    {
        $curl = curl_init();

        $data = [
            'reference' => hash('md5', random_int(0, 100000) + random_int(0, 100000)),
            'currency' => 'ARS',
            'description' => 'description',
            'total' => 100.00
        ];

        curl_setopt_array($curl, [
            CURLOPT_URL => "https://api.mobbex.com/p/checkout",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => $this->mobbex->getHeaders(),
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        if ($err) {
            Data::log("Mockup Checkout Error:" . print_r($err, true), "mobbex_error_" . date('m_Y') . ".log");
            return false;
        } else {
            $res = json_decode($response, true);
            Data::log("Mockup Checkout Response:" . print_r($res, true), "mobbex_" . date('m_Y') . ".log"); 
            return $res['data'];
        }
    }

}

