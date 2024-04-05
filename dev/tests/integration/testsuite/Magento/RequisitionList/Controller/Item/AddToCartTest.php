<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\RequisitionList\Controller\Item;

use Magento\Customer\Model\CustomerRegistry;
use Magento\Customer\Model\Session;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Message\MessageInterface;
use Magento\Quote\Model\Quote;
use Magento\RequisitionList\Api\Data\RequisitionListInterface;
use Magento\RequisitionList\Api\RequisitionListRepositoryInterface;
use Magento\TestFramework\TestCase\AbstractController;

/**
 * Tests for 'Add Requisition List items to Cart' Controller.
 *
 * @magentoAppArea frontend
 */
class AddToCartTest extends AbstractController
{
    /**
     * @var Session
     */
    private $session;

    /**
     * @var CustomerRegistry
     */
    private $customerRegistry;

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

        $this->session = $this->_objectManager->get(Session::class);
        $this->customerRegistry = $this->_objectManager->get(CustomerRegistry::class);
        $this->requisitionListRepository = $this->_objectManager->get(RequisitionListRepositoryInterface::class);
    }

    /**
     * Check that item from Requisition List is added to cart if it has custom File option.
     *
     * @return void
     * @magentoDataFixture Magento/Customer/_files/customer.php
     * @magentoDataFixture Magento/RequisitionList/_files/list_item_for_product_custom_option_file.php
     * @magentoConfigFixture base_website btob/website_configuration/requisition_list_active 1
     */
    public function testAddProductWithCustomFileOption(): void
    {
        $requisitionList = $this->getRequisitionListByName('list name');
        $requisitionListItem = current($requisitionList->getItems());
        $this->getRequest()->setMethod(HttpRequest::METHOD_POST);
        $this->getRequest()->setPostValue([
            'requisition_id' => $requisitionList->getId(),
            'selected' => $requisitionListItem->getId(),
        ]);
        $customer = $this->customerRegistry->retrieveByEmail('customer@example.com');
        $this->session->setCustomerAsLoggedIn($customer);

        $this->dispatch('/requisition_list/item/addtocart');

        /** @var Quote $quote */
        $quote = $this->_objectManager->create(Quote::class);
        $addedItems = $quote->loadByCustomer($customer)->getAllItems();
        $this->assertCount(1, $addedItems);

        $expectedSku = $requisitionListItem->getSku();
        $actualSku = current($addedItems)->getSku();
        $this->assertEquals($expectedSku, $actualSku);

        $this->assertSessionMessages(
            $this->equalTo([(string)__('You added %1 item(s) to your shopping cart.', count($addedItems))]),
            MessageInterface::TYPE_SUCCESS
        );
    }

    /**
     * Get Requisition List by name.
     *
     * @param string $listName
     * @return RequisitionListInterface
     */
    private function getRequisitionListByName(string $listName): RequisitionListInterface
    {
        /** @var FilterBuilder $filterBuilder */
        $filterBuilder = $this->_objectManager->create(FilterBuilder::class);
        $filter = $filterBuilder->setField(RequisitionListInterface::NAME)->setValue($listName)->create();
        /** @var SearchCriteriaBuilder $searchCriteriaBuilder */
        $searchCriteriaBuilder = $this->_objectManager->create(SearchCriteriaBuilder::class);
        $searchCriteriaBuilder->addFilters([$filter]);
        /** @var RequisitionListInterface[] $list */
        $list = $this->requisitionListRepository->getList($searchCriteriaBuilder->create())->getItems();

        return array_pop($list);
    }
}
