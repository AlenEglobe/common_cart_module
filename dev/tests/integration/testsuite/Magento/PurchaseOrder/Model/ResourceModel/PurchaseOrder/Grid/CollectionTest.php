<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Magento\PurchaseOrder\Model\ResourceModel\PurchaseOrder\Grid;

use Magento\Company\Api\CompanyRepositoryInterface;
use Magento\Company\Api\Data\CompanyInterface;
use Magento\Company\Api\Data\PermissionInterface;
use Magento\Company\Api\Data\RoleInterface;
use Magento\Company\Api\RoleRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Session;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\PurchaseOrder\Api\Data\PurchaseOrderInterface;
use Magento\PurchaseOrder\Api\PurchaseOrderRepositoryInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;
use Magento\Translation\Model\ResourceModel\StringUtils;
use Magento\Framework\Translate;
use Magento\Framework\App\Area;

/**
 * Collection test class for the purchase order grid.
 *
 * @see \Magento\PurchaseOrder\Model\ResourceModel\PurchaseOrder\Grid\Collection
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @magentoAppArea frontend
 */
class CollectionTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var CompanyRepositoryInterface
     */
    private $companyRepository;

    /**
     * @var RoleRepositoryInterface
     */
    private $roleRepository;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var PurchaseOrderRepositoryInterface
     */
    private $purchaseOrderRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var Session
     */
    private $session;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        /** @var ObjectManager $objectManager */
        $objectManager = Bootstrap::getObjectManager();

        $this->companyRepository = $objectManager->get(CompanyRepositoryInterface::class);
        $this->roleRepository = $objectManager->get(RoleRepositoryInterface::class);
        $this->customerRepository = $objectManager->get(CustomerRepositoryInterface::class);
        $this->purchaseOrderRepository = $objectManager->get(PurchaseOrderRepositoryInterface::class);
        $this->searchCriteriaBuilder = $objectManager->get(SearchCriteriaBuilder::class);
        $this->session = $objectManager->get(Session::class);

        // Grant the company "Default User" role access to the root purchase order resource.
        $this->setCompanyRolePermission(
            'Magento',
            'Default User',
            'Magento_PurchaseOrder::all',
            PermissionInterface::ALLOW_PERMISSION
        );

        $this->setCompanyRolePermission(
            'Magento',
            'Default User',
            'Magento_PurchaseOrder::view_purchase_orders_for_subordinates',
            PermissionInterface::DENY_PERMISSION
        );

        $this->setCompanyRolePermission(
            'Magento',
            'Default User',
            'Magento_PurchaseOrder::view_purchase_orders',
            PermissionInterface::DENY_PERMISSION
        );
    }

    /**
     * Sets the permission value for the specified company role.
     *
     * @param string $companyName
     * @param string $roleName
     * @param string $resourceId
     * @param string $permissionValue
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    private function setCompanyRolePermission(
        string $companyName,
        string $roleName,
        string $resourceId,
        string $permissionValue
    ) {
        // Get the company
        $this->searchCriteriaBuilder->addFilter('company_name', $companyName);
        $searchCriteria = $this->searchCriteriaBuilder->create();
        $results = $this->companyRepository->getList($searchCriteria)->getItems();

        /** @var CompanyInterface $company */
        $company = reset($results);

        // Get the company role
        $this->searchCriteriaBuilder->addFilter('company_id', $company->getId());
        $this->searchCriteriaBuilder->addFilter('role_name', $roleName);
        $searchCriteria = $this->searchCriteriaBuilder->create();
        $results = $this->roleRepository->getList($searchCriteria)->getItems();

        /** @var RoleInterface $role */
        $role = reset($results);

        // For that role, find the specified permission and set it to the desired value
        /** @var PermissionInterface $permission */
        foreach ($role->getPermissions() as $permission) {
            if ($permission->getResourceId() === $resourceId) {
                $permission->setPermission($permissionValue);
                break;
            }
        }

        $this->roleRepository->save($role);
    }

    /**
     * Test that customers within a company hierarchy can view the expected purchase orders in the result set.
     *
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     * @magentoDataFixture Magento/PurchaseOrder/_files/company_with_structure_and_purchase_orders.php
     * @dataProvider viewPurchaseOrderPermissionsDataProvider
     */
    public function testPurchaseOrderResultsWithCompanyHierarchy($permissions)
    {
        foreach ($permissions as $permission) {
            $this->setCompanyRolePermission(
                'Magento',
                'Default User',
                $permission,
                PermissionInterface::ALLOW_PERMISSION
            );
        }
        $expectedPurchaseOrderData = $this->getExpectedPurchaseOrdersData($permissions);

        foreach ($expectedPurchaseOrderData as $scenarioName => $scenarioData) {
            $this->assertCustomerHasExpectedResults(
                $scenarioData,
                $scenarioName
            );
        }
    }

    /**
     * Test that customers within a company hierarchy can view the canceled purchase orders in the result set.
     *
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     * @magentoDataFixture Magento/PurchaseOrder/_files/company_with_structure_and_purchase_orders.php
     * @dataProvider viewPurchaseOrderPermissionsDataProvider
     */
    public function testCanceledPurchaseOrderResultsWithCompanyHierarchy($permissions)
    {
        foreach ($permissions as $permission) {
            $this->setCompanyRolePermission(
                'Magento',
                'Default User',
                $permission,
                PermissionInterface::ALLOW_PERMISSION
            );
        }
        $this->setPurchaseOrderStatus(PurchaseOrderInterface::STATUS_CANCELED);

        $expectedPurchaseOrderData = $this->getExpectedPurchaseOrdersData($permissions);

        foreach ($expectedPurchaseOrderData as $scenarioName => $scenarioData) {
            $this->assertCustomerHasExpectedResults(
                $scenarioData,
                $scenarioName
            );
        }
    }

    public function viewPurchaseOrderPermissionsDataProvider()
    {
        return [
            'no view' => [[]],
            'view only' => [['Magento_PurchaseOrder::view_purchase_orders']],
            'view & subordinates' => [
                [
                    'Magento_PurchaseOrder::view_purchase_orders',
                    'Magento_PurchaseOrder::view_purchase_orders_for_subordinates',
                ]
            ],
            'view & company' => [
                [
                    'Magento_PurchaseOrder::view_purchase_orders',
                    'Magento_PurchaseOrder::view_purchase_orders_for_company'
                ]
            ],
            'view, subordinates, & company' => [
                [
                    'Magento_PurchaseOrder::view_purchase_orders',
                    'Magento_PurchaseOrder::view_purchase_orders_for_subordinates',
                    'Magento_PurchaseOrder::view_purchase_orders_for_company'
                ]
            ],
        ];
    }

    /**
     * Test that a customer who does not have permission to view purchase orders has an empty result set.
     *
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     * @magentoDataFixture Magento/PurchaseOrder/_files/company_with_structure_and_purchase_orders.php
     */
    public function testPurchaseOrderResultsWithoutViewPermission()
    {
        // Deny the company "Default User" role the ability to view purchase orders.
        $this->setCompanyRolePermission(
            'Magento',
            'Default User',
            'Magento_PurchaseOrder::view_purchase_orders',
            PermissionInterface::DENY_PERMISSION
        );

        $defaultUserWithSubordinates = $this->customerRepository->get('veronica.costello@example.com');

        $this->assertCustomerHasExpectedResults(
            [
                'customer_id' => $defaultUserWithSubordinates->getId(),
                'expected_purchase_order_ids' => []
            ]
        );
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     * @magentoDataFixture Magento/PurchaseOrder/_files/company_with_structure_and_purchase_orders.php
     * @magentoDataFixture Magento/Customer/_files/customer.php
     */
    public function testPurchaseOrderResultsWithNonCompanyUser()
    {
        // Allow the company "Default User" role in "Magento" company to view purchase orders.
        $this->setCompanyRolePermission(
            'Magento',
            'Default User',
            'Magento_PurchaseOrder::view_purchase_orders',
            PermissionInterface::ALLOW_PERMISSION
        );

        $nonCompanyUser = $this->customerRepository->get('customer@example.com');

        $this->assertCustomerHasExpectedResults(
            [
                'customer_id' => $nonCompanyUser->getId(),
                'expected_purchase_order_ids' => []
            ]
        );
    }

    /**
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     * @magentoDataFixture Magento/PurchaseOrder/_files/company_with_structure_and_purchase_orders.php
     * @magentoDataFixture Magento/Company/_files/company_with_admin.php
     */
    public function testPurchaseOrderResultsWithOtherCompanyAdmin()
    {
        // Allow the company "Default User" role in "Magento" company to view purchase orders.
        $this->setCompanyRolePermission(
            'Magento',
            'Default User',
            'Magento_PurchaseOrder::view_purchase_orders',
            PermissionInterface::ALLOW_PERMISSION
        );

        $otherCompanyAdmin = $this->customerRepository->get('company-admin@example.com');

        $this->assertCustomerHasExpectedResults(
            [
                'customer_id' => $otherCompanyAdmin->getId(),
                'expected_purchase_order_ids' => []
            ]
        );
    }

    /**
     * Test purchase order collection with different sort order by status and translations
     *
     * @param array $translations purchase order statuses translations
     * @param array $expectedResult expected purchase order statuses sort results
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     * @magentoDataFixture Magento/PurchaseOrder/_files/company_with_structure_and_purchase_orders_with_all_statuses.php
     * @dataProvider statusTranslations
     */
    public function testPurchaseOrderCollectionWithSortOrderByStatusAlphabetical($translations, $expectedResult)
    {
        $this->setTranslations($translations);
        $customer = $this->customerRepository->get('john.doe@example.com');
        $this->session->loginById($customer->getId());
        $ascResults = $this->getCollectionStatusesBySortOrder(Collection::SORT_ORDER_ASC);
        $descResults = $this->getCollectionStatusesBySortOrder(Collection::SORT_ORDER_DESC);
        $this->assertEquals($expectedResult, $ascResults);
        $this->assertEquals(array_reverse($expectedResult), $descResults);
    }

    /**
     * Data Provider for purchase order status translations and expected sort results
     *
     * @return array[]
     */
    public function statusTranslations()
    {
        return [
            [
                'translations' => [
                    'Pending' => 'Pending',
                    'Approval Required'=> 'Approval Required',
                    'Approved' => 'Approved',
                    'Approved - Ordered' => 'Approved - Ordered',
                    'Approved - Order Failed' => 'Approved - Order Failed',
                    'Approved - Pending Payment' => 'Approved - Pending Payment',
                    'Rejected' => 'Rejected',
                    'Canceled' => 'Canceled',

                ],
                'ascExpectedResult' => [
                    PurchaseOrderInterface::STATUS_APPROVAL_REQUIRED,
                    PurchaseOrderInterface::STATUS_APPROVED,
                    PurchaseOrderInterface::STATUS_ORDER_FAILED,
                    PurchaseOrderInterface::STATUS_ORDER_PLACED,
                    PurchaseOrderInterface::STATUS_APPROVED_PENDING_PAYMENT,
                    PurchaseOrderInterface::STATUS_CANCELED,
                    PurchaseOrderInterface::STATUS_PENDING,
                    PurchaseOrderInterface::STATUS_REJECTED,
                ]
            ],
            [
                'translations' => [
                    'Rejected' => 'A Rejected',
                    'Canceled' => 'B Canceled',
                    'Approved - Ordered' => 'C Approved - Ordered',
                    'Approved' => 'D Approved',
                    'Pending' => 'E Pending',
                    'Approved - Order Failed' => 'F Approved - Order Failed',
                    'Approved - Pending Payment' => 'X Approved - Pending Payment',
                    'Approval Required'=> 'Z Approval Required',

                ],
                'ascExpectedResult' => [
                    PurchaseOrderInterface::STATUS_REJECTED,
                    PurchaseOrderInterface::STATUS_CANCELED,
                    PurchaseOrderInterface::STATUS_ORDER_PLACED,
                    PurchaseOrderInterface::STATUS_APPROVED,
                    PurchaseOrderInterface::STATUS_PENDING,
                    PurchaseOrderInterface::STATUS_ORDER_FAILED,
                    PurchaseOrderInterface::STATUS_APPROVED_PENDING_PAYMENT,
                    PurchaseOrderInterface::STATUS_APPROVAL_REQUIRED
                ]
            ],
        ];
    }

    /**
     * Assert that the expected purchase orders are loaded in the collection for the specified customer.
     *
     * @param int[] $scenarioData
     * @param string $scenarioName
     */
    private function assertCustomerHasExpectedResults($scenarioData, $scenarioName = '')
    {
        $customerId = $scenarioData['customer_id'];
        $expectedPurchaseOrderIds = $scenarioData['expected_purchase_order_ids'];
        $this->session->loginById($customerId);

        /** @var Collection $purchaseOrderCollection */
        $purchaseOrderCollection = Bootstrap::getObjectManager()->create(Collection::class);

        $actualPurchaseOrders = $purchaseOrderCollection->load();
        $actualPurchaseOrderIds = array_column($actualPurchaseOrders->toArray()['items'], 'entity_id');

        sort($expectedPurchaseOrderIds, SORT_NUMERIC);
        sort($actualPurchaseOrderIds, SORT_NUMERIC);

        $this->assertEquals(
            $expectedPurchaseOrderIds,
            $actualPurchaseOrderIds,
            $scenarioName
        );

        $this->session->logout();
    }

    /**
     * Get the expected purchase orders for each customer test scenario based on the data fixture.
     *
     * This data provider is not used with an annotation since the customers and purchase orders created by
     * the fixture must be fetched from the database before determining the expected result data.
     *
     * @param string[] $permissions
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    private function getExpectedPurchaseOrdersData($permissions)
    {
        $adminCustomerId = $this->customerRepository->get('john.doe@example.com')->getId();
        $adminCustomerPurchaseOrderIds = $this->getPurchaseOrderIdsByCreatorId($adminCustomerId);

        $levelOneCustomerId = $this->customerRepository->get('veronica.costello@example.com')->getId();
        $levelOneCustomerPurchaseOrderIds = $this->getPurchaseOrderIdsByCreatorId($levelOneCustomerId);

        $levelTwoCustomerId = $this->customerRepository->get('alex.smith@example.com')->getId();
        $levelTwoCustomerPurchaseOrderIds = $this->getPurchaseOrderIdsByCreatorId($levelTwoCustomerId);

        if (!array_diff($permissions, [])) {
            return [
                'customer_with_no_subordinates' => [
                    'customer_id' => $levelTwoCustomerId,
                    'expected_purchase_order_ids' => []
                ],
                'customer_with_direct_subordinates' => [
                    'customer_id' => $levelOneCustomerId,
                    'expected_purchase_order_ids' => []
                ],
                'customer_with_nested_subordinates' => [
                    'customer_id' => $adminCustomerId,
                    'expected_purchase_order_ids' => array_merge(
                        $adminCustomerPurchaseOrderIds,
                        $levelOneCustomerPurchaseOrderIds,
                        $levelTwoCustomerPurchaseOrderIds
                    )
                ]
            ];
        }

        if (!array_diff($permissions, ['Magento_PurchaseOrder::view_purchase_orders'])) {
            return [
                'customer_with_no_subordinates' => [
                    'customer_id' => $levelTwoCustomerId,
                    'expected_purchase_order_ids' => $levelTwoCustomerPurchaseOrderIds
                ],
                'customer_with_direct_subordinates' => [
                    'customer_id' => $levelOneCustomerId,
                    'expected_purchase_order_ids' => $levelOneCustomerPurchaseOrderIds
                ],
                'customer_with_nested_subordinates' => [
                    'customer_id' => $adminCustomerId,
                    'expected_purchase_order_ids' => array_merge(
                        $adminCustomerPurchaseOrderIds,
                        $levelOneCustomerPurchaseOrderIds,
                        $levelTwoCustomerPurchaseOrderIds
                    )
                ]
            ];
        }

        if (!array_diff(
            $permissions,
            [
                'Magento_PurchaseOrder::view_purchase_orders',
                'Magento_PurchaseOrder::view_purchase_orders_for_subordinates'
            ]
        )) {
            return [
                'customer_with_no_subordinates' => [
                    'customer_id' => $levelTwoCustomerId,
                    'expected_purchase_order_ids' => $levelTwoCustomerPurchaseOrderIds
                ],
                'customer_with_direct_subordinates' => [
                    'customer_id' => $levelOneCustomerId,
                    'expected_purchase_order_ids' => array_merge(
                        $levelOneCustomerPurchaseOrderIds,
                        $levelTwoCustomerPurchaseOrderIds
                    )
                ],
                'customer_with_nested_subordinates' => [
                    'customer_id' => $adminCustomerId,
                    'expected_purchase_order_ids' => array_merge(
                        $adminCustomerPurchaseOrderIds,
                        $levelOneCustomerPurchaseOrderIds,
                        $levelTwoCustomerPurchaseOrderIds
                    )
                ]
            ];
        }

        if (!array_diff(
            $permissions,
            [
                'Magento_PurchaseOrder::view_purchase_orders',
                'Magento_PurchaseOrder::view_purchase_orders_for_company'
            ]
        )) {
            return [
                'customer_with_no_subordinates' => [
                    'customer_id' => $levelTwoCustomerId,
                    'expected_purchase_order_ids' => array_merge(
                        $adminCustomerPurchaseOrderIds,
                        $levelOneCustomerPurchaseOrderIds,
                        $levelTwoCustomerPurchaseOrderIds
                    )
                ],
                'customer_with_direct_subordinates' => [
                    'customer_id' => $levelOneCustomerId,
                    'expected_purchase_order_ids' => array_merge(
                        $adminCustomerPurchaseOrderIds,
                        $levelOneCustomerPurchaseOrderIds,
                        $levelTwoCustomerPurchaseOrderIds
                    )
                ],
                'customer_with_nested_subordinates' => [
                    'customer_id' => $adminCustomerId,
                    'expected_purchase_order_ids' => array_merge(
                        $adminCustomerPurchaseOrderIds,
                        $levelOneCustomerPurchaseOrderIds,
                        $levelTwoCustomerPurchaseOrderIds
                    )
                ]
            ];
        }

        if (!array_diff(
            $permissions,
            [
                'Magento_PurchaseOrder::view_purchase_orders',
                'Magento_PurchaseOrder::view_purchase_orders_for_subordinates',
                'Magento_PurchaseOrder::view_purchase_orders_for_company'
            ]
        )) {
            return [
                'customer_with_no_subordinates' => [
                    'customer_id' => $levelTwoCustomerId,
                    'expected_purchase_order_ids' => array_merge(
                        $adminCustomerPurchaseOrderIds,
                        $levelOneCustomerPurchaseOrderIds,
                        $levelTwoCustomerPurchaseOrderIds
                    )
                ],
                'customer_with_direct_subordinates' => [
                    'customer_id' => $levelOneCustomerId,
                    'expected_purchase_order_ids' => array_merge(
                        $adminCustomerPurchaseOrderIds,
                        $levelOneCustomerPurchaseOrderIds,
                        $levelTwoCustomerPurchaseOrderIds
                    )
                ],
                'customer_with_nested_subordinates' => [
                    'customer_id' => $adminCustomerId,
                    'expected_purchase_order_ids' => array_merge(
                        $adminCustomerPurchaseOrderIds,
                        $levelOneCustomerPurchaseOrderIds,
                        $levelTwoCustomerPurchaseOrderIds
                    )
                ]
            ];
        }

        return [];
    }

    /**
     * Get purchase orders created by the data fixture based on the creator id.
     *
     * @param int $customerId
     * @return int[] $purchaseOrderIds
     */
    private function getPurchaseOrderIdsByCreatorId($customerId)
    {
        $this->searchCriteriaBuilder->addFilter('creator_id', $customerId);
        $searchCriteria = $this->searchCriteriaBuilder->create();

        $purchaseOrders = $this->purchaseOrderRepository->getList($searchCriteria)->getItems();
        $purchaseOrderIds = [];

        foreach ($purchaseOrders as $purchaseOrder) {
            /** @var PurchaseOrderInterface $purchaseOrder */
            $purchaseOrderIds[] = $purchaseOrder->getEntityId();
        }

        return $purchaseOrderIds;
    }

    /**
     * Set purchase order status
     *
     * @param string $status
     * @return void
     */
    private function setPurchaseOrderStatus($status)
    {
        $searchCriteria = $this->searchCriteriaBuilder->create();
        $purchaseOrders = $this->purchaseOrderRepository->getList($searchCriteria)->getItems();
        foreach ($purchaseOrders as $purchaseOrder) {
            /** @var PurchaseOrderInterface $purchaseOrder */
            $purchaseOrder->setStatus($status);
            $this->purchaseOrderRepository->save($purchaseOrder);
        }
    }

    /**
     * Get purchase order collection statuses array from collection sorted by status
     *
     * @param string $sortOrder
     * @return array
     */
    private function getCollectionStatusesBySortOrder(string $sortOrder)
    {
        /** @var Collection $purchaseOrderCollection */
        $purchaseOrderCollection = Bootstrap::getObjectManager()->create(Collection::class);
        $purchaseOrderCollection->setOrder(PurchaseOrderInterface::PO_STATUS, $sortOrder);
        $actualPurchaseOrders = $purchaseOrderCollection->load();
        $statuses = [];
        foreach ($actualPurchaseOrders as $order) {
            $statuses[] = $order->getStatus();
        }
        return $statuses;
    }

    /**
     * Set custom translations
     *
     * @param array $translations
     * @return $this
     */
    private function setTranslations(array $translations)
    {
        /** @var StringUtils $stringUtils */
        $stringUtils = Bootstrap::getObjectManager()->create(StringUtils::class);
        foreach ($translations as $string => $translation) {
            $stringUtils->saveTranslate($string, $translation);
        }
        $model = Bootstrap::getObjectManager()->get(Translate::class);
        $model->loadData(Area::AREA_FRONTEND, true);
        return $this;
    }
}
