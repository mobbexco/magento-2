<?php

namespace Mobbex\Webpay\Observer;

use Magento\Framework\App\Action\Context;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class CategorySaveObserver implements \Magento\Framework\Event\ObserverInterface
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
    protected $registry;
    /**
     * ProductSaveObserver constructor.
     * @param CustomFieldFactory $_customFieldFactory
     */
    public function __construct(
        Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\App\RequestInterface $request,
        \Mobbex\Webpay\Model\CustomFieldFactory $customFieldFactory
    ) {
        $this->_customFieldFactory = $customFieldFactory;
        $this->context = $context;
        $this->_request = $context->getRequest();
        $this->registry = $registry;
    }


    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        // Get category id
        $category = $this->registry->registry('current_category');//get current category
        $categoryId = $category->getId();
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
        

        $customField = $this->_customFieldFactory->create();
        $customField->saveCustomField($categoryId, 'category', 'common_plans', serialize($commonPlans));
        $customField->saveCustomField($categoryId, 'category', 'advanced_plans', serialize($advancedPlans));
    }
}