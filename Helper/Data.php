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
        // Get checkout with order data
        $checkout = $this->_objectManager->get('Magento\Checkout\Model\Type\Onepage')->getCheckout();

        try {
            return $this->mobbex->executeHook('mobbexProcessPayment', false, $checkout) ?: $this->mobbex->createCheckout($checkout);
        } catch (\Exception $e) {
            Data::log('Mobbex: Error Processing Payment' . $e->getMessage(), 'mobbex_error.log');
        }
    }

    /**
     * Get a Mockup checkout that serves for extract some specific data.
     * 
     * @return array
     */
    public function getCheckoutMockup()
    {
        // Get checkout with quote data
        $checkout = $this->_objectManager->get('Magento\Checkout\Model\Type\Onepage')->getCheckout();

        try {
            return $this->mobbex->executeHook('mobbexProcessPayment', false, $checkout) ?: $this->mobbex->createCheckoutFromQuote($checkout->getQuote());
        } catch (\Exception $e) {
            Data::log('Mobbex: Error Obtaining Mockup Checkout' . $e->getMessage(), 'mobbex_error.log');
        }
    }

    /**
     * Returns a query param with the installments of the product.
     * @param int $total
     * @param array $installments
     * @return string $query
     */
    public function getInstallmentsQuery($total, $installments = [])
    {
        // Build query params and replace special chars
        return preg_replace('/%5B[0-9]+%5D/simU', '%5B%5D', http_build_query(compact('total', 'installments')));
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

        $curl  = curl_init();
        $query = $this->getInstallmentsQuery($total, $installments);

        curl_setopt_array($curl, [
            CURLOPT_URL            => "https://api.mobbex.com/p/sources" . ($query ? "?$query" : ''),
            CURLOPT_HTTPHEADER     => $this->mobbex->getHeaders(),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'GET'
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
                    'label' => $plan['name'] ?: $plan['description'],
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
                    'label'   => $plan['name'] ?: $plan['description'],
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
}
