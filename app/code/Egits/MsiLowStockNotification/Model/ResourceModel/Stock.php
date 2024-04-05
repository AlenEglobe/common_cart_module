<?php


namespace Egits\MsiLowStockNotification\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Class Stock is the resource model
 *
 */
class Stock extends AbstractDb
{
    /**
     * Constructor for table resource
     */
    protected function _construct()
    {
        $this->_init('low_stock_sources', 'id');
    }

    /**
     * Method to truncate the table
     *
     * @return bool
     */
    public function flushTable()
    {
        $tableName = $this->getMainTable();

        try {
            $this->getConnection()->truncateTable($tableName);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
