<?php

namespace Mobbex\Webpay\Setup;

use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\UpgradeDataInterface;

class UpgradeData implements UpgradeDataInterface
{
    public function __construct(
        \Magento\Eav\Setup\EavSetupFactory $eavSetupFactory,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory,
        \Mobbex\Webpay\Model\CustomFieldFactory $customFieldFactory
    ) {
        $this->eavSetupFactory          = $eavSetupFactory;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->customFields             = $customFieldFactory->create();
    }

    /**
     * Remove deprecated saved data. 
     * 
     * @param ModuleDataSetupInterface $setup
     * @param ModuleContextInterface $context
     */
    public function upgrade($setup, $context)
    {
        $setup->startSetup();

        //Remove deprecated attributes
        if ($context->getVersion() < '2.1.5')
            $this->removeDeprecatedAttributes($setup);

        //Install Mobbex Custom Statuses
        if($context->getVersion() < '3.1.0')
            $this->installMobbexOrderStatus($setup);
    }

    /**
     * Remove deprecated attributes.
     * 
     * @param ModuleDataSetupInterface $setup
     */
    public function removeDeprecatedAttributes($setup)
    {
        $eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);
        $productEntity = \Magento\Catalog\Model\Product::ENTITY;
        $ahoraPlanRefs = ['ahora_3', 'ahora_6', 'ahora_12', 'ahora_18'];
        // Remove deprecated 'ahora' plan attributes
        foreach ($ahoraPlanRefs as $planRef) {
            // If attribute exists
            if ($eavSetup->getAttribute($productEntity, $planRef, 'attribute_id')) {
                // Get all uses
                foreach ($this->getProductAttributeUses($planRef) as $productId => $product) {
                    $commonPlans = unserialize($this->customFields->getCustomField($productId, 'product', 'common_plans')) ?: [];
                    // Move value to common_plans array and save
                    $commonPlans[] = $planRef;
                    $this->customFields->saveCustomField($productId, 'product', 'common_plans', serialize($commonPlans));
                }
                // Remove attribute
                $eavSetup->removeAttribute($productEntity, $planRef);
            }
        }
    }

    /**
     * Retrieve all products uses for the given attribute.
     * 
     * @param string $attributeCode
     * 
     * @return Product[] 
     */
    public function getProductAttributeUses($attributeCode)
    {
        $productCollection = $this->productCollectionFactory->create()
            ->addAttributeToFilter($attributeCode, '1')
            ->load();

        return $productCollection->getItems() ?: [];
    }

    /**
     * Install Mobbex custom order states.
     * 
     * @param ModuleDataSetupInterface $setup
     */
    public function installMobbexOrderStatus($setup)
    {
        //Install order status
        $statuses = [
            ['status' => 'mobbex_failed',   'label' => 'Fallido (Mobbex)'],
            ['status' => 'mobbex_refunded', 'label' => 'Devuelto (Mobbex)'],
            ['status' => 'mobbex_rejected', 'label' => 'Rechazado (Mobbex)'],
            ['status' => 'mobbex_revision', 'label' => 'En Revisión (Mobbex)']
        ];

        $setup->getConnection()->insertArray($setup->getTable('sales_order_status'), ['status', 'label'], $statuses);

        //Install order states
        $states = [
            [
                "status"     => "mobbex_failed",
                "state"      => "mobbex_failed",
                "is_default" => 1
            ],
            [
                "status"     => "mobbex_refunded",
                "state"      => "mobbex_refunded",
                "is_default" => 1
            ],
            [
                "status"     => "mobbex_rejected",
                "state"      => "mobbex_rejected",
                "is_default" => 1
            ],
            [
                "status"     => "mobbex_revision",
                "state"      => "mobbex_revision",
                "is_default" => 1
            ]
        ];

        $setup->getConnection()->insertArray($setup->getTable('sales_order_status_state'), ['status', 'state', 'is_default'], $states);

    }
}