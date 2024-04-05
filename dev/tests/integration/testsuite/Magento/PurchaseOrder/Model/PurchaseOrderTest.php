<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\PurchaseOrder\Model;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\ObjectManagerInterface;
use Magento\PurchaseOrder\Api\Data\PurchaseOrderInterface;
use Magento\PurchaseOrder\Api\PurchaseOrderRepositoryInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * Purchase order model test.
 */
class PurchaseOrderTest extends TestCase
{
    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var PurchaseOrderRepositoryInterface
     */
    private $purchaseOrderRepository;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->purchaseOrderRepository = $this->objectManager->get(PurchaseOrderRepositoryInterface::class);
        $this->customerRepository = $this->objectManager->get(CustomerRepositoryInterface::class);
        $this->searchCriteriaBuilder = $this->objectManager->get(SearchCriteriaBuilder::class);
    }

    /**
     * Test create duplicate purchase order for same quote.
     *
     * @magentoDataFixture Magento/PurchaseOrder/_files/purchase_order_using_negotiable_quote.php
     */
    public function testPurchaseOrderDuplicateQuote()
    {
        $this->expectException(\Magento\Framework\Exception\CouldNotSaveException::class);
        $this->expectExceptionMessage('Unique constraint violation found');

        $purchaseOrder = $this->getPurchaseOrderForCustomer('customer@example.com');
        // emulate new object
        $data = $purchaseOrder->getData();
        unset($data['entity_id']);
        $purchaseOrder->setData($data);
        $this->purchaseOrderRepository->save($purchaseOrder);
    }

    /**
     * Get purchase order for the given customer.
     *
     * @param string $customerEmail
     * @return \Magento\PurchaseOrder\Api\Data\PurchaseOrderInterface
     */
    private function getPurchaseOrderForCustomer(string $customerEmail)
    {
        $customer = $this->customerRepository->get($customerEmail);
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter(PurchaseOrderInterface::CREATOR_ID, $customer->getId())
            ->create();
        $purchaseOrders = $this->purchaseOrderRepository->getList($searchCriteria)->getItems();
        return array_shift($purchaseOrders);
    }
}
