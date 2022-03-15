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
        \Mobbex\Webpay\Model\CustomFieldFactory $customFieldFactory,
        \Magento\Framework\Event\ConfigInterface $eventConfig,
        \Magento\Framework\Event\ObserverFactory $observerFactory
    ) {
        $this->order = $order;
        $this->modelOrder = $modelOrder;
        $this->cart = $cart;
        $this->scopeConfig = $scopeConfig;
        $this->mobbex = $mobbex;

        $this->_objectManager = $_objectManager;
        $this->log = $logger;
        $this->customFields = $customFieldFactory->create();
        $this->eventConfig     = $eventConfig;
        $this->observerFactory = $observerFactory;
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
     * Get a Mockup checkout that serves for extract some specific data.
     * @return bool
     */
    public function getCheckoutMockup($quoteData)
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
     * Get sources with common and advanced plans from mobbex.
     * 
     * @param int|float|null $total
     * @param array $installments
     * 
     * @return array
     */
    public function getSources($total = null, $installments = [])
    {
        $entityData = $this->getEntityData();

        if (!$entityData)
            return [];

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL            => "https://api.mobbex.com/p/sources/list/$entityData[countryReference]/$entityData[tax_id]" . ($total ? "?total=$total" : ''),
            CURLOPT_HTTPHEADER     => $this->mobbex->getHeaders(),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => json_encode(compact('installments')),
        ]);

        $response = curl_exec($curl);
        $error    = curl_error($curl);

        curl_close($curl);

        if ($error)
            self::log('Sources Obtaining cURL Error' . $error, "mobbex_error_" . date('m_Y') . ".log");

        $result = json_decode($response, true);

        if (empty($result['result']))
            self::log('Sources Obtaining Error', "mobbex_error_" . date('m_Y') . ".log");

        return isset($result['data']) ? $result['data'] : [];
    }

    /**
     * Get entity data from Mobbex API or db if possible.
     * 
     * @return string[] 
     */
    public function getEntityData()
    {
        // First, try to get from db
        $entityData = $this->mobbex->config->getEntityData();

        if ($entityData)
            return json_decode($entityData, true);

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL            => "https://api.mobbex.com/p/entity/validate",
            CURLOPT_HTTPHEADER     => $this->mobbex->getHeaders(),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => "",
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'GET',
        ]);

        $response = curl_exec($curl);
        $error    = curl_error($curl);

        curl_close($curl);

        if ($error)
            return self::log('Entity Data Obtaining cURL Error' . $error, "mobbex_error_" . date('m_Y') . ".log");

        $res = json_decode($response, true);

        if (empty($res['data']))
            return self::log('Entity Data Obtaining Error', "mobbex_error_" . date('m_Y') . ".log");

        // Save data
        $this->mobbex->config->save($this->mobbex->config::PATH_ENTITY_DATA, json_encode($res['data']));

        return $res['data'];
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
     * Retrieve product subscription data.
     * 
     * @param int|string $id
     * 
     * @return array
     */
    public function getProductEntity($id, $catalogType = 'product')
    {
        return $this->customFields->getCustomField($id, $catalogType, 'entity') ?: '';
    }

    /**
     * Retrieve product subscription data.
     * 
     * @param int|string $id
     * 
     * @return array
     */
    public function getProductSubscription($id)
    {
        return $this->mobbex->getProductSubscription($id);
    }

    /**
     * Execute a hook and retrieve the response.
     * 
     * @param string $name The hook name (in camel case).
     * @param bool $filter Filter first arg in each execution.
     * @param mixed ...$args Arguments to pass.
     * 
     * @return mixed Last execution response or value filtered. Null on exceptions.
     */
    public function executeHook($name, $filter = false, ...$args)
    {
        try {
            // Use snake case to search event
            $eventName = ltrim(strtolower(preg_replace('/[A-Z]([A-Z](?![a-z]))*/', '_$0', $name)), '_');

            // Get registered observers and first arg to return as default
            $observers = $this->eventConfig->getObservers($eventName) ?: [];
            $value     = $filter ? reset($args) : false;

            foreach ($observers as $observerData) {
                // Instance observer
                $instanceMethod = !empty($observerData['shared']) ? 'get' : 'create';
                $observer       = $this->observerFactory->$instanceMethod($observerData['instance']);

                // Get method to execute
                $method = [$observer, $name];

                // Only execute if is callable
                if (!empty($observerData['disabled']) || !is_callable($method))
                    continue;

                $value = call_user_func_array($method, $args);

                if ($filter)
                    $args[0] = $value;
            }

            return $value;
        } catch (\Exception $e) {
            self::log('Mobbex Hook Error: ' . $e->getMessage(), 'mobbex_error.log');
        }
    }
}