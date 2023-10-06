<?php

namespace Mobbex\Webpay\Block;

use Magento\Backend\Block\Template as MagentoTemplate;

/**
 * Use this block to access the mobbex helper methods and props.
 */
class Template extends MagentoTemplate
{
    /** @var \Mobbex\Webpay\Helper\Sdk */
    public $sdk;

    /** @var \Mobbex\Webpay\Helper\Config */
    public $config;

    /** @var \Mobbex\Webpay\Helper\Mobbex */
    public $helper;
   
    /** @var \Mobbex\Webpay\Helper\Logger */
    public $logger;

    /** @var array */
    public $params;

    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Mobbex\Webpay\Helper\Sdk $sdk,
        \Mobbex\Webpay\Helper\Config $config,
        \Mobbex\Webpay\Helper\Mobbex $helper,
        \Mobbex\Webpay\Helper\Logger $logger,
        array $data = []
    ) {
        parent::__construct($context, $data);

        $this->sdk    = $sdk;
        $this->config = $config;
        $this->helper = $helper;
        $this->logger = $logger;

        //Init mobbex php plugins sdk
        $this->sdk->init();
        
        // Set params as public prop
        $this->params = $this->_request->getParams();
    }
}