<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;
use Magento\PurchaseOrder\Api\PurchaseOrderRepositoryInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;

/** @var $objectManager \Magento\TestFramework\ObjectManager */
$objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();

$taxRules = [
    'Test Rule'
];
$taxRates = [
    'us',
    'ca'
];


$taxRuleResource = $objectManager->get(\Magento\Tax\Model\ResourceModel\Calculation\Rule::class);
foreach ($taxRules as $taxRuleCode) {
    $taxRule = $objectManager->create(\Magento\Tax\Model\Calculation\Rule::class);
    $taxRuleResource->load($taxRule, $taxRuleCode, 'code');
    $taxRuleResource->delete($taxRule);
}

/** @var \Magento\Tax\Model\ResourceModel\Calculation\Rate $resourceModel */
$resourceModel = $objectManager->get(\Magento\Tax\Model\ResourceModel\Calculation\Rate::class);

foreach ($taxRates as $taxRate) {
    try {
        /** @var \Magento\Tax\Model\Calculation\Rate $taxRateEntity */
        $taxRateEntity = $objectManager->create(\Magento\Tax\Model\Calculation\Rate::class);
        $resourceModel->load($taxRateEntity, $taxRate, 'code');
        $resourceModel->delete($taxRateEntity);
    } catch (\Magento\Framework\Exception\CouldNotDeleteException $couldNotDeleteException) {
        // It's okay if the entity already wiped from the database
    }
}

/** @var PurchaseOrderRepositoryInterface $purchaseOrderRepository */
$purchaseOrderRepository = $objectManager->get(PurchaseOrderRepositoryInterface::class);
$searchCriteriaBuilder = $objectManager->get(SearchCriteriaBuilder::class);
$searchCriteria = $searchCriteriaBuilder->addFilter('increment_id', '900000001')->create();
/** @var \Magento\PurchaseOrder\Api\Data\PurchaseOrderInterface $purchaseOrder */
$purchaseOrder =current($purchaseOrderRepository->getList($searchCriteria)->getItems());
if ($purchaseOrder) {
    $quoteId = $purchaseOrder->getQuoteId();
    /** @var CartRepositoryInterface $quoteRepository */
    $quoteRepository = $objectManager->get(CartRepositoryInterface::class);
    try {
        $quote = $quoteRepository->get($quoteId);
        $quoteRepository->delete($quote);
    } catch (NoSuchEntityException $exception) {
        // no action
    }
    $purchaseOrderRepository->delete($purchaseOrder);
}

Resolver::getInstance()->requireDataFixture(
    'Magento/Company/_files/company_rollback.php'
);
Resolver::getInstance()->requireDataFixture(
    'Magento/Checkout/_files/quote_with_shipping_method_rollback.php'
);
