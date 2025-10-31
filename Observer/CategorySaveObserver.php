<?php

namespace Mobbex\Webpay\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class CategorySaveObserver implements ObserverInterface
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
        \Magento\Framework\Serialize\Serializer\Serialize $serializer
    )
    {
        $this->helper             = $helper;
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

        //Get mobbex configs
        $categoryConfigs = [
            'entity'         => isset($this->params['entity']) ? $this->params['entity'] : '',
            'common_plans'   => isset($this->params['common_plans']) ? $this->params['common_plans']: "[]",
            'manual_config'  => isset($this->params['mobbex_manual_config']) ? $this->params['mobbex_manual_config'] : "no",
            'featured_plans' => isset($this->params['mobbex_featured_plans']) ? $this->params['mobbex_featured_plans'] : "[]",
            'advanced_plans' => isset($this->params['mobbex_advanced_plans']) ? $this->params['mobbex_advanced_plans'] : "[]",
            'selected_plans' => isset($this->params['mobbex_selected_plans']) ? $this->params['mobbex_selected_plans'] : "[]",
            'show_featured'  => isset($this->params['mobbex_show_featured_plans']) ? $this->params['mobbex_show_featured_plans'] : "no",
        ];

        //Save mobbex custom fields
        foreach ($categoryConfigs as $key => $value) {
            $customField = $this->customFieldFactory->create();
            $customField->saveCustomField($observer->getCategory()->getId(), 'category', $key, $value);
        }

        $this->helper->executeHook('mobbexSaveCategorySettings', false, $observer->getCategory(), $this->params);
    }
}