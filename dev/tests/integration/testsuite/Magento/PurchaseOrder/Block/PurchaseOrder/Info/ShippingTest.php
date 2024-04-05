<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento\PurchaseOrder\Block\PurchaseOrder\Info;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\PurchaseOrder\Api\Data\PurchaseOrderInterface;
use Magento\PurchaseOrder\Api\PurchaseOrderRepositoryInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;

/**
 * Block test class for purchase order shipping and address information.
 *
 * @see \Magento\PurchaseOrder\Block\PurchaseOrder\Info\Shipping
 *
 * @magentoAppArea frontend
 * @magentoAppIsolation enabled
 */
class ShippingTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var Shipping
     */
    private $shippingBlock;

    /**
     * @var PurchaseOrderRepositoryInterface
     */
    private $purchaseOrderRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        /** @var ObjectManager $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        $this->shippingBlock = $objectManager->get(Shipping::class);
        $this->purchaseOrderRepository = $objectManager->get(PurchaseOrderRepositoryInterface::class);
        $this->searchCriteriaBuilder = $objectManager->get(SearchCriteriaBuilder::class);
    }

    /**
     * Test that the shipping address for the Purchase Order is correctly displayed.
     *
     * @magentoDataFixture Magento/PurchaseOrder/_files/purchase_orders.php
     * @dataProvider expectedShippingAddressDataProvider
     * @param string $incrementId
     * @param string[] $expectedAddress
     */
    public function testGetShippingAddressHtml($incrementId, $expectedAddress)
    {
        // Explicitly set the Purchase Order for the block
        $purchaseOrder = $this->getPurchaseOrderByIncrementId($incrementId);
        $this->shippingBlock->setPurchaseOrderById($purchaseOrder->getEntityId());

        $actualShippingAddressHtml = $this->shippingBlock->getShippingAddressHtml();

        foreach ($expectedAddress as $expectedString) {
            $this->assertStringContainsString($expectedString, $actualShippingAddressHtml);
        }
    }

    /**
     * Test that the billing address for the Purchase Order is correctly displayed.
     *
     * @magentoDataFixture Magento/PurchaseOrder/_files/purchase_orders.php
     * @dataProvider expectedBillingAddressDataProvider
     * @param string $incrementId
     * @param string[] $expectedAddress
     */
    public function testGetBillingAddressHtml($incrementId, $expectedAddress)
    {
        // Explicitly set the Purchase Order for the block
        $purchaseOrder = $this->getPurchaseOrderByIncrementId($incrementId);
        $this->shippingBlock->setPurchaseOrderById($purchaseOrder->getEntityId());

        $actualBillingAddressHtml = $this->shippingBlock->getBillingAddressHtml();

        foreach ($expectedAddress as $expectedString) {
            $this->assertStringContainsString($expectedString, $actualBillingAddressHtml);
        }
    }

    /**
     * Get the Purchase Order created by the fixture.
     *
     * @param string $incrementId
     * @return PurchaseOrderInterface
     */
    private function getPurchaseOrderByIncrementId($incrementId)
    {
        $searchCriteria =  $this->searchCriteriaBuilder
           ->addFilter(PurchaseOrderInterface::INCREMENT_ID, $incrementId)
           ->create();
        $purchaseOrders = $this->purchaseOrderRepository->getList($searchCriteria)->getItems();

        return reset($purchaseOrders);
    }

    /**
     * @return array
     */
    public function expectedShippingAddressDataProvider()
    {
        return [
            [
                'purchase_order_increment_id' => '900000001',
                'expected_address' => [
                    'customer_name' => 'John Smith',
                    'company_name' => 'CompanyName',
                    'street' => 'Green str, 67',
                    'city_state_zip' => 'CityM,  Alabama, 75477',
                    'country' => 'United States',
                    'telephone' => '3468676'
                ]
            ]
        ];
    }

    /**
     * @return array
     */
    public function expectedBillingAddressDataProvider()
    {
        return [
            [
                'purchase_order_increment_id' => '900000001',
                'expected_address' => [
                    'customer_name' => 'John Smith',
                    'company_name' => 'CompanyName',
                    'street' => 'Green str, 67',
                    'city_state_zip' => 'CityM,  Alabama, 75477',
                    'country' => 'United States',
                    'telephone' => '3468676'
                ]
            ]
        ];
    }
}
