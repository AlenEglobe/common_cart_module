<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\RequisitionList\Service\V1;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\AuthenticationException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Reflection\DataObjectProcessor;
use Magento\Framework\Webapi\Rest\Request;
use Magento\Integration\Api\CustomerTokenServiceInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\WebapiAbstract;
use Magento\RequisitionList\Api\RequisitionListRepositoryInterface;
use Magento\RequisitionList\Api\Data\RequisitionListInterface;
use Magento\RequisitionList\Api\Data\RequisitionListItemInterface;
use Magento\RequisitionList\Model\ResourceModel\RequisitionListItem as RequisitionListItemResource;

/**
 * Test requisition list successfully created
 *
 * @magentoAppIsolation enabled
 */
class RequisitionListRepositorySaveTest extends WebapiAbstract
{
    const SERVICE_VERSION = 'V1';
    const SERVICE_NAME = 'requisitionListRequisitionListRepositoryV1';
    const RESOURCE_PATH = '/V1/requisition_lists/';

    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var RequisitionListRepositoryInterface
     */
    private $requisitionListRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var FilterBuilder
     */
    private $filterBuilder;

    /**
     * @var DataObjectProcessor
     */
    private $dataObjectProcessor;

    /**
     * @var CustomerTokenServiceInterface
     */
    private $tokenService;

    /**
     * @var int
     */
    private $listId;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->customerRepository = $this->objectManager->get(
            CustomerRepositoryInterface::class
        );
        $this->requisitionListRepository =  $this->objectManager->get(
            RequisitionListRepositoryInterface::class
        );
        $this->productRepository = $this->objectManager->get(
            ProductRepositoryInterface::class
        );
        $this->searchCriteriaBuilder = $this->objectManager->get(
            SearchCriteriaBuilder::class
        );
        $this->filterBuilder =  $this->objectManager->get(
            FilterBuilder::class
        );
        $this->dataObjectProcessor = Bootstrap::getObjectManager()->create(
            DataObjectProcessor::class
        );
        $this->tokenService = Bootstrap::getObjectManager()->create(
            CustomerTokenServiceInterface::class
        );
    }

    /**
     * @inheritdoc
     */
    protected function tearDown(): void
    {
        try {
            $this->requisitionListRepository->deleteById($this->getListId());
        } catch (\InvalidArgumentException $e) {
        }
        parent::tearDown();
    }

    /**
     * Test requisition list created successfully
     *
     * @return void
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @magentoConfigFixture btob/website_configuration/requisition_list_active 1
     * @magentoApiDataFixture Magento/RequisitionList/_files/customer_for_requisition_list.php
     * @magentoApiDataFixture Magento/RequisitionList/_files/list_two.php
     * @magentoApiDataFixture Magento/Catalog/_files/product_simple.php
     */
    public function testRequisitionListCreateSuccessfully(): void
    {
        $product = $this->productRepository->get('simple');
        $requisitionListId = $this->getRequisitionListByName('list two');
        $this->setListId((int) $requisitionListId);
        $requisitionList = $this->requisitionListRepository->get($this->listId);
        $customer = $this->customerRepository->get('customer@example.com');
        $token = $this->getToken($customer->getEmail(), 'password');
        $serviceInfo = $this->getServiceInfo($token);

        /** @var RequisitionListItemInterface $requisitionListItem */
        $requisitionListItem = $this->objectManager->create(RequisitionListItemInterface::class);
        /** @var RequisitionListItemResource $requisitionListItemResource */
        $requisitionListItemResource = $this->objectManager->create(RequisitionListItemResource::class);

        $requisitionListItem
            ->setRequisitionListId($this->getListId())
            ->setSku($product->getSku())
            ->setStoreId(1)
            ->setQty(1);
        $requisitionListItemResource->save($requisitionListItem);
        $requisitionList->setItems([$requisitionListItem]);

        $requisitionListObject = $this->dataObjectProcessor->buildOutputDataArray(
            $requisitionList,
            RequisitionListInterface::class
        );
        $requestData = ['requisitionList' => $requisitionListObject];
        $this->_webApiCall($serviceInfo, $requestData);

        $requisitionList = $this->requisitionListRepository->get($this->getListId());
        $this->assertNotEmpty($requisitionList);
        $requisitionListItems = $requisitionList->getItems();
        if ($requisitionListItems) {
            $currentItem = current($requisitionListItems);
            $this->assertEquals($product->getSku(), $currentItem->getSku());
            $this->assertEquals(1, $currentItem->getQty());
        }
    }

    /**
     * Get Requisition list id
     *
     * @return int
     */
    public function getListId(): int
    {
        return $this->listId;
    }

    /**
     * Set Requisition list id
     *
     * @param int $id
     * @return void
     */
    private function setListId(int $id): void
    {
        $this->listId = $id;
    }

    /**
     * Get Requisition list id by name
     *
     * @param string $name
     * @return int
     */
    private function getRequisitionListByName(string $name): int
    {
        $filters[] = $this->filterBuilder
            ->setField('name')
            ->setConditionType('eq')
            ->setValue($name)
            ->create();

        $this->searchCriteriaBuilder->addFilters($filters);
        $searchCriteria = $this->searchCriteriaBuilder->create()->setPageSize(1);
        $searchResults = $this->requisitionListRepository->getList($searchCriteria)->getItems();
        $listId = 0;
        foreach ($searchResults as $key => $value) {
            if ($value->getId()) {
                $listId = (int)$key;
            }
        }

        return $listId;
    }

    /**
     * Get access token to Web API for customer
     *
     * @param string $email
     * @param string $password
     * @return string
     * @throws AuthenticationException
     */
    private function getToken(string $email, string $password): string
    {
        return $this->tokenService->createCustomerAccessToken(
            $email,
            $password
        );
    }

    /**
     * Get the service info array
     *
     * @param string $token
     * @return array
     */
    private function getServiceInfo(string $token): array
    {
        return [
            'rest' => [
                'resourcePath' => self::RESOURCE_PATH,
                'httpMethod' => Request::HTTP_METHOD_POST,
                'token' => $token
            ],
            'soap' => [
                'service' => self::SERVICE_NAME,
                'serviceVersion' => self::SERVICE_VERSION,
                'operation' => self::SERVICE_NAME . 'save',
                'token' => $token
            ],
        ];
    }
}
