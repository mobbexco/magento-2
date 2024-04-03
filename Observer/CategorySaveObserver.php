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

    /** @var array */
    public $params;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Mobbex\Webpay\Helper\Mobbex $helper,
        \Mobbex\Webpay\Model\CustomFieldFactory $customFieldFactory
    )
    {
        $this->helper             = $helper;
        $this->customFieldFactory = $customFieldFactory;
        $this->params             = $context->getRequest()->getParams();
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
            'entity'              => isset($this->params['entity']) ? $this->params['entity'] : '',
            'plans_configuration' => isset($this->params['mbbx_sources']) ? $this->params['mbbx_sources'] : "[]",
        ];

        //Save mobbex custom fields
        foreach ($categoryConfigs as $key => $value) {
            $customField = $this->customFieldFactory->create();
            $customField->saveCustomField($observer->getCategory()->getId(), 'category', $key, $value);
        }

        //Save plans sort order
        if (isset($this->params['mbbx_sources'])) {
            $plans_order = \Mobbex\Repository::getPlansSortOrder(json_decode($this->params['mbbx_sources'], true));
            $this->customFieldFactory->create()->saveCustomField(1, 'mobbex_plans', 'plans_order', json_encode($plans_order));
        }

        $this->helper->executeHook('mobbexSaveCategorySettings', false, $observer->getCategory(), $this->params);
    }
}