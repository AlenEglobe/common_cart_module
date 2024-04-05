<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

use Magento\TestFramework\Workaround\Override\Fixture\Resolver;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\Sales\Api\Data\OrderInterfaceFactory;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Registry;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;

Resolver::getInstance()->requireDataFixture(
    'Magento/Checkout/_files/quote_with_virtual_product_and_address_rollback.php'
);
Resolver::getInstance()->requireDataFixture('Magento/Company/_files/company_rollback.php');
Resolver::getInstance()->requireDataFixture('Magento/CatalogRule/_files/catalog_rule_6_off_logged_user_rollback.php');

$objectManager = Bootstrap::getObjectManager();
/** @var Registry $registry */
$registry = $objectManager->get(Registry::class);
/** @var OrderRepositoryInterface $orderRepository */
$orderRepository = $objectManager->get(OrderRepositoryInterface::class);
/** @var CartRepositoryInterface $quoteRepository */
$quoteRepository = $objectManager->get(CartRepositoryInterface::class);
/** @var OrderInterfaceFactory $orderFactory */
$orderFactory = $objectManager->create(OrderInterfaceFactory::class);
/** @var SearchCriteriaBuilder $searchCriteriaBuilder */
$searchCriteriaBuilder = $objectManager->get(SearchCriteriaBuilder::class);

$registry->unregister('isSecureArea');
$registry->register('isSecureArea', true);

try {
    $order = $orderFactory->create()->loadByIncrementId('test_order_with_virtual_product');
    $orderRepository->delete($order);
} catch (NoSuchEntityException $exception) {
    //Order already removed
}

$searchCriteria = $searchCriteriaBuilder->create();
$items = $quoteRepository->getList($searchCriteria)
    ->getItems();
foreach ($items as $item) {
    $quoteRepository->delete($item);
}

$registry->unregister('isSecureArea');
$registry->register('isSecureArea', false);
