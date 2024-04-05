<?php


namespace Egits\MsiLowStockNotification\Model\ResourceModel\Stock;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Egits\MsiLowStockNotification\Model\Stock as ModelTable;
use Egits\MsiLowStockNotification\Model\ResourceModel\Stock as ResourceModelTable;

class Collection extends AbstractCollection
{
    /**
     * The Constructor method for Collection
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(ModelTable::class, ResourceModelTable::class);
    }
}
