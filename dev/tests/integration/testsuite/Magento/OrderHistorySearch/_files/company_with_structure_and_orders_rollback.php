<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

use Magento\Framework\Registry;
use Magento\Sales\Api\Data\OrderInterfaceFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;

$objectManager = Bootstrap::getObjectManager();
/** @var OrderRepositoryInterface $orderRepository */
$orderRepository = $objectManager->get(OrderRepositoryInterface::class);
/** @var OrderInterfaceFactory $orderFactory */
$orderFactory = $objectManager->get(OrderInterfaceFactory::class);

/** @var Registry $registry */
$registry = $objectManager->get(Registry::class);
$registry->unregister('isSecureArea');
$registry->register('isSecureArea', true);

// delete company orders
$orderIncrementIds = ['100000001', '100000002', '100000003'];
foreach ($orderIncrementIds as $orderIncrementId) {
    $order = $orderFactory->create()->loadByIncrementId($orderIncrementId);
    $orderId = $order->getId();
    if ($orderId) {
        $order = $orderRepository->get($orderId);
        $order->getExtensionAttributes()->getCompanyOrderAttributes()->delete();
        $orderRepository->delete($order);
    }
}

$registry->unregister('isSecureArea');
$registry->register('isSecureArea', false);

Resolver::getInstance()->requireDataFixture('Magento/Company/_files/company_with_structure_rollback.php');
Resolver::getInstance()->requireDataFixture('Magento/Sales/_files/order_list_rollback.php');
