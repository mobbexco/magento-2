<?php

namespace Mobbex\Webpay\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class ProductSaveObserver implements ObserverInterface
{
    /** @var \Mobbex\Webpay\Model\EventManager */
    public $eventManager;

    /** @var \Mobbex\Webpay\Helper\Config */
    public $config;
    
    /** @var \Mobbex\Webpay\Helper\Logger */
    public $logger;

    /** @var \Mobbex\Webpay\Helper\Sdk */
    public $sdk;

    /** @var \Mobbex\Webpay\Model\CustomFieldFactory */
    public $customFieldFactory;

    /** @var \Magento\Framework\Serialize\Serializer\Serialize */
    public $serializer;

    /** @var array */
    public $params;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Mobbex\Webpay\Helper\Mobbex $helper,
        \Mobbex\Webpay\Helper\Config $config,
        \Mobbex\Webpay\Model\EventManager $eventManager,
        \Mobbex\Webpay\Model\CustomFieldFactory $customFieldFactory,
        \Magento\Framework\Serialize\Serializer\Serialize $serialize,
        \Mobbex\Webpay\Helper\Logger $logger,
        \Mobbex\Webpay\Helper\Sdk $sdk
    )
    {
        $this->helper             = $helper;
        $this->config             = $config;
        $this->logger             = $logger;
        $this->eventManager       = $eventManager;
        $this->customFieldFactory = $customFieldFactory;
        $this->params             = $context->getRequest()->getParams();
        $this->serializer         = $serialize;
        $this->sdk                = $sdk;
        $this->sdk->init();
    }

    /**
     * Save own product options.
     * 
     * @param Observer $observer
     */
    public function execute($observer)
    {
        // Only if options are loaded
        if (empty($this->params['mbbx_options_loaded']))
            return;

        //Get mobbex configs
        $productConfigs = [
            'entity'           => isset($this->params['entity']) ? $this->params['entity'] : '',
            'subscription_uid' => isset($this->params['sub_uid']) ? $this->params['sub_uid'] : '',
            'manual_config'    => isset($this->params['mobbex_manual_config']) ? $this->params['mobbex_manual_config'] : "no",
            'featured_plans'   => isset($this->params['mobbex_featured_plans']) ? $this->params['mobbex_featured_plans'] : "[]",
            'advanced_plans'   => isset($this->params['mobbex_advanced_plans']) ? $this->params['mobbex_advanced_plans'] : "[]",
            'show_featured'    => isset($this->params['mobbex_show_featured_plans']) ? $this->params['mobbex_show_featured_plans'] : "no",
        ];

        $product = $observer->getProduct();
        $id      = $product->getId();
        
        //Save mobbex custom fields
        foreach ($productConfigs as $key => $value) {
            $customField = $this->customFieldFactory->create();
            $customField->saveCustomField($id, 'product', $key, $value);
        }

        $this->saveBestPlan($product);

        $this->eventManager->dispatch('mobbexSaveProductSettings', false, $observer->getProduct(), $this->params);
    }

    /**
     * saveBestPlan saves the required data to show the best plan banner in products catalog page
     * 
     * @param object $product
     */
    private function saveBestPlan($product = null)
    {
        if (!$product)
            return null;

        $id = $product->getId();

        $featuredPlans = $this->config->getAllPlansConfiguratorSettings($product, "manual_config")
            ? json_decode($this->config->getAllPlansConfiguratorSettings($product, "featured_plans"), true)
            : null;

        if (empty($featuredPlans))
            return null;

        $best_plan = $this->getBestPlan($featuredPlans, $product, $id);

        $customField = $this->customFieldFactory->create();
        $customField->saveCustomField($id, 'product', 'bestPlan', $best_plan);
    }

    /**
     * getBestPlan get the best plan configured as featured plan for a product
     * 
     * @param array      $featuredPlans
     * @param int|string $id
     * 
     * @return null|string best plan in featured plans
     */
    private function getBestPlan($featuredPlans, $product, &$id) 
    {
        $sources = [];
        // $product = $observer->getProduct();
        $total   = $product->getPriceInfo()->getPrice('final_price')->getValue();

        $installments = \Mobbex\Repository::getInstallments(
            [$id],
            [],
            $this->config->getProductPlans($product)
        );

        // Get sources from cache or Mobbex API
        try {
            $sources = \Mobbex\Repository::getSources(
                $total,
                $installments
            );
        }  catch (\Exception $e) {
            $this->logger->log(
                'error', 
                'ProductSaveObserver > getSources', 
                $e->getMessage()
            );
            return null;
        }
        
        if (empty($sources))
            return null;

        return $this->helper->filterFeaturedPlans($sources, $featuredPlans);
    }
}