<?php


namespace Egits\MsiLowStockNotification\Model;

use Egits\MsiLowStockNotification\Api\Data\StockInterface;
use Egits\MsiLowStockNotification\Model\ResourceModel\Stock as ResourceModel;
use Magento\Framework\Model\AbstractModel;

class Stock extends AbstractModel implements StockInterface
{

    /**
     * Constructor method for the model class
     *
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(ResourceModel::class);
    }

    /**
     * Method used for fetching the entity id
     *
     * @return mixed
     */
    public function getEntityId()
    {
        return $this->_getData('id');
    }

    /**
     * Method used for fetching the Customer id
     *
     * @return mixed
     */
    public function getCustomerId(): mixed
    {
        return $this->_getData('customer_id');
    }

    /**
     * Method used for fetching the Product SKU
     *
     * @return mixed
     */
    public function getProductItemSku(): mixed
    {
        return $this->_getData('sku');
    }

    /**
     * Method used for fetching the source name
     *
     * @return mixed
     */
    public function getSourceName(): mixed
    {
        return $this->_getData('source_name');
    }

    /**
     * Method used for fetching the source quantity
     *
     * @return mixed
     */
    public function getSourceQuantity(): mixed
    {
        return $this->_getData('source_qty');
    }

    /**
     * Method used to set the entity id
     *
     * @param int $id
     * @return Stock
     */
    public function setEntityId($id): Stock
    {
        return $this->setData('id', $id);
    }

    /**
     * Method used to set the customer id
     *
     * @param int $customerId
     * @return Stock
     */
    public function setCustomerId($customerId): Stock
    {
        return $this->setData('customer_id', $customerId);
    }

    /**
     * Method used to set the product sku
     *
     * @param string $sku
     * @return Stock
     */
    public function setProductItemSku($sku): Stock
    {
        return $this->setData('sku', $sku);
    }

    /**
     * Method used to set the source name
     *
     * @param string $sourceName
     * @return Stock
     */
    public function setSourceName($sourceName): Stock
    {
        return $this->setData('source_name', $sourceName);
    }

    /**
     * Method used to set the source quantity
     *
     * @param int|string|mixed $sourceQty
     * @return Stock
     */
    public function setSourceQuantity($sourceQty): Stock
    {
        return $this->setData('source_qty', $sourceQty);
    }
}
