<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\Framework\Exception\NoSuchEntityException;

require INTEGRATION_TESTS_DIR . '/testsuite/Magento/Company/_files/company_with_structure_rollback.php';

/** @var \Magento\Framework\Registry $registry */
$registry->unregister('isSecureArea');
$registry->register('isSecureArea', true);

/** @var ProductRepositoryInterface $productRepository */
$productRepository = Bootstrap::getObjectManager()->get(ProductRepositoryInterface::class);
$productSkus = ['virtual_product_po_rule'];
foreach ($productSkus as $sku) {
    try {
        $product = $productRepository->get($sku, false, null, true);
        $productRepository->delete($product);
    } catch (NoSuchEntityException $e) {
        //product already deleted.
    }
}

$registry->unregister('isSecureArea');
$registry->register('isSecureArea', false);
