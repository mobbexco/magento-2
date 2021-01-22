<?php

namespace Mobbex\Webpay\Ui;

use Mobbex\Webpay\Helper\Data;
use Magento\Catalog\Model\Locator\LocatorInterface;
use Magento\Catalog\Ui\DataProvider\Product\Form\Modifier\AbstractModifier;
use Magento\Ui\Component\Form\Field;
use Magento\Ui\Component\Form\Fieldset;
use Magento\Ui\Component\Form\Element\Select;
use Magento\Ui\Component\Form\Element\DataType\Text;

class ProductAdminFields extends AbstractModifier
{
    /**
     * @var Data
     */
    protected $_helper;

    /**
     * @var LocatorInterface
     */
    protected $_locator;

    /**
     * @var CustomFieldFactory
     */
    protected $_customFieldFactory;

    /**
     * ProductAdminFields constructor.
     * @param Data $_helper
     * @param LocatorInterface $_locator
     * @param CustomFieldFactory $_customFieldFactory
     */
    public function __construct(
        Data $_helper, 
        LocatorInterface $_locator,
        \Mobbex\Webpay\Model\CustomFieldFactory $customFieldFactory
        ) 
    {
        $this->_helper = $_helper;
        $this->_locator = $_locator;
        $this->_customFieldFactory = $customFieldFactory;
    }

    public function modifyMeta(array $meta)
    {
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
     * Returns form fields.
     * @return array
     */
    public function getFields()
    {
        // Get sources with common plans from mobbex
        $sources = $this->_helper->getSources();

        // Get saved values from database
        $productId   = $this->_locator->getProduct()->getId();
        $customField = $this->_customFieldFactory->create();
        $checkedCommonPlans = unserialize($customField->getCustomField($productId, 'product', 'common_plans'));

        // Create form fields from received data
        $formFields = [];
        $formFields['title_field'] = $this->getTitle();

        // Skip these plans for backward support
        $skippedPlans = ['ahora_3', 'ahora_6', 'ahora_12', 'ahora_18'];
        
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

                        // Create form fields data
                        $formFields['common_plan_' . $reference]['arguments']['data']['config'] = [
                            'label'         => $installment['name'],
                            'componentType' => Field::NAME,
                            'formElement'   => Select::NAME,
                            'dataScope'     => 'common_plan_' . $reference,
                            'dataType'      => Text::NAME,
                            'sortOrder'     => count($formFields) + 1,
                            'value'         => $isChecked ? 1 : 0,
                            'options'       => [
                                ['value' => '0', 'label' => __('Enabled')],
                                ['value' => '1', 'label' => __('Disabled')]
                            ],
                        ];
                    }
                }
            }
        }

        // Get sources with advanced rule plans
        $sourcesAdvanced = $this->_helper->getSourcesAdvanced();
        // Get saved values from database
        $customField = $this->_customFieldFactory->create();
        $checkedAdvancedPlans = unserialize($customField->getCustomField($productId, 'product', 'advanced_plans'));

        foreach ($sourcesAdvanced as $source) {
            // If source has plans
            if (!empty($source['installments'])) {
                foreach ($source['installments'] as $installment) {
                    // If it hasn't been added to array yet
                    if (!array_key_exists('advanced_plan_' . $installment['uid'], $formFields)) {
                        $isChecked = is_array($checkedAdvancedPlans) ? in_array($installment['uid'], $checkedAdvancedPlans) : false;

                        // Create form fields data
                        $formFields['advanced_plan_' . $installment['uid']]['arguments']['data']['config'] = [
                            'label'         => $source['source']['name'] .': '. $installment['name'],
                            'notice'        => __('Plan with advanced rules'),
                            'componentType' => Field::NAME,
                            'formElement'   => Select::NAME,
                            'dataScope'     => 'advanced_plan_' . $installment['uid'],
                            'dataType'      => Text::NAME,
                            'sortOrder'     => count($formFields) + 1,
                            'value'         => $isChecked ? 1 : 0,
                            'options'       => [
                                ['value' => '0', 'label' => __('Disabled')],
                                ['value' => '1', 'label' => __('Enabled')]
                            ],
                        ];
                    }
                }
            }
        }
        
        return $formFields;
    }

    /**
     * Returns the form title (inside 'container' component).
     * @return array
     */
    public function getTitle()
    {
        return [
            'arguments' => [
                'data' => [
                    'config' => [
                        'content' => __("Los planes habilitados aparecerán en el checkout de este producto. Deshabilítelos para que no aparezcan."),
                        'formElement' => 'container',
                        'componentType' => 'container',
                        'template' => 'ui/form/components/complex',
                        'label' => false,
                    ],
                ],
            ],
        ];
    }

    
    public function modifyData(array $data)
    {
        return $data;
    }
}
