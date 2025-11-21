<?php

namespace Mobbex\Webpay\Plugin\Adminhtml;

class UserTab
{
    /**
     * Add custom tab to the user edit page
     *
     * @param \Magento\User\Block\User\Edit\Tabs $subject
     * 
     * @return \Magento\User\Block\User\Edit\Tabs
     */
    public function beforeToHtml(\Magento\User\Block\User\Edit\Tabs $subject)
    {
        return $subject->addTab(
            'mobbex_user_settings',
            [
                'label' => 'Mobbex',
                'title' => 'Mobbex',
                'sort_order' => 100,
                'content' => $subject->getLayout()
                    ->createBlock('Mobbex\Webpay\Block\UserSettings')
                    ->setTemplate('Mobbex_Webpay::user-settings.phtml')
                    ->toHtml(),
            ]
        );
    }
}
