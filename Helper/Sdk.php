<?php

namespace Mobbex\Webpay\Helper;

class Sdk extends \Magento\Framework\App\Helper\AbstractHelper
{
    /** @var \Mobbex\Webpay\Helper\Instantiator */
    public $instantiator;

    /** @var \Magento\Framework\Module\ResourceInterface */
    public $moduleResource;

    /** @var \Magento\Framework\App\ProductMetadataInterface */
    public $productMetadata;

    public function __construct(
        \Mobbex\Webpay\Helper\Instantiator $instantiator,
        \Magento\Framework\Module\ResourceInterface $moduleResource,
        \Magento\Framework\App\ProductMetadataInterface $productMetadata
    ) {
        $instantiator->setProperties($this, ['config', 'helper', '_urlBuilder']);
        $this->moduleResource  = $moduleResource;
        $this->productMetadata = $productMetadata;
    }

    /**
     * Allow to use SDK classes.
     */
    public function init()
    {
        // Set platform information
        \Mobbex\Platform::init('magento_2', $this->moduleResource->getDbVersion('Mobbex'), $this->_urlBuilder->getUrl('/'),
        [
            'magento' => $this->productMetadata->getVersion(),
            'webpay'  => $this->moduleResource->getDbVersion('Mobbex_Webpay'),
            'sdk'     => \Composer\InstalledVersions::getVersion('mobbexco/php-plugins-sdk'),
        ], $this->config->getAll(), [$this->helper, 'executeHook']);

        // Init api conector
        \Mobbex\Api::init();
    }
}