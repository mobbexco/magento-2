<?php

namespace Mobbex\Webpay\Block;

class Info extends \Magento\Payment\Block\Info
{
    /** @var \Magento\Framework\App\State */
    protected $_state;

    /** @var \Mobbex\Webpay\Model\Transaction */
    public $mobbexTransaction;

    /** @var \Mobbex\Webpay\Model\CustomField */
    public $customField;

    /** @var \Mobbex\Webpay\Helper\Mobbex */
    public $helper;

    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context, 
        \Magento\Framework\App\State $_state,
        \Mobbex\Webpay\Model\TransactionFactory $mobbexTransactionFactory,
        \Mobbex\Webpay\Model\CustomFieldFactory $customFieldFactory,
        \Mobbex\Webpay\Helper\Mobbex $helper,
        array $data = []
    ) {
        parent::__construct($context, $data);

        $this->mobbexTransaction = $mobbexTransactionFactory->create();
        $this->customField       = $customFieldFactory->create();
        $this->helper            = $helper;
        $this->_state            = $_state;
    }

    /**
     * Prepare information specific to current payment method
     *
     * @param null|array $transport
     * 
     * @return \Magento\Framework\DataObject
     */
    protected function _prepareSpecificInformation($transport = null)
    {
        $transport = parent::_prepareSpecificInformation($transport);

        // Get all transaction data
        $order       = $this->getInfo()->getOrder();
        $trx         = $this->mobbexTransaction->getTransactions(['order_id' => $order->getIncrementId(), 'parent' => 1]);
        $childs      = $this->mobbexTransaction->getMobbexChilds($trx) ?: [];
        $refunded    = $this->customField->getCustomField($order->getIncrementId(), 'order', 'total_refunded') ?: '';

        // If there is no transaction, return the transport
        if (empty($trx['payment_id']) || empty($trx['operation_type']) || empty($trx['total']))
            return $transport;

        $table = [
            'Transaction ID'     => $trx['payment_id'],
            'Total'              => "$ $trx[total]",
            'Total Refunded'     => "$ $refunded",
            'Source'             => "$trx[source_name], $trx[source_number]",
            'Source Installment' => "$trx[installment_count] cuota/s de $ $trx[installment_amount] (plan $trx[installment_name])",
            'Entity Name'        => "$trx[entity_name] (UID $trx[entity_uid])",
            'Coupon'             => "https://mobbex.com/console/$trx[entity_uid]/operations/?oid=$trx[payment_id]",
            'Risk Analysis'      => $trx['risk_analysis'],
        ];

        // Add sources from multicard
        if ($trx['operation_type'] == 'payment.multiple-sources') {
            // Hide default source data
            unset($table['Source'], $table['Source Installment']);

            foreach ($childs as $chd) {
                // Use the last 4 digits of the card to idetify it
                $cardId = substr($chd['source_number'], -4);

                $table["Source $cardId"]             = "$chd[source_name], $chd[source_number]";
                $table["Source Installment $cardId"] = "$chd[installment_count] cuota/s de $ $chd[installment_amount] (plan $chd[installment_name])";
            }
        }

        // Add multivendor entities
        if ($trx['operation_type'] == 'payment.multiple-vendor') {
            // Hide default source data
            unset($table['Source'], $table['Source Installment']);

            foreach ($childs as $k => $chd) {
                $id = $k + 1;

                $table["Seller Entity Name ($id)"]        = "$chd[entity_name] (UID $chd[entity_uid])";
                $table["Seller Entity Transaction ($id)"] = $chd['payment_id'];
                $table["Seller Entity Total ($id)"]       = "$ $chd[total]";
                $table["Seller Source ($id)"]             = "$chd[source_name], $chd[source_number]";
                $table["Seller Source Installment ($id)"] = "$chd[installment_count] cuota/s de $ $chd[installment_amount] (plan $chd[installment_name])";
            }
        }

        // Hide some values from frontend
        if ($this->_state->getAreaCode() != 'adminhtml') {
            $table = array_filter($table, function ($key) {
                return strpos($key, 'Entity') === false && !in_array($key, ['Coupon', 'Risk Analysis']);
            }, ARRAY_FILTER_USE_KEY);
        }

        // Hide refunded if is empty
        if (!$refunded)
            unset($table['Total Refunded']);

        // Execute hook to filter data
        $table = $this->helper->executeHook('mobbexOrderPanelInfo', true, $table, $this->getInfo(), $trx, $childs);

        // Create final table (force type and translate)
        $finalTable = [];

        foreach ($table as $column => $value)
            $finalTable[(string) __($column)] = $value;

        return $transport->setData(
            array_merge($finalTable, $transport->getData())
        );
    }
}
