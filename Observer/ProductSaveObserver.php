<?php

namespace Mobbex\Webpay\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class ProductSaveObserver implements ObserverInterface
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
        \Magento\Framework\Serialize\Serializer\Serialize $serialize
    )
    {
        $this->eventManager       = $eventManager;
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

        //Get mobbex configs
        $productConfigs = [
            'entity'           => isset($this->params['entity']) ? $this->params['entity'] : '',
            'subscription_uid' => isset($this->params['sub_uid']) ? $this->params['sub_uid'] : '',
            'common_plans'     => isset($this->params['common_plans'  ]) ? $this->params['common_plans'] : "[]",
            'manual_config'    => isset($this->params['mobbex_manual_config']) ? $this->params['mobbex_manual_config'] : "no",
            'featured_plans'   => isset($this->params['mobbex_featured_plans']) ? $this->params['mobbex_featured_plans'] : "[]",
            'advanced_plans'   => isset($this->params['mobbex_advanced_plans']) ? $this->params['mobbex_advanced_plans'] : "[]",
            'selected_plans'   => isset($this->params['mobbex_selected_plans']) ? $this->params['mobbex_selected_plans'] : "[]",
            'show_featured'    => isset($this->params['mobbex_show_featured_plans']) ? $this->params['mobbex_show_featured_plans'] : "no",
        ];
        
        //Save mobbex custom fields
        foreach ($productConfigs as $key => $value) {
            $customField = $this->customFieldFactory->create();
            $customField->saveCustomField($observer->getProduct()->getId(), 'product', $key, $value);
        }

        $this->eventManager->dispatch('mobbexSaveProductSettings', false, $observer->getProduct(), $this->params);
    }
}