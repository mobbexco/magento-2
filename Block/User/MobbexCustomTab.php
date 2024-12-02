<?php

namespace Mobbex\Webpay\Block\User;


class MobbexCustomTab extends \Magento\Backend\Block\Template
{
    /** @var \Magento\Backend\Block\Template\Context */
    public $context;

    /** @var \Magento\Framework\App\RequestInterface */
    public $request;

    /** @var \Mobbex\Webpay\Model\CustomField */
    public $customField;

    public $selectedPos = array();

    public $posList = array();

    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Framework\App\RequestInterface $request,
        \Mobbex\Webpay\Helper\Sdk $sdk,
        \Mobbex\Webpay\Model\CustomFieldFactory $customFieldFactory,
        array $data = []
    ) {
        parent::__construct($context, $data);
        
        //Init mobbex php sdk
        $sdk->init();

        $this->customField  = $customFieldFactory->create();
        $this->request = $request;

        $this->selectedPos = json_decode($this->customField->getCustomField($this->request->getParam('user_id'), 'user', 'pos_list'), true);
        $this->posList     = $this->getPosList();
    }

    /**
     * Get all POS avaible
     *
     * @return array
     */
    public function getPosList()
    {
        // Make capture request
        $result = \Mobbex\Api::request([
            'method' => 'GET',
            'uri'    => "pos/",
        ]);

        $posList = array_map(function ($pos) {
            return [
                'label' => __($pos['name']), 
                'value' => __($pos['uid']), 
                'description' => __($pos['description']), 
                'reference' => __($pos['reference'])
            ];
        }, $result['docs']);

        return $posList;
    }
}
