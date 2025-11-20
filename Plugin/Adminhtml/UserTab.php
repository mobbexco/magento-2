<?php

namespace Mobbex\Webpay\Plugin\Adminhtml;

class UserTab
{
    /**
     * Add custom tab to the user edit page
     *
     * @param UserEditTabs $subject
     * @return UserEditTabs
     */
    public function beforeToHtml(\Magento\User\Block\User\Edit\Tabs $subject)
    {
        $subject->addTab(
            'custom_tab',
            [
                'label' => __('Mobbex POS'),
                'title' => __('Mobbex POS'),
                'content' => $subject->getLayout()->createBlock('Mobbex\Webpay\Block\User\PosConfig')
                    ->setTemplate('Mobbex_Webpay::user/mobbex-tab.phtml')
                    ->toHtml(),
                'sort_order' => 100,
            ]
        );
        return $subject;
    }
}
