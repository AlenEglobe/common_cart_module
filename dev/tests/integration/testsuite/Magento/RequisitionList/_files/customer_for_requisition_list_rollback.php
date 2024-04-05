<?php
/**
 * Fixture for Customer List method.
 *
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\TestFramework\Helper\Bootstrap;

/** @var \Magento\Framework\Registry $registry */
$registry = Bootstrap::getObjectManager()->get(\Magento\Framework\Registry::class);
$registry->unregister('isSecureArea');
$registry->register('isSecureArea', true);

/** @var CustomerRepositoryInterface $customerRepository */
$customerRepository = Bootstrap::getObjectManager()->get(CustomerRepositoryInterface::class);
$customerIds = [1,2];
foreach ($customerIds as $id) {
    try {
        $customerRepository->deleteById($id);
    } catch (NoSuchEntityException $e) {
    }
}

$registry->unregister('isSecureArea');
$registry->register('isSecureArea', false);
