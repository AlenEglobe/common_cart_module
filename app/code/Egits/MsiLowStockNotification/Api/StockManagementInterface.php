<?php

namespace Egits\MsiLowStockNotification\Api;

interface StockManagementInterface
{

    /**
     * Get wishlist item collection for all wishlists
     *
     * @return \Magento\Wishlist\Model\ResourceModel\Item\Collection
     */
    public function getWishlistCollection();

    /**
     * Get wishlist ID using the customer ID
     *
     * @param int $customerId
     * @return int|null
     */
    public function getWishlistIdByCustomerId($customerId);

    // /**
    //  * Method returns the data inside sources table
    //  *
    //  * @param string $productSku
    //  * @return GetSourceItemsDataBySku
    //  */
    // public function getSourceDataBySku($productSku);

    /**
     * Check if any source quantity is lower than the threshold
     *
     * @param array $wishlistData
     * @return mixed|string|null|array
     */
    public function checkSources($wishlistData);

    /**
     * Returns the product using the product id
     *
     * @param int $productId
     * @return Magento\Catalog\Api\ProductRepositoryInterface
     */
    public function getProductFromProductId($productId);

    /**
     * Get the wishlist model using the wishlist ID
     *
     * @param int $wishlistId
     * @return Magento\Wishlist\Model\Wishlist
     */
    public function getWishlistFromWishlistId($wishlistId);

    /**
     * Get the customer id from the wishlsit table using wishlist id
     *
     * @param int $wishlistId
     * @return int|null
     */
    public function getCustomerIdFromWishlistId($wishlistId);

    /**
     * Get the configuration settings for the data
     *
     * @return array
     */
    public function getModuleConfig();

    /**
     * This method is called to save the low stock sources data to the table
     *
     * @param array $lowStockSources
     */
    public function saveLowStockItemData(array $lowStockSources);

    /**
     * This method returns the extracted low stock values
     *
     * @param array $lowStockSource
     * @return array
     */
    public function extractLowStockSourceValues($lowStockSource);

    /**
     * This method returns the current list of low stock values
     *
     * @return StockCollectionFactory
     */
    public function getLowStockCollection();
}
