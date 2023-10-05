<?php

namespace Mobbex\Webpay\Helper;

class Sdk extends \Magento\Framework\App\Helper\AbstractHelper
{
    /** @var \Mobbex\Webpay\Helper\Config */
    public $config;

    /** @var \Mobbex\Webpay\Helper\Mobbex */
    public $helper;

    /** @var \Mobbex\Webpay\Helper\Logger */
    public $logger;

    /** @var \Magento\Framework\UrlInterface */
    public $_urlBuilder;

    /** @var \Magento\Framework\Module\ResourceInterface */
    public $moduleResource;

    /** @var \Magento\Framework\App\ProductMetadataInterface */
    public $productMetadata;

    /** @var \Mobbex\Webpay\Model\Cache */
    public $cache;

    /** @var \Mobbex\Webpay\Helper\Db */
    public $db;

    /**
     * Constructor.
     * 
     * @param \Mobbex\Webpay\Helper\Config $config
     * @param \Mobbex\Webpay\Helper\Db $db
     * @param \Mobbex\Webpay\Helper\Mobbex $helper
     * @param \Mobbex\Webpay\Helper\Logger $logger
     * @param \Magento\Framework\UrlInterface $urlBuilder
     * @param \Magento\Framework\Module\ResourceInterface $moduleResource
     * @param \Magento\Framework\App\ProductMetadataInterface $productMetadata
     * @param \Mobbex\Webpay\Model\Cache $cache
     * 
     */
    public function __construct(
        \Mobbex\Webpay\Helper\Config $config,
        \Mobbex\Webpay\Helper\Db $db,
        \Mobbex\Webpay\Helper\Mobbex $helper,
        \Mobbex\Webpay\Helper\Logger $logger,
        \Magento\Framework\UrlInterface $urlBuilder,
        \Magento\Framework\Module\ResourceInterface $moduleResource,
        \Magento\Framework\App\ProductMetadataInterface $productMetadata,
        \Mobbex\Webpay\Model\Cache $cache
    ) {
        $this->config          = $config;
        $this->db              = $db;
        $this->helper          = $helper;
        $this->logger          = $logger;
        $this->cache           = $cache;
        $this->moduleResource  = $moduleResource;
        $this->productMetadata = $productMetadata;
        $this->_urlBuilder     = $urlBuilder;
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

        \Mobbex\Platform::loadModels($this->cache, $this->db);

        // Init api conector
        \Mobbex\Api::init();
    }
}