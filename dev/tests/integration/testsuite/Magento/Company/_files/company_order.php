<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

use Magento\Company\Model\Company;
use Magento\Company\Model\Order as CompanyOrder;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Workaround\Override\Fixture\Resolver as FixtureResolver;

FixtureResolver::getInstance()->requireDataFixture('Magento/Sales/_files/order.php');
FixtureResolver::getInstance()->requireDataFixture('Magento/Company/_files/company.php');

$order = Bootstrap::getObjectManager()->create(OrderInterface::class);
$order->loadByIncrementId('100000001');

$company = Bootstrap::getObjectManager()->create(Company::class);
$company->load('Magento', 'company_name');

$companyOrder = Bootstrap::getObjectManager()->create(CompanyOrder::class);
$companyOrder->setOrderId($order->getEntityId());
$companyOrder->setCompanyId($company->getId());
$companyOrder->setCompanyName('Magento');
$companyOrder->save();
