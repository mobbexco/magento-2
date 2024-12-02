<?php

namespace Mobbex\Webpay\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class SaveUserPos implements ObserverInterface
{
    /** @var \Mobbex\Webpay\Model\CustomField */
    public $customField;

    public function __construct(
        \Mobbex\Webpay\Model\CustomFieldFactory $customFieldFactory
    ) {
        $this->customField  = $customFieldFactory->create();
    }

    public function execute(Observer $observer)
    {
        $request = $observer->getEvent()->getObject()->getData();

        if(isset($request['user_id']) && isset($request['mobbex_pos']))
            $this->customField->saveCustomField($request['user_id'], 'user', 'pos_list', json_encode($request['mobbex_pos']));
    }
}
