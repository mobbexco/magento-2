<?php
 
 namespace Mobbex\Webpay\Block\Category;
 
 use Magento\Customer\Model\Session;
 use Magento\Framework\ObjectManagerInterface;
 use Magento\Framework\View\Element\Template;
 use Magento\Framework\View\Element\Template\Context;
 use Mobbex\Webpay\Helper\Data;
 use Psr\Log\LoggerInterface;
 use Mobbex\Webpay\Helper\Config;
 use Magento\Catalog\Model\Product;


class Tab extends \Magento\Backend\Block\Template
{
//    protected $_template = 'newtab.phtml';
	protected $_template = "Mobbex_Webpay::catalog/category/tab.phtml";

    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }
}
?>