<?php

namespace Mobbex\Webpay\Controller\Payment;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

/**
 * Class Redirect
 * @package Mobbex\Webpay\Controller\Payment
 */
class Redirect extends Action
{
    /**
     * @var PageFactory
     */
    protected $resultPageFactory;

    /**
     * Redirect constructor.
     * @param Context $context
     * @param PageFactory $resultPageFactory
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory
    ) {
        $this->resultPageFactory = $resultPageFactory;
        parent::__construct($context);
    }

    /**
     * @return ResponseInterface|ResultInterface|Page
     */
    public function execute()
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->getConfig()->getTitle()->prepend(__('Loading...'));

        $block = $resultPage->getLayout()
            ->createBlock('Mobbex\Webpay\Block\Payment\Redirect')
            ->setTemplate('Mobbex_Webpay::payment/redirect.phtml')
            ->toHtml();
        $this->getResponse()->setBody($block);

        return $this->resultPageFactory->create();
    }
}
