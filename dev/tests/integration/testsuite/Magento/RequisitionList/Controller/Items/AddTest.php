<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Magento\RequisitionList\Controller\Items;

use Magento\Catalog\Api\Data\ProductCustomOptionInterfaceFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Api\ExtensibleDataInterface;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\MessageInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\RequisitionList\Api\Data\RequisitionListInterface;
use Magento\RequisitionList\Api\RequisitionListRepositoryInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\TestFramework\App\MutableScopeConfig;
use Magento\TestFramework\TestCase\AbstractController;

/**
 * @magentoAppArea frontend
 * @magentoAppIsolation enabled
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class AddTest extends AbstractController
{
    /**
     * @var Json
     */
    private $jsonHelper;

    /**
     * @var MutableScopeConfig
     */
    private $mutableScopeConfig;

    /**
     * @var FormKey
     */
    private $formKey;

    /**
     * @var AccountManagementInterface
     */
    private $accountManagement;

    /**
     * @var CustomerSession
     */
    private $customerSession;

    /**
     * @var RequisitionListRepositoryInterface
     */
    private $requisitionListRepository;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->jsonHelper = $this->_objectManager->create(Json::class);
        $this->formKey = $this->_objectManager->create(FormKey::class);
        $this->mutableScopeConfig = $this->_objectManager->create(MutableScopeConfig::class);
        $this->accountManagement = $this->_objectManager->create(AccountManagementInterface::class);
        $this->customerSession = $this->_objectManager->create(CustomerSession::class);
        $this->requisitionListRepository = $this->_objectManager->create(
            RequisitionListRepositoryInterface::class
        );
    }

    /**
     * @inheritDoc
     */
    protected function tearDown(): void
    {
        $this->setConfig(false);
        $this->customerSession->logout();
        parent::tearDown();
    }

    /**
     * Set requisition list active status;
     *
     * @param bool $isRequisitionListActive
     */
    private function setConfig($isRequisitionListActive)
    {
        $this->mutableScopeConfig->setValue(
            'btob/website_configuration/company_active',
            1,
            ScopeInterface::SCOPE_STORE
        );
        $this->mutableScopeConfig->setValue(
            'btob/website_configuration/requisition_list_active',
            $isRequisitionListActive ? '1' : '0',
            ScopeInterface::SCOPE_WEBSITE
        );
    }

    /**
     * Test that add product that has configuration exception.
     *
     * @magentoDataFixture Magento/Customer/_files/customer.php
     * @magentoDataFixture Magento/Checkout/_files/quote_with_simple_product_saved.php
     * @magentoDataFixture Magento/RequisitionList/_files/list.php
     * @throws LocalizedException
     */
    public function testAddProductWithConfigurationException()
    {
        $customer = $this->accountManagement->authenticate('customer@example.com', 'password');
        $this->customerSession->loginById($customer->getId());
        $this->setConfig(true);

        $productRepository = $this->_objectManager->create(ProductRepositoryInterface::class);
        $product = $productRepository->get('simple');
        $requisitionList = $this->getRequisitionList('list name');
        $product->setCanSaveCustomOptions(true)
                ->setHasOptions(true);

        $oldOptions = [
            [
                'previous_group' => 'select',
                'title' => 'Test Select',
                'type' => 'drop_down',
                'is_require' => 1,
                'sort_order' => 0,
                'values' => [
                    [
                        'option_type_id' => null,
                        'title' => 'Option 1',
                        'price' => '-3,000.00',
                        'price_type' => 'fixed',
                        'sku' => '3-1-select',
                    ],
                    [
                        'option_type_id' => null,
                        'title' => 'Option 2',
                        'price' => '5,000.00',
                        'price_type' => 'fixed',
                        'sku' => '3-2-select',
                    ],
                ]
            ],
        ];

        $options = [];

        $customOptionFactory = $this->_objectManager->create(ProductCustomOptionInterfaceFactory::class);

        foreach ($oldOptions as $option) {
            $option = $customOptionFactory->create(['data' => $option]);
            $option->setProductSku($product->getSku());
            $options[] = $option;
        }

        $product->setOptions($options);
        $productRepository->save($product);

        $url = 'http://localhost.com/dev';
        $uenc = strtr(base64_encode($url), '+/=', '-_,');
        $selectedOptions = 'uenc=' . $uenc . '&product=' . $product->getId() . '&qty=1';

        $productData = [
            'sku' => $product->getSku(),
            'options' => $selectedOptions,
            'qty' => 3
        ];

        $this->getRequest()->setMethod(Http::METHOD_POST);
        $this->getRequest()->setParam('product_data', $this->jsonHelper->serialize([$productData]));
        $this->getRequest()->setParam("list_id", $requisitionList->getId());
        $this->dispatch('requisition_list/items/add');

        $this->assertSessionMessages(
            $this->equalTo([$this->getMessageText(
                'The product\'s required option(s) weren\'t entered. Make sure the options are entered and try again.'
            )]),
            MessageInterface::TYPE_WARNING
        );

        $this->assertRedirect($this->stringContains('checkout/cart'));
    }

    /**
     * Test that add product that has configuration exception.
     *
     * @magentoDataFixture Magento/Customer/_files/customer.php
     * @magentoDataFixture Magento/GroupedProduct/_files/product_grouped_with_simple.php
     * @magentoDataFixture Magento/RequisitionList/_files/list.php
     *
     * @return void
     */
    public function testAddGroupedProduct(): void
    {
        $customer = $this->accountManagement->authenticate('customer@example.com', 'password');
        $this->customerSession->loginById($customer->getId());
        $this->setConfig(true);

        $productRepository = $this->_objectManager->create(ProductRepositoryInterface::class);
        $product = $productRepository->get('grouped');
        $parentProductId= $product->getId();
        $requisitionList = $this->getRequisitionList('list name');

        $url = 'http://localhost.com/dev';
        $uenc = strtr(base64_encode($url), '+/=', '-_,');
        $selectedOptions = 'uenc=' . $uenc . '&product=' . $parentProductId . '&qty=1';

        $productData = [
            'sku' => $product->getSku(),
            'options' => $selectedOptions,
            'qty' => 1
        ];

        $this->getRequest()->setMethod(Http::METHOD_POST);
        $this->getRequest()->setParam('product_data', $this->jsonHelper->serialize([$productData]));
        $this->getRequest()->setParam("list_id", $requisitionList->getId());
        $this->dispatch('requisition_list/items/add');
        $infoBuyRequest = [
            'info_buyRequest' => [
                'super_product_config' => [
                    'product_type' => 'grouped',
                    'product_id' => $parentProductId
                ],
                'item' => $parentProductId,
            ],
            'product_type' => 'grouped',
        ];

        $items = $requisitionList->getItems();
        foreach ($items as $item) {
            $this->assertEquals($infoBuyRequest, $item->getOptions());
        }
    }

    /**
     * Get Requisition List by name
     *
     * @param string $listName
     * @return \Magento\RequisitionList\Model\RequisitionList
     */
    private function getRequisitionList($listName)
    {
        $filterBuilder = $this->_objectManager->create(FilterBuilder::class);
        $filter = $filterBuilder->setField(RequisitionListInterface::NAME)->setValue($listName)->create();
        $searchCriteriaBuilder = $this->_objectManager->create(SearchCriteriaBuilder::class);
        $searchCriteriaBuilder->addFilters([$filter]);
        $list = $this->requisitionListRepository->getList($searchCriteriaBuilder->create())->getItems();

        return array_pop($list);
    }

    /**
     * @param string $message
     * @return string
     */
    private function getMessageText(string $message): string
    {
        return htmlentities($message, ENT_QUOTES | ENT_SUBSTITUTE);
    }
}
