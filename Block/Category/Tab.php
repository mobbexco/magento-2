<?php
 
 namespace Mobbex\Webpay\Block\Category;
 

 use Magento\Customer\Model\Session;
 use Magento\Framework\ObjectManagerInterface;
 use Magento\Framework\View\Element\Template;
 use Magento\Framework\View\Element\Template\Context;
 use Mobbex\Webpay\Helper\Data;
 use Psr\Log\LoggerInterface;
 use Mobbex\Webpay\Helper\Config;
 use Magento\Ui\Component\Form\Field;
 use Magento\Ui\Component\Form\Fieldset;
 use Magento\Ui\Component\Form\Element\Select;
 use Magento\Ui\Component\Form\Element\DataType\Text;
 use \Magento\Framework\Registry;
 use Magento\Catalog\Model\Locator\LocatorInterface;

class Tab extends \Magento\Backend\Block\Template
{

    /**
    * @var Data
    */
    protected $_helper;


    /**
     * @var CustomFieldFactory
     */
    protected $_customFieldFactory;

    /**
    * @var Category
    */
    protected $_category = null;

	//protected $_template = "Mobbex_Webpay::catalog/category/tab.phtml";

    /**
     * CategoryAdminFields constructor.
     * @param Data $_helper
     */
    public function __construct(
        Data $_helper, 
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Framework\Registry $registry,
        LocatorInterface $_locator,
        \Mobbex\Webpay\Model\CustomFieldFactory $customFieldFactory,
        array $data = []
    ) {
        $this->_helper = $_helper;
        parent::__construct($context, $data);
        $this->_coreRegistry = $registry;
        $this->_locator = $_locator;
        $this->_customFieldFactory = $customFieldFactory;
    }


    public function getCategoriaid()
    {        
        if (!$this->_category) {
            $this->_category = $this->_coreRegistry->registry('category');
        }
        return $this->_category->getId();
    }


    public function modifyMeta(array $meta)
    {
        // Exit if module is not ready
        if (!$this->_helper->mobbex->isReady()) {
            return $meta;
        }

        $meta = array_replace_recursive(
            $meta,
            [
                'mobbex' => [
                    'arguments' => [
                        'data' => [
                            'config' => [
                                'label' => __('Mobbex Plans Settings'),
                                'collapsible' => true,
                                'componentType' => Fieldset::NAME,
                                'dataScope' => 'data.mobbex',
                                'sortOrder' => 10,
                            ],
                        ]
                    ],
                    'children' => $this->getFields()
                ],
            ]
        );

        return $meta;
    }

    /**
     * Returns form in html.
     * @return String
     */
    public function getFields()
    {
        // Get sources with common plans from mobbex
        $sources = $this->_helper->getSources();

        // Get saved values from database
        $categoryId   = $this->getCategoriaid();
        $customField = $this->_customFieldFactory->create();
        $checkedCommonPlans = unserialize($customField->getCustomField($categoryId, 'category', 'common_plans'));

        // Create form fields from received data
        $formFields = [];

        // Skip these plans for backward support
        $skippedPlans = [];
        $html = '<div class="admin__field-complex-content" data-bind="html: $data.content"><h3><b>Los planes habilitados aparecerán en el checkout de este producto. Deshabilítelos para que no aparezcan.</b></h3></div>';
        $html =$html. '<label style="font-size: 16px;padding-left: 10px;padding-top: 45px;"><b> Planes Básicos  </b></label>';
        foreach ($sources as $source) {
            // If source has plans
            if (!empty($source['installments']['list'])) {
                $installments = $source['installments']['list'];

                foreach ($installments as $installment) {
                    $reference = $installment['reference'];
                    
                    // If it hasn't been added to array yet
                    if (!array_key_exists('common_plan_' . $reference, $formFields) &&
                        !in_array($reference, $skippedPlans)
                    ) {
                        $isChecked = is_array($checkedCommonPlans) ? in_array($reference, $checkedCommonPlans) : false;
                        //if the category is new, then check = true in all common plans
                        $tag =  (!$isChecked || $categoryId == 0 ) ? 'checked=true' : ' ';
                        
                        $formFields['common_plan_' . $reference] = true;//added

                        // Create form fields data
                       $html =$html. '
                                <div class="row" style="margin-left: 5%;">
                                    <div class="col-md-8 form-group">
                                        <div class="checkbox">                          
                                        <label style="font-size: 16px;padding-left: 50px;">
                                            <input data-form-part="category_form" type="checkbox" id="'.$installment['name'].'" name="mobbex[common_plan_'.$installment['reference'].']" '.$tag.' >
                                            <label style="margin-left: 2%;"> <b>'.$installment['name'].' </b></label>
                                        </label>
                                        </div>
                                    </div>
                                </div>
                        ';
                    }
                }
            }
        }

        $html =$html. '<br><br>';
        $html =$html. ' <label style="font-size: 16px;padding-left: 10px;padding-top: 45px;"><b> Planes Avanzados </b></label>';
        // Get sources with advanced rule plans
        $sourcesAdvanced = $this->_helper->getSourcesAdvanced();
        // Get saved values from database
        $customField = $this->_customFieldFactory->create();
        $checkedAdvancedPlans = unserialize($customField->getCustomField($categoryId, 'category', 'advanced_plans'));

        foreach ($sourcesAdvanced as $source) {
            // If source has plans
            if (!empty($source['installments'])) {
                foreach ($source['installments'] as $installment) {
                    // If it hasn't been added to array yet
                    if (!array_key_exists('advanced_plan_' . $installment['uid'], $formFields)) {
                        $isChecked = is_array($checkedAdvancedPlans) ? in_array($installment['uid'], $checkedAdvancedPlans) : false;
                        $tag = ($isChecked) ? 'checked=true' : ' ';
                        $label = $source['source']['name'] .': '. $installment['name'];
                        $formFields['advanced_plan_' . $installment['uid']] = true;//added

                        $html =$html. '
                            <div class="row" style="margin-left: 5%;">
                                <div class="col-md-8 form-group">
                                    <div class="checkbox">                          
                                    <label style="font-size: 16px;padding-left: 50px;">
                                        <input data-form-part="category_form" type="checkbox" id="'.$installment['name'].'" name="mobbex[advanced_plan_'.$installment['uid'].']" '.$tag.' >
                                        <label style="margin-left: 2%;"> <b>'.$label.'</b></label>
                                    </label>
                                    </div>
                                </div>
                            </div>
                        ';
                    }
                }
            }
        }
        
        return $html;
    }



}
?>