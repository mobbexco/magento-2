<?php

namespace Mobbex\Webpay\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class CategorySaveObserver implements ObserverInterface
{
    /** @var \Mobbex\Webpay\Model\EventManager */
    public $eventManager;

    /** @var \Mobbex\Webpay\Model\CustomFieldFactory */
    public $customFieldFactory;

    /** @var \Magento\Framework\Serialize\Serializer\Serialize */
    public $serializer;

    /** @var array */
    public $params;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Mobbex\Webpay\Model\EventManager $eventManager,
        \Mobbex\Webpay\Model\CustomFieldFactory $customFieldFactory,
        \Magento\Framework\Serialize\Serializer\Serialize $serializer
    )
    {
        $this->eventManager       = $eventManager;
        $this->customFieldFactory = $customFieldFactory;
        $this->params             = $context->getRequest()->getParams();
        $this->serializer         = $serializer;
    }

    /**
     * Save own category options.
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
            } else if (strpos($key, 'advanced_plan_') !== false && $value === '1'){
                // Add UID to advanced plans
                $advancedPlans[] = explode('advanced_plan_', $key)[1];
            }
        }

        //Get mobbex configs
        $categoryConfigs = [
            'entity'           => isset($this->params['entity']) ? $this->params['entity'] : '',
            'common_plans'     => $this->serializer->serialize($commonPlans),
            'advanced_plans'   => $this->serializer->serialize($advancedPlans),
        ];

        //Save mobbex custom fields
        foreach ($categoryConfigs as $key => $value) {
            $customField = $this->customFieldFactory->create();
            $customField->saveCustomField($observer->getCategory()->getId(), 'category', $key, $value);
        }

        $this->eventManager->dispatch('mobbexSaveCategorySettings', false, $observer->getCategory(), $this->params);
    }
}