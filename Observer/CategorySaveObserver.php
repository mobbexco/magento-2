<?php

namespace Mobbex\Webpay\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class CategorySaveObserver implements ObserverInterface
{
    public function __construct(\Magento\Framework\App\Action\Context $context, \Mobbex\Webpay\Helper\Instantiator $instantiator)
    {
        $instantiator->setProperties($this, ['helper', 'customFieldFactory']);
        $this->params = $context->getRequest()->getParams();
    }

    /**
     * Save own category options.
     * 
     * @param Observer $observer
     */
    public function execute($observer)
    {
        // Get plans selected
        $commonPlans = $advancedPlans = [];
        foreach ($this->params as $key => $value) {
            if (strpos($key, 'common_plan_') !== false && $value === '0') {
                // Add UID to common plans
                $commonPlans[] = explode('common_plan_', $key)[1];
            } else if (strpos($key, 'advanced_plan_') !== false && $value === '1'){
                // Add UID to advanced plans
                $advancedPlans[] = explode('advanced_plan_', $key)[1];
            }
        }

        //Get mobbex configs
        $categoryConfigs = [
            'entity'           => isset($this->params['entity']) ? $this->params['entity'] : '',
            'common_plans'     => serialize($commonPlans),
            'advanced_plans'   => serialize($advancedPlans),
        ];

        //Save mobbex custom fields
        foreach ($categoryConfigs as $key => $value) {
            $customFields = $this->customFieldFactory->create();
            $customFields->saveCustomField($observer->getCategory()->getId(), 'category', $key, $value);
        }

        $this->helper->executeHook('mobbexSaveCategorySettings', false, $observer->getCategory(), $this->params);
    }
}