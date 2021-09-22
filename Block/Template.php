<?php

namespace Mobbex\Webpay\Block;

use Magento\Backend\Block\Template as MagentoTemplate;

/**
 * Use this block to access the mobbex helper methods and props.
 */
class Template extends MagentoTemplate
{
    public function __construct(
        \Mobbex\Webpay\Helper\Data $helper, 
        \Magento\Backend\Block\Template\Context $context,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->helper = $helper;

        // Set params as public prop
        $this->params = $this->_request->getParams();
    }
}