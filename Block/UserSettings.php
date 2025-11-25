<?php

namespace Mobbex\Webpay\Block;

/**
 * Class UserSettings
 * 
 * Custom tab for admin user configuration to manage POS terminals assignment.
 * 
 * @package Mobbex\Webpay\Block\User
 */
class UserSettings extends \Magento\Backend\Block\Template
{
    /** @var \Magento\Framework\App\RequestInterface */
    private $request;

    /** @var \Mobbex\Webpay\Helper\Pos */
    private $posHelper;

    /** @var array */
    public $userPosList = [];

    /** @var array */
    public $entityPosList = [];

    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Framework\App\RequestInterface $request,
        \Mobbex\Webpay\Helper\Sdk $sdk,
        \Mobbex\Webpay\Helper\Pos $posHelper,
        array $data = []
    ) {
        parent::__construct($context, $data);

        $this->request = $request;
        $this->posHelper = $posHelper;

        $sdk->init();

        $this->entityPosList = $this->posHelper->getAllPosList();
        $this->userPosList = $this->posHelper->getUserAssignedPosUids($this->request->getParam('user_id'));
    }
}
