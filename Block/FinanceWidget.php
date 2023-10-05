<?php

namespace Mobbex\Webpay\Block;

class FinanceWidget extends \Magento\Backend\Block\Template
{
    /** @var \Magento\Backend\Block\Template\Context */
    public $context;

    /** @var \Magento\Framework\Registry */
    public $registry;

    /** @var \Magento\Framework\Pricing\Helper\Data */
    public $priceHelper;

    /** @var \Magento\Checkout\Model\Session */
    public $checkoutSession;

    /** @var \Mobbex\Webpay\Helper\Sdk */
    public $sdk;

    /** @var \Mobbex\Webpay\Helper\Config */
    public $config;

    /** Amount ot calculate payment methods */
    public $total = 0;

    /** Products to apply their plans config */
    public $products = [];

    /** Sources to show in finance widget */
    public $sources = [];

    /**
     * Constructor.
     *
     * @param \Magento\Backend\Block\Template\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\Pricing\Helper\Data $priceHelper
     * @param \Magento\Checkout\Model\Session $CheckoutSession
     * @param \Mobbex\Webpay\Helper\Sdk $sdk
     * @param \Mobbex\Webpay\Helper\Config $config
     * @param array $data
     * 
     */
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Pricing\Helper\Data $priceHelper,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Mobbex\Webpay\Helper\Sdk $sdk,
        \Mobbex\Webpay\Helper\Config $config,
        array $data = []
    ) {
        parent::__construct($context, $data);

        $this->registry         = $registry;
        $this->priceHelper      = $priceHelper;
        $this->_checkoutSession = $checkoutSession;
        $this->sdk              = $sdk;
        $this->config           = $config;

        //Init mobbex php sdk
        $this->sdk->init();

        // Get current action name
        $action = $this->_request->getFullActionName();

        // Get current objects
        $product = $this->registry->registry('product');
        $quote   = $this->_checkoutSession->getQuote();

        // Exit if quote is empty or product cannot be sold
        if ($action == 'catalog_product_view' ? !$product->isSaleable() : !$quote->hasItems())
            return $this->getLayout()->unsetElement('mbbx.finance.widget');

        $this->products = $action == 'catalog_product_view' ? [$product] : [];

        if(empty($this->products)) {
            foreach ($quote->getAllVisibleItems() as $item)
                $this->products[] = $item->getProduct();
        }
        
        $this->total = $action == 'catalog_product_view' ? $product->getPriceInfo()->getPrice('final_price')->getValue() : $quote->getGrandTotal();
        extract($this->config->getAllProductsPlans($this->products));
        $this->sources = \Mobbex\Repository::getSources($this->total, \Mobbex\Repository::getInstallments($this->products, $common_plans, $advanced_plans));
    }


}