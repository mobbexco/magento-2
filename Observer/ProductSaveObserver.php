<?php

namespace Mobbex\Webpay\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class ProductSaveObserver implements ObserverInterface
{
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Mobbex\Webpay\Model\CustomFieldFactory $customFieldFactory,
        \Mobbex\Webpay\Helper\Data $helper
    ) {
        $this->customFieldsFactory = $customFieldFactory;
        $this->params              = $context->getRequest()->getParams();
        $this->helper              = $helper;
    }

    /**
     * Save own product options.
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
            'common_plans'     => serialize($commonPlans),
            'advanced_plans'   => serialize($advancedPlans),
        ];
        
        //Save mobbex custom fields
        foreach ($productConfigs as $key => $value) {
            $customFields = $this->customFieldsFactory->create();
            $customFields->saveCustomField($observer->getProduct()->getId(), 'product', $key, $value);
        }

        $this->helper->mobbex->executeHook('mobbexSaveProductSettings', false, $observer->getProduct(), $this->params);
    }
}