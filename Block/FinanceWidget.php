<?php

namespace Mobbex\Webpay\Block;

class FinanceWidget extends \Magento\Backend\Block\Template
{
    /** @var \Mobbex\Webpay\Helper\Instantiator */
    public $instantiator;

    /** @var \Magento\Framework\Registry */
    public $registry;

    /** Amount ot calculate payment methods */
    public $total = 0;

    /** Products to apply their plans config */
    public $products = [];

    /** Sources to show in finance widget */
    public $sources = [];

    public function __construct(
        \Mobbex\Webpay\Helper\Instantiator $instantiator, 
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Pricing\Helper\Data $priceHelper,
        \Magento\Backend\Block\Template\Context $context,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $instantiator->setProperties($this, ['sdk', 'config', '_checkoutSession']);
        $this->registry = $registry;
        $this->priceHelper = $priceHelper;

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
        extract($this->config->getProductPlans($this->products));
        $this->sources = \Mobbex\Repository::getSources($this->total, \Mobbex\Repository::getInstallments($this->products, $common_plans, $advanced_plans));
    }


}