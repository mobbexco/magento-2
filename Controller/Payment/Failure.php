<?php

namespace Mobbex\Webpay\Controller\Payment;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

/**
 * Class Failure
 * @package Mobbex\Webpay\Controller\Payment
 */
class Failure extends Action
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

    public function execute()
    {
        error_log('se ejecuto: ' . "\n" . json_encode('sipi', JSON_PRETTY_PRINT) . "\n", 3, 'log.log');
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $_checkoutSession = $objectManager->create('\Magento\Checkout\Model\Session');
        $_quoteFactory = $objectManager->create('\Magento\Quote\Model\QuoteFactory');
        
        $order = $_checkoutSession->getLastRealOrder();
        $quote = $_quoteFactory->create()->loadByIdWithoutStore($order->getQuoteId());
        if ($quote->getId()) {
            $quote->setIsActive(1)->setReservedOrderId(null)->save();
            $_checkoutSession->replaceQuote($quote);
            $resultRedirect = $this->resultRedirectFactory->create();
            $resultRedirect->setPath('checkout/cart');
            //$this->messageManager->addWarningMessage('Payment Failed.');
            return $resultRedirect;
        }
    }
}
