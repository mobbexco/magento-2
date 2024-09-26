<?php

namespace Mobbex\Webpay\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class ProductSaveObserver implements ObserverInterface
{
    /** @var \Mobbex\Webpay\Helper\Mobbex */
    public $helper;

    /** @var \Mobbex\Webpay\Model\CustomFieldFactory */
    public $customFieldFactory;

    /** @var \Magento\Framework\Serialize\Serializer\Serialize */
    public $serializer;

    /** @var array */
    public $params;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Mobbex\Webpay\Helper\Mobbex $helper,
        \Mobbex\Webpay\Model\CustomFieldFactory $customFieldFactory,
        \Magento\Framework\Serialize\Serializer\Serialize $serialize
    )
    {
        $this->helper             = $helper;
        $this->customFieldFactory = $customFieldFactory;
        $this->params             = $context->getRequest()->getParams();
        $this->serializer         = $serialize;
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

        // Get plans selected
        $commonPlans = $advancedPlans = [];
        foreach ($this->params as $key => $value) {
            if (strpos($key, 'common_plan_') !== false && $value === '0') {
                // Add UID to common plans
                $commonPlans[] = explode('common_plan_', $key)[1];
            } else if (strpos($key, 'advanced_plan_') !== false && $value === '1') {
                // Add UID to advanced plans
                $advancedPlans[] = explode('advanced_plan_', $key)[1];
            }
        }

        //Get mobbex configs
        $productConfigs = [
            'entity'           => isset($this->params['entity']) ? $this->params['entity'] : '',
            'is_subscription'  => isset($this->params['enable_sub']) ? $this->params['enable_sub'] : 'no',
            'subscription_uid' => isset($this->params['sub_uid']) ? $this->params['sub_uid'] : '',
            'common_plans'     => $this->serializer->serialize($commonPlans),
            'advanced_plans'   => $this->serializer->serialize($advancedPlans),
        ];
        
        //Save mobbex custom fields
        foreach ($productConfigs as $key => $value) {
            $customField = $this->customFieldFactory->create();
            $customField->saveCustomField($observer->getProduct()->getId(), 'product', $key, $value);
        }

        $this->helper->executeHook('mobbexSaveProductSettings', false, $observer->getProduct(), $this->params);
    }
}