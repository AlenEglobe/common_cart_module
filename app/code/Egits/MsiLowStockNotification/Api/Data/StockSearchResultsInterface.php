<?php

namespace Egits\MsiLowStockNotification\Api\Data;

use Magento\Framework\Api\SearchResultsInterface;

interface StockSearchResultsInterface extends SearchResultsInterface
{
    /**
     * Get post list.
     *
     * @return StockInterface[]
     */
    public function getItems(): array;
    /**
     * Set post list.
     *
     * @param StockInterface[] $items
     * @return $this
     */
    public function setItems(array $items);
}
