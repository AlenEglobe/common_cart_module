<?php

namespace Egits\MsiLowStockNotification\Api\Data;

interface StockInterface
{
    /**#@+
     * Constants for keys of data array. Identical to the name of the getter in snake case
     */
    public const ROW_ID  = 'id';
    public const CUSTOMER_ID  = 'customer_id';
    public const SKU  = 'sku';
    public const SOURCE_NAME  = 'source_name';
    public const SOURCE_QTY  = 'source_qty';

    /**#@-*/

    //GETTERS

    /**
     * Get The Single row item Id
     *
     * @return int|null
     */
    public function getEntityId();

    /**
     * Get The Customer Id
     *
     * @return int|null
     */
    public function getCustomerId();

    /**
     * Get SKU of the product
     *
     * @return string|null
     */
    public function getProductItemSku();

    /**
     * Function is used to get the source id
     *
     * @return mixed
     */
    public function getSourceName();

    /**
     * Function is used to get the source quantity
     *
     * @return mixed
     */
    public function getSourceQuantity();

    //SETTERS

    /**
     * Set Table single row item Id
     *
     * @param int $id
     * @return $this
     */
    public function setEntityId($id);

    /**
     * Set Customer Id
     *
     * @param int $customerId
     * @return $this
     */
    public function setCustomerId($customerId);

    /**
     * Set the product SKU
     *
     * @param string $sku
     * @return $this
     */
    public function setProductItemSku($sku);

    /**
     * Set Source Name
     *
     * @param string $sourceName
     * @return $this
     */
    public function setSourceName($sourceName);

    /**
     * Set Source Quantity
     *
     * @param int $sourceQty
     * @return $this
     */
    public function setSourceQuantity($sourceQty);
}
