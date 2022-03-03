<?php

namespace Mobbex\Webpay\Block;

class FinanceWidget extends \Magento\Backend\Block\Template
{
    /** @var \Mobbex\Webpay\Helper\Data */
    public $helper;

    /** @var \Mobbex\Webpay\Helper\Config */
    public $config;

    /** @var \Magento\Framework\Registry */
    public $registry;

    /** @var \Magento\Framework\Pricing\Helper\Data */
    public $priceHelper;

    /** Amount ot calculate payment methods */
    public $total = 0;

    /** Products to apply their plans config */
    public $products = [];

    /** Sources to show in finance widget */
    public $sources = [];

    public function __construct(
        \Mobbex\Webpay\Helper\Data $helper,
        \Mobbex\Webpay\Helper\Config $config,
        \Magento\Framework\Registry $registry,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Framework\Pricing\Helper\Data $priceHelper,
        \Magento\Backend\Block\Template\Context $context,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->helper      = $helper;
        $this->config      = $config;
        $this->registry    = $registry;
        $this->priceHelper = $priceHelper;

        // Get current action name
        $action = $this->_request->getFullActionName();

        // Get current objects
        $product = $this->registry->registry('product');
        $quote   = $checkoutSession->getQuote();

        // Exit if quote is empty or product cannot be sold
        if ($action == 'catalog_product_view' ? !$product->isSaleable() : !$quote->hasItems())
            return $this->getLayout()->unsetElement('mbbx.finance.widget');

        $this->total    = $action == 'catalog_product_view' ? $product->getFinalPrice() : $quote->getGrandTotal();
        $this->products = $action == 'catalog_product_view' ? [$product->getId()] : $quote->getAllVisibleItems();
        $this->sources  = $this->helper->getSources($this->total, $this->helper->mobbex->getInstallments($this->products));

    }
}