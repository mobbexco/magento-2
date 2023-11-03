<?php

namespace Mobbex\Webpay\Plugin;

use Magento\Checkout\Block\Checkout\LayoutProcessor;

/**
 * Class CheckoutForm
 * @package Mobbex\Webpay\Plugin
 */
class CheckoutForm
{
    /** @var Mobbex\Webpay\Helper\Config */
    public $config;

    /**
     * @param Config $config
     */
    public function __construct(\Mobbex\Webpay\Helper\Config $config)
    {
        $this->config = $config;
    }

    /**
     * Add fields to address form in checkout.
     * 
     * Exectued after Magento\Checkout\Block\Checkout\LayoutProcessor::process
     * 
     * @param LayautProcessor $subject
     * @param array $jsLayout
     * 
     * @return array
     */
    public function afterProcess(LayoutProcessor $subject, $jsLayout)
    {
        // Exit if dni field option is disabled
        if (!$this->config->get('own_dni_field') || $this->config->get('dni_column'))
            return $jsLayout;

        // Create DNI field
        $dniFieldCode = 'mbbx_dni';
        $dniField     = [
            'component'   => 'Magento_Ui/js/form/element/abstract',
            'config'      => [
                'customScope' => 'shippingAddress.custom_attributes',
                'customEntry' => null,
                'template'    => 'ui/form/field',
                'elementTmpl' => 'ui/form/element/input',
            ],
            'dataScope'   => "shippingAddress.custom_attributes.$dniFieldCode",
            'label'       => 'DNI',
            'provider'    => 'checkoutProvider',
            'sortOrder'   => 100,
            'validation'  => [
                'required-entry' => true
            ],
            'visible'     => true,
        ];

        // Add fields to layout and return
        $jsLayout['components']['checkout']['children']['steps']['children']['shipping-step']['children']['shippingAddress']['children']['shipping-address-fieldset']['children'][$dniFieldCode] = $dniField;
        return $jsLayout;
    }
}