<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\RequisitionList\Model\RequisitionListItem\Validator;

use Magento\RequisitionList\Model\RequisitionListItem;
use Magento\RequisitionList\Model\ResourceModel\RequisitionList\Item\Collection as RequisitionListItemCollection;
use Magento\TestFramework\Helper\Bootstrap;

/**
 * @magentoDataFixture Magento/Customer/_files/customer.php
 * @magentoDataFixture Magento/RequisitionList/_files/products.php
 * @magentoDataFixture Magento/RequisitionList/_files/list_items_for_search.php
 */
class StockTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var Stock
     */
    private $stockValidator;

    /**
     * @var RequisitionListItem[]
     */
    private $items;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->stockValidator = Bootstrap::getObjectManager()->create(Stock::class);

        $itemCollection = Bootstrap::getObjectManager()->create(RequisitionListItemCollection::class);
        foreach ($itemCollection as $item) {
            $this->items[$item->getSku()] = $item;
        }
    }

    /**
     * @dataProvider validateDataProvider
     * @param string $sku
     * @param array $validationResult
     */
    public function testValidate(string $sku, array $validationResult)
    {
        $errors = $this->stockValidator->validate($this->items[$sku]);
        $this->assertEquals($validationResult, array_keys($errors));
    }

    /**
     * @return array
     */
    public function validateDataProvider(): array
    {
        return [
            'in stock item' => [
                'item 1',
                [],
            ],
            'out of stock item' => [
                'item 2',
                [Stock::ERROR_OUT_OF_STOCK],
            ],
            'in stock item with backorders' => [
                'item 3',
                [],
            ],
            'in stock item with notified backorders' => [
                'item 4',
                [],
            ],
            'low quantity item' => [
                'item 5',
                [Stock::ERROR_LOW_QUANTITY],
            ],
        ];
    }
}
