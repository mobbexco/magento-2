<?php

namespace Mobbex\Webpay\Setup;

class UpgradeData implements \Magento\Framework\Setup\UpgradeDataInterface
{
    /** @var \Magento\Eav\Setup\EavSetupFactory */
    public $eavSetupFactory;

    /** @var \Mobbex\Webpay\Model\CustomFieldFactory */
    public $customFieldFactory;

    /** @var \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory */
    public $productCollectionFactory;

    /** @var \Magento\Framework\Setup\ModuleDataSetupInterface */
    public $setup;

    /** @var \Magento\Framework\Setup\ModuleContextInterface */
    public $context;

    /** @var \Magento\Framework\DB\Adapter\AdapterInterface */
    public $connection;

    /** @var \Magento\Eav\Setup\EavSetup */
    public $eavSetup;

    public function __construct(
        \Magento\Eav\Setup\EavSetupFactory $eavSetupFactory,
        \Mobbex\Webpay\Model\CustomFieldFactory $customFieldFactory,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory
    ) {
        $this->eavSetupFactory          = $eavSetupFactory;
        $this->customFieldFactory       = $customFieldFactory;
        $this->productCollectionFactory = $productCollectionFactory;
    }

    /**
     * Upgrade module database data.
     * 
     * @param \Magento\Framework\Setup\ModuleDataSetupInterface $setup
     * @param \Magento\Framework\Setup\ModuleContextInterface $context
     */
    public function upgrade($setup, $context)
    {
        $this->setup      = $setup;
        $this->context    = $context;
        $this->connection = $setup->getConnection();

        // Init setup
        $this->setup->startSetup();
        $this->eavSetup = $this->eavSetupFactory->create(['setup' => $this->setup]);

        //Remove deprecated attributes
        if ($context->getVersion() < '2.1.5')
            $this->removeDeprecatedAttributes();

        $this->addOrderStatus([
            'mobbex_failed' => [
                'label' => 'Fallido (Mobbex)',
            ],
            'mobbex_refunded' => [
                'label' => 'Devuelto (Mobbex)',
            ],
            'mobbex_rejected' => [
                'label' => 'Rechazado (Mobbex)',
            ],
            'mobbex_revision' => [
                'label' => 'En Revisión (Mobbex)',
            ],
            'mobbex_authorized' => [
                'label' => 'Autorizado (Mobbex)',
            ],
        ]);
    }

    /**
     * Remove deprecated 'ahora' plan attributes.
     */
    public function removeDeprecatedAttributes()
    {
        $productEntity = \Magento\Catalog\Model\Product::ENTITY;

        foreach (['ahora_3', 'ahora_6', 'ahora_12', 'ahora_18'] as $planRef) {
            // If attribute exists
            if ($this->eavSetup->getAttribute($productEntity, $planRef, 'attribute_id')) {
                // Get all uses
                foreach ($this->getProductAttributeUses($planRef) as $productId => $product) {
                    $commonPlans = unserialize($this->customFieldFactory->create()->getCustomField($productId, 'product', 'common_plans')) ?: [];

                    // Move value to common_plans array and save
                    $commonPlans[] = $planRef;
                    $this->customFieldFactory->create()->saveCustomField($productId, 'product', 'common_plans', serialize($commonPlans));
                }

                // Remove attribute
                $this->eavSetup->removeAttribute($productEntity, $planRef);
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
        return $this->productCollectionFactory
            ->create()
            ->addAttributeToFilter($attributeCode, '1')
            ->load()
            ->getItems() ?: [];
    }

    /**
     * Try to add a list of order status to database.
     * 
     * @param string[] $status An array of status with the name as key and label as value. Also can send an array with 
     */
    public function addOrderStatus($status)
    {
        foreach ($status as $name => $label) {
            // Only add status if not exists yet
            if (!$this->searchOrderStatus($name, 'sales_order_status'))
                $this->connection->insert($this->setup->getTable('sales_order_status'), [
                    'status' => $name,
                    'label'  => isset($label['label']) ? $label['label'] : $name,
                ]);

            // Only add state reference if not exists yet
            if (!$this->searchOrderStatus($name, 'sales_order_status_state'))
                $this->connection->insert($this->setup->getTable('sales_order_status_state'), [
                    'status'     => $name,
                    'state'      => isset($label['state'])      ? $label['state']      : $name,
                    'is_default' => isset($label['is_default']) ? $label['is_default'] : 1,
                ]);
        }
    }

    /**
     * Search an order status on db.
     * 
     * @param string $name The name of status.
     * @param string $table Table name on db.
     * 
     * @return array 
     */
    public function searchOrderStatus($name, $table)
    {
        return $this->connection->fetchRow(
            $this->connection
                ->select()
                ->from($this->setup->getTable($table))
                ->where('status = ?', $name)
        );
    }
}
