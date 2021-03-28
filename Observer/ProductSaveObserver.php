<?php

namespace Mobbex\Webpay\Observer;

use Magento\Framework\App\Action\Context;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class ProductSaveObserver implements ObserverInterface
{
    /**
     * @var CustomFieldFactory
     */
    protected $_customFieldFactory;

    /**
     * @var Context
     */
    protected $context;
    protected $_request;

    /**
     * ProductSaveObserver constructor.
     * @param CustomFieldFactory $_customFieldFactory
     */
    public function __construct(
        Context $context,
        \Mobbex\Webpay\Model\CustomFieldFactory $customFieldFactory
    ) {
        $this->_customFieldFactory = $customFieldFactory;
        $this->context = $context;
        $this->_request = $context->getRequest();
    }

    /**
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        // Get product id
        $productId = $observer->getProduct()->getId();
        // Get post data
        $params = $this->_request->getParams();
        if(!isset($params['mobbex'])){
            return;
        }
        $postFields = $params['mobbex'];

        $commonPlans = [];
        $advancedPlans = [];

        // Get plans selected and save data
        foreach ($postFields as $id => $value) {
            if (strpos($id, 'common_plan_') !== false && $value === '1') {
                $uid = explode('common_plan_', $id)[1];
                $commonPlans[] = $uid;
            } else if (strpos($id, 'advanced_plan_') !== false && $value === '1') {
                $uid = explode('advanced_plan_', $id)[1];
                $advancedPlans[] = $uid;
            } else {
                unset($postFields[$id]);
            }
        }

        $customFieldCommon = $this->_customFieldFactory->create();
        $customFieldCommon->saveCustomField($productId, 'product', 'common_plans', serialize($commonPlans));
        $customFieldAdvance = $this->_customFieldFactory->create();
        $customFieldAdvance->saveCustomField($productId, 'product', 'advanced_plans', serialize($advancedPlans));
    }
}
