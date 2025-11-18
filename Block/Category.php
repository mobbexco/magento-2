<?php

namespace Mobbex\Webpay\Block;

class Category extends \Magento\Backend\Block\Template
{
    /** @var \Mobbex\Webpay\Helper\Sdk */
    public $sdk;

    /** @var \Mobbex\Webpay\Helper\Config */
    public $config;

    /** @var \Mobbex\Webpay\Model\EventManager */
    public $eventManager;

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
        \Mobbex\Webpay\Model\EventManager $eventManager,
        \Mobbex\Webpay\Helper\Logger $logger,
        array $data = []
    ) {
        parent::__construct($context, $data);

        $this->sdk    = $sdk;
        $this->config = $config;
        $this->eventManager = $eventManager;
        $this->logger = $logger;
        $this->params = $this->_request->getParams();

        $this->sdk->init();
    }
}