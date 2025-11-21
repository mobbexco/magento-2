<?php

namespace Mobbex\Webpay\Observer;

class SaveUserSettings implements \Magento\Framework\Event\ObserverInterface
{
    /** @var \Mobbex\Webpay\Model\CustomField */
    public $customField;

    /** @var \Mobbex\Webpay\Helper\Logger */
    public $logger;

    public function __construct(
        \Mobbex\Webpay\Model\CustomFieldFactory $customFieldFactory,
        \Mobbex\Webpay\Helper\Logger $logger
    ) {
        $this->customField  = $customFieldFactory->create();
        $this->logger = $logger;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $user = $observer->getEvent()->getObject();

        if (!$user) {
            $this->logger->log('error', 'SaveUserSettings Observer: No user found in observer.');
            return;
        }

        if ($user->getData('user_id') && $user->getData('mobbex_pos'))
            $this->customField->saveCustomField(
                $user->getData('user_id'),
                'user',
                'pos_list',
                json_encode($user->getData('mobbex_pos')
            ));
    }
}
