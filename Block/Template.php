<?php

namespace Mobbex\Webpay\Block;

use Magento\Backend\Block\Template as MagentoTemplate;

/**
 * Use this block to access the mobbex helper methods and props.
 */
class Template extends MagentoTemplate
{
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Mobbex\Webpay\Helper\Instantiator $instantiator,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $instantiator->setProperties($this, ['sdk', 'config', 'helper', 'repository']);

        // Set params as public prop
        $this->params = $this->_request->getParams();
    }
}