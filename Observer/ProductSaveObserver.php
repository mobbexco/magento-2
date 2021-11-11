<?php

namespace Mobbex\Webpay\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class ProductSaveObserver implements ObserverInterface
{
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Mobbex\Webpay\Model\CustomFieldFactory $customFieldFactory
    ) {
        $this->customFields = $customFieldFactory->create();
        $this->params       = $context->getRequest()->getParams();
    }

    /**
     * Save own product options.
     * 
     * @param Observer $observer
     */
    public function execute($observer)
    {
        $commonPlans = $advancedPlans = [];

        // Get plans selected
        foreach ($this->params as $key => $value) {
            if (strpos($key, 'common_plan_') !== false && $value === '0') {
                // Add UID to common plans
                $commonPlans[] = explode('common_plan_', $key)[1];
            } else if (strpos($key, 'advanced_plan_') !== false && $value === '1'){
                // Add UID to advanced plans
                $advancedPlans[] = explode('advanced_plan_', $key)[1];
            }
        }

        $entity = $this->params['entity'] ?: '';

        $this->customFields->saveCustomField($observer->getProduct()->getId(), 'product', 'common_plans', serialize($commonPlans));
        $this->customFields->saveCustomField($observer->getProduct()->getId(), 'product', 'advanced_plans', serialize($advancedPlans));
        $this->customFields->saveCustomField($observer->getProduct()->getId(), 'product', 'entity', $entity);
    }
}