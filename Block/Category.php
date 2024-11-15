<?php

namespace Mobbex\Webpay\Block;

class Category extends \Magento\Backend\Block\Template
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

    /** @var string */
    public $type = 'category';

    /** @var string */
    public $form = 'category_form';

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
        $this->params = $this->_request->getParams();

        $this->sdk->init();
    }
}