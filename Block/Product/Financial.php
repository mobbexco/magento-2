<?php
namespace Mobbex\Webpay\Block\Product;

/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

/**
 * Product description block
 *
 * @author
 */


use Magento\Customer\Model\Session;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Mobbex\Webpay\Helper\Data;
use Psr\Log\LoggerInterface;
use Mobbex\Webpay\Helper\Config;
use Magento\Catalog\Model\Product;

/**
 * @api
 * @since 100.0.2
 */
class Financial extends \Magento\Framework\View\Element\Template
{

    /**
     * @var Data
     */
    protected $_helper;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var Product
     */
    protected $_product = null;

    /**
     * Core registry
     *
     * @var \Magento\Framework\Registry
     */
    protected $_coreRegistry = null;

    /**
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param array $data
     * @param Mobbex\Webpay\Helper\Config $config
     * @param Mobbex\Webpay\Helper\Mobex $mobbex
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Framework\Registry $registry,
        \Mobbex\Webpay\Helper\Data $helper,
        \Magento\Framework\Pricing\Helper\Data $priceHelper,
        array $data = [],
        Config $config
    ) {
        $this->_coreRegistry = $registry;
        parent::__construct($context, $data);
        $this->config = $config;
        $this->helper = $helper;
        $this->priceHelper = $priceHelper;
        $sources = $this->getSources();
    }

    /**
     * @return Product
     */
    public function getProduct()
    {
        if (!$this->_product) {
            $this->_product = $this->_coreRegistry->registry('product');
        }
        return $this->_product;
    }

    /**
     * @return String Apikey
     */
    public function getApikey()
    {
        return $this->config->getApiKey();
    }

    /**
     * Return the Cuit/Tax_id using the ApiKey to request via web service
     * @return String Cuit
     */
    public function getCuit()
    {
        $curl = curl_init();
        $cuit = "";

        $headers = $this->getHeaders();

        curl_setopt_array($curl, [
            CURLOPT_URL => "https://api.mobbex.com/p/entity/validate",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => $headers,
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);
        $cuit = "";
        if ($err) {
            //search the cuit in the plugins config if cant get it from api request
            $cuit = $this->config->getCuit();
        } else {
            $res = json_decode($response, true);
            if (isset($res['data']) && isset($res['data']['tax_id'])) {
                $cuit = $res['data']['tax_id'];
            }
        }
        return $cuit;
    }

     /**
     * Return the Sources with the filtered plans
     * @return array
     */
    public function getSources() {

        if(isset($this->sources)) {
            return $this->sources;
        }

        //Get the product data
        $product =  $this->getProduct();
        $productId =  $product->getId();

        //Get the plans for the product
        $inactivePlans = $this->helper->mobbex->getInactivePlans($productId);
        $activePlans = $this->helper->mobbex->getActivePlans($productId);
        
        //Get the sources filtered
        $sources = $this->helper->getSources($product->getPrice(), $inactivePlans, $activePlans);
        
        return $this->sources = $sources;
    }

    /**
     * Return true if the "Activar Info. Financiación" is set to "Si" or false if it's "No"
     * @return array
     */
    public function getFinancialActive()
    {
        return $this->config->getFinancialActive();
    }

    /**
     * Build an array with the headers for an api request
     * @return array
     */
    private function getHeaders()
    {
        return [
            'cache-control: no-cache',
            'content-type: application/json',
            'x-api-key: ' . $this->config->getApiKey(),
            'x-access-token: ' . $this->config->getAccessToken(),
        ];
    }
}
