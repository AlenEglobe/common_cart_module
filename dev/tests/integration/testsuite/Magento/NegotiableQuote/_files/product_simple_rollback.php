<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

\Magento\TestFramework\Helper\Bootstrap::getInstance()->getInstance()->reinitialize();

/** @var \Magento\Framework\Registry $registry */
$registry = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()->get(\Magento\Framework\Registry::class);

$registry->unregister('isSecureArea');
$registry->register('isSecureArea', true);

try {
    /** @var \Magento\Catalog\Api\ProductRepositoryInterface $productRepository */
    $productRepository = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()
        ->get(\Magento\Catalog\Api\ProductRepositoryInterface::class);
    $product = $productRepository->get('simple', false, null, true);
    $productRepository->delete($product);
    $product = $productRepository->get('simple2', false, null, true);
    $productRepository->delete($product);
}
catch (\Exception $e) {}

$registry->unregister('isSecureArea');
$registry->register('isSecureArea', false);
