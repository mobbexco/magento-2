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

    /** @var \Mobbex\Webpay\Model\Cache */
    public $cache;

    public function __construct(
        \Mobbex\Webpay\Helper\Instantiator $instantiator,
        \Magento\Framework\Module\ResourceInterface $moduleResource,
        \Magento\Framework\App\ProductMetadataInterface $productMetadata,
        \Mobbex\Webpay\Model\Cache $cache
    ) {
        $instantiator->setProperties($this, ['config', 'helper', '_urlBuilder', 'logger']);
        $this->moduleResource  = $moduleResource;
        $this->productMetadata = $productMetadata;
        $this->cache           = $cache;
    }

    /**
     * Allow to use SDK classes.
     */
    public function init()
    {
        // Set platform information
        \Mobbex\Platform::init('magento_2', $this->moduleResource->getDbVersion('Mobbex_Webpay'), $this->_urlBuilder->getUrl('/'),
            [
                'platform' => $this->productMetadata->getVersion(),
                'sdk'      => class_exists('\Composer\InstalledVersions') ? \Composer\InstalledVersions::getVersion('mobbexco/php-plugins-sdk') : '',
            ], 
            $this->config->getAll(), 
            [$this->helper, 'executeHook'],
            [$this->logger, 'log']
        );

        \Mobbex\Platform::loadModels($this->cache);

        // Init api conector
        \Mobbex\Api::init();
    }
}