<?php

namespace Mobbex\Webpay\Plugin\Adminhtml;

class CaptureButton
{
    /** @var \Magento\Sales\Api\OrderRepositoryInterface */
    public $orderRepository;

    public function __construct(
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
    ) {
        $this->orderRepository = $orderRepository;
    }

    /**
     * Intercept order actions addition to push own capture button.
     * 
     * @param \Magento\Backend\Block\Widget\Button\Toolbar\Interceptor $subject
     * @param \Magento\Framework\View\Element\AbstractBlock $context
     * @param \Magento\Backend\Block\Widget\Button\ButtonList $buttonList
     */
    public function beforePushButtons(
        \Magento\Backend\Block\Widget\Button\Toolbar\Interceptor $subject,
        \Magento\Framework\View\Element\AbstractBlock $context,
        \Magento\Backend\Block\Widget\Button\ButtonList $buttonList
    ) {
        if ($context->getRequest()->getFullActionName() != 'sales_order_view')
            return;

        $order = $this->orderRepository->get(
            $context->getRequest()->getParam('order_id')
        );

        // Only if order is authorized
        if ($order->getStatus() != 'mobbex_authorized')
            return;

        // Get url of capture controller
        $url = $context->getUrl('webpay_admin/payment/capture', [
            'order_id' => $context->getRequest()->getParam('order_id')
        ]);

        // Add to current button list
        $buttonList->add(
            'mbbxCaptureButton',
            [
                'label'   => __('Capture'),
                'class'   => 'reset',
                'onclick' => "setLocation('$url')",
            ],
            -1
        );
    }
}