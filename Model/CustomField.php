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
        $this->_init(\Mobbex\Webpay\Model\Resource\CustomField::class);
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
     * Saves custom field
     * 
     * @param int $row_id
     * @param string $object
     * @param string $field_name
     * @param string $data
     * 
     * @return boolean
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
}