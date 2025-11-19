<?php

namespace Mobbex\Webpay\Model;

use Magento\Framework\Model\AbstractModel;

/**
 * Class CustomField
 * @package Mobbex\Webpay\Model
 */
class CustomField extends AbstractModel
{
    /**
     * Initialize Model
     */
    protected function _construct()
    {
        $this->_init(\Mobbex\Webpay\Model\Source\CustomField::class);
    }

    /**
     * Get custom field data
     * 
     * @param int $row_id
     * @param string $object
     * @param string $field_name
     * @param string $data
     * @param string $searched_column
     * 
     * @return string
     */
    public function getCustomField($row_id, $object, $field_name, $searched_column = 'data')
    {
        $collection = $this->getCollection()
            ->addFieldToFilter('row_id', $row_id)
            ->addFieldToFilter('object', $object)
            ->addFieldToFilter('field_name', $field_name)
            ->getColumnValues($searched_column);

        return !empty($collection[0]) ? $collection[0] : false;
    }

    /**
     * Search custom field rows. Filter by object, field_name or data.
     * 
     * @param string|null $object
     * @param string|null $field_name
     * @param string|null $data
     * @param int|null $limit Quantity of results. Can be a lot of rows.
     * @param int|null $page Use to paginate results.
     * 
     * @return \Magento\Framework\DataObject[]
     */
    public function searchRows($object = null, $field_name = null, $data = null, $limit = null, $page = null)
    {
        $collection = $this->getCollection();

        if ($object !== null)
            $collection->addFieldToFilter('object', $object);

        if ($field_name !== null)
            $collection->addFieldToFilter('field_name', $field_name);

        if ($data !== null)
            $collection->addFieldToFilter('data', $data);

        if ($limit !== null)
            $collection->setPageSize($limit);

        if ($page !== null && $limit !== null)
            $collection->setCurPage($page);

        return $collection->getItems();
    }

    /**
     * Saves custom field
     * 
     * @param int $row_id
     * @param string $object
     * @param string $field_name
     * @param string $data
     * 
     * @return $this
     */
    public function saveCustomField($row_id, $object, $field_name, $data)
    {
        // Previus record
        $previousId = $this->getCustomField($row_id, $object, $field_name, 'customfield_id');
        
        // Instantiate if record previously exists
        if ($previousId) {
            $this->load($previousId);
        }

        $this->setData('row_id', $row_id);
        $this->setData('object', $object);
        $this->setData('field_name', $field_name);
        $this->setData('data', $data);

        return $this->save();
    }

    /**
     * Deletes a custom field
     * 
     * @param int $row_id
     * @param string $object
     * @param string $field_name
     * 
     * @return bool
     */
    public function deleteCustomField($row_id, $object, $field_name)
    {
        $previousId = $this->getCustomField($row_id, $object, $field_name, 'customfield_id');

        if (!$previousId)
            return false;

        $this->load($previousId);
        $this->delete();

        return true;
    }
}