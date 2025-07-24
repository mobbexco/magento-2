<?php

namespace Mobbex\Webpay\Controller\Payment;

/**
 * Class Checkout
 * @package Mobbex\Webpay\Controller\Frontend\FinanceWidget;
 */

class Sources implements \Magento\Framework\App\Action\HttpGetActionInterface
{
    /** @var \Mobbex\Webpay\Helper\Sdk */
    public $sdk;

    /** @var \Mobbex\Webpay\Helper\Logger */
    public $logger;

    /** @var \Mobbex\Webpay\Helper\Config */
    public $config;

    /** @var \Magento\Framework\App\RequestInterface $request, */
    public $_request;

    /** @var \Magento\Catalog\Model\ProductFactory */
    public $_productFactory;

    public function __construct(
        \Mobbex\Webpay\Helper\Sdk $sdk,
        \Mobbex\Webpay\Helper\Logger $logger,
        \Mobbex\Webpay\Helper\Config $config,
        \Magento\Framework\App\RequestInterface $request,
        \Magento\Catalog\Model\ProductFactory $productFactory
        
    )
    {
        $this->sdk             = $sdk;
        $this->logger          = $logger;
        $this->config          = $config;
        $this->_request        = $request;
        $this->_productFactory = $productFactory;

        //Init mobbex php plugins sdk
        $this->sdk->init();
    }

    /**
     * Execute method to handle the request and return sources from Mobbex API
     *
     * @return ResponseInterface|ResultInterface
     */
    public function execute()
    {
        // Gets query params
        $total       = $this->_request->getParam('mbbxTotal');
        $product_ids = $this->_request->getParam('mbbxProductIds');

        // Filters out non-numeric values
        $product_ids = array_filter($product_ids, function ($id) {
            return is_numeric($id);
        });

        $products = [];

        // Instance each product
        foreach ($product_ids as $product_id) {
            $product = $this->_productFactory->create()->load($product_id);

            if (method_exists($product, 'getId') && $product->getId())
                $products[] = $product;
        }

        // Extracts products plans
        extract($this->config->getAllProductsPlans($products));

        $installments = \Mobbex\Repository::getInstallments(
                $products,
                $common_plans,
                $advanced_plans
            );

        try {
            $sources = \Mobbex\Repository::getSources(
                $total,
                $installments
            );
    
            die (json_encode(
                [
                    'success' => true,
                    'sources' => $sources,
                ]
            ));

        } catch (\Exception $e) {
            $this->logger->log(
                'error', 'Error getting sources', 
                [
                    'message' => $e->getMessage(),
                    'code'    => $e->getCode(),
                ]
            );

            die (json_encode(
                [
                    'success' => false,
                    'message' => $e->getMessage(),
                ]
            ));
        }
    }
}
