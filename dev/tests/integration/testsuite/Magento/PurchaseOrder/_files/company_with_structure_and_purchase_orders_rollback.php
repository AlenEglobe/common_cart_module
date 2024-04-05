<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Registry;
use Magento\PurchaseOrder\Api\PurchaseOrderRepositoryInterface;
use Magento\Quote\Model\QuoteRepository;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\OrderRepository;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;

/** @var ObjectManager $objectManager */
$objectManager = Bootstrap::getObjectManager();

/** @var QuoteRepository $quoteRepository */
$quoteRepository = $objectManager->get(QuoteRepository::class);

/** @var PurchaseOrderRepositoryInterface $purchaseOrderRepository */
$purchaseOrderRepository = $objectManager->get(PurchaseOrderRepositoryInterface::class);

/** @var SearchCriteriaBuilder $searchCriteriaBuilder */
$searchCriteriaBuilder = $objectManager->get(SearchCriteriaBuilder::class);

/** @var OrderFactory $orderFactory */
$orderFactory = $objectManager->get(OrderFactory::class);

/** @var OrderRepository $orderRepository */
$orderRepository = $objectManager->get(OrderRepository::class);

/** @var Registry $registry */
$registry = $objectManager->get(Registry::class);
$registry->unregister('isSecureArea');
$registry->register('isSecureArea', true);

// Delete any quotes
$searchCriteria = $searchCriteriaBuilder->create();
$results = $quoteRepository->getList($searchCriteria)->getItems();
foreach ($results as $quote) {
    $quote->delete();
}

try {
    $order = $orderFactory->create()->loadByIncrementId('100000001');
    $orderRepository->delete($order);
} catch (NoSuchEntityException $exception) {
    //Order already removed
}


$registry->unregister('isSecureArea');
$registry->register('isSecureArea', false);


Resolver::getInstance()->requireDataFixture(
    'Magento/Company/_files/company_with_structure_rollback.php'
);
Resolver::getInstance()->requireDataFixture(
    'Magento/Checkout/_files/quote_with_virtual_product_and_address_rollback.php'
);
