<?php

namespace Mobbex\Webpay\Block;

class ProductTag extends \Magento\Framework\View\Element\Template
{
    /** @var \Mobbex\Webpay\Helper\Config */
    public $config;

    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Mobbex\Webpay\Helper\Config $config,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->config = $config;
    }

    /**
     * Checks if the product catalog tag feature is enabled.
     *
     * @return bool
     */
    public function isTagEnabled()
    {
        return (bool) $this->config->get('show_tag_on_products_catalog');
    }

    /**
     * Checks if the product catalog banner feature is enabled.
     *
     * @return bool
     */
    public function isBannerEnabled()
    {
        return (bool) $this->config->get('show_banner_on_products_catalog');
    }

    /**
     * Returns controller URL to get finance data.
     *
     * @return string
     */
    public function getFinanceDataUrl()
    {
        return $this->getUrl('sugapay/catalog/financedata');
    }
}