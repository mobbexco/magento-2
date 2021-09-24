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
     * Get sources with common plans from mobbex.
     * @param integer|null $total
     */
    public function getSources($total = null, $inactivePlans = null)
    {
        $curl = curl_init();

        $data = $total ? '?total=' . $total : null;

        if ($data && $inactivePlans) {
            $data .= '&';
            foreach ($inactivePlans as $plan) {
                $data .= '&installments[]=-' . $plan;
            }
        }

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
     * Filter advanced sources 
     *
     * @return array
     */
    public function filterAdvancedSources($sources, $advancedPlans)
    {
        foreach ($sources as $firstKey => $source) {
            foreach ($source['installments'] as $key => $installment) {
                if (!in_array($installment['uid'], $advancedPlans)) {
                    unset($sources[$firstKey]['installments'][$key]);
                }
            }
        }
        return $sources;
    }

    /**
     * Merge common sources with sources obtained by advanced rules.
     * 
     * @param mixed $sources
     * @param mixed $advanced_sources
     * 
     * @return array
     */
    public function mergeSources($sources, $advanced_sources)
    {
        foreach ($advanced_sources as $advanced_source) {
            $key = array_search($advanced_source['sourceReference'], array_column(array_column($sources, 'source'), 'reference'));

            // If source exists in common sources array
            if ($key !== false) {
                // Only add installments
                $sources[$key]['installments']['list'] = array_merge($sources[$key]['installments']['list'], $advanced_source['installments']);
            } else {
                $sources[] = [
                    'source'       => $advanced_source['source'],
                    'installments' => [
                        'list' => $advanced_source['installments']
                    ]
                ];
            }
        }

        return $sources;
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
}

