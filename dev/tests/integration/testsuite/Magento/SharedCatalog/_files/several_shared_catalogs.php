<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

use Magento\Customer\Api\Data\GroupInterfaceFactory;
use Magento\Customer\Model\Group;
use Magento\Customer\Model\ResourceModel\Group\Collection;
use Magento\Customer\Model\ResourceModel\GroupRepository;
use Magento\SharedCatalog\Model\SharedCatalog;
use Magento\SharedCatalog\Model\SharedCatalogFactory;
use Magento\Tax\Model\ClassModel;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Workaround\Override\Fixture\Resolver;

Resolver::getInstance()->requireDataFixture('Magento/SharedCatalog/_files/tax_class.php');
Resolver::getInstance()->requireDataFixture('Magento/SharedCatalog/_files/customer_group.php');

/** @var \Magento\Tax\Model\ResourceModel\TaxClass\Collection $taxClassCollection */
$taxClassCollection = Bootstrap::getObjectManager()
    ->create(\Magento\Tax\Model\ResourceModel\TaxClass\Collection::class);
/** @var ClassModel $taxClass */
$taxClass = $taxClassCollection->getLastItem();
$taxClassId = $taxClass->getId();
/** @var GroupRepository $groupRepository */
$groupRepository = Bootstrap::getObjectManager()
    ->create(GroupRepository::class);
/** @var GroupInterfaceFactory $customerGroup */
$groupFactory = Bootstrap::getObjectManager()->create(GroupInterfaceFactory::class);

$sharedCatalogsData = [
    [
        'name' => 'shared catalog ' . time(),
        'description' => 'shared catalog description MASS',
    ],
    [
        'name' => 'Dedicated catalog ' . time(),
        'description' => 'catalog description MASS',
    ],
    [
        'name' => 'Secret catalog ' . time(),
        'description' => 'catalog description MASS',
    ],
    [
        'name' => 'Latest catalog ' . time(),
        'description' => 'catalog description MASS',
    ]
];

/** @var SharedCatalogFactory $sharedCatalogFactory */
$sharedCatalogFactory = Bootstrap::getObjectManager()->get(SharedCatalogFactory::class);

foreach ($sharedCatalogsData as $sharedCatalogsEntry) {
    $customerGroup = $groupFactory->create(
        [
            'data' => [
                'code' => 'MASS customer group ' . $sharedCatalogsEntry['name'],
                'tax_class_id' => $taxClassId
            ]
        ]
    );
    $customerGroup = $groupRepository->save($customerGroup);

    /** @var $sharedCatalog SharedCatalog */
    $sharedCatalog = $sharedCatalogFactory->create();
    $sharedCatalog->setName($sharedCatalogsEntry['name']);
    $sharedCatalog->setDescription($sharedCatalogsEntry['description']);
    $sharedCatalog->setType(0);
    $sharedCatalog->setCreatedBy(null);
    $sharedCatalog->setTaxClassId($taxClassId);
    $sharedCatalog->setCustomerGroupId($customerGroup->getId());
    $sharedCatalog->setStoreId(0);
    $sharedCatalog->save();
}
