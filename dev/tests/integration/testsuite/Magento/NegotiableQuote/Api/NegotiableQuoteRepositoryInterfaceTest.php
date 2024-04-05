<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\NegotiableQuote\Api;

use Magento\Company\Api\Data\CompanyInterface;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\NegotiableQuote\Api\Data\NegotiableQuoteInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\User\Model\User;

/**
 * Class NegotiableQuoteRepositoryInterfaceTest.
 */
class NegotiableQuoteRepositoryInterfaceTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var NegotiableQuoteRepositoryInterface
     */
    private $repository;

    protected function setUp(): void
    {
        $this->repository = Bootstrap::getObjectManager()->create(NegotiableQuoteRepositoryInterface::class);
    }

    /**
     * @magentoDataFixture Magento/NegotiableQuote/_files/negotiable_quotes_for_search.php
     */
    public function testGetList()
    {
        /** @var FilterBuilder $filterBuilder */
        $filterBuilder = Bootstrap::getObjectManager()->create(FilterBuilder::class);

        $filter1 = $filterBuilder->setField(NegotiableQuoteInterface::QUOTE_NAME)
            ->setValue('quote 2')
            ->create();
        $filter2 = $filterBuilder->setField(NegotiableQuoteInterface::QUOTE_NAME)
            ->setValue('quote 3')
            ->create();
        $filter3 = $filterBuilder->setField(NegotiableQuoteInterface::QUOTE_NAME)
            ->setValue('quote 4')
            ->create();
        $filter4 = $filterBuilder->setField(NegotiableQuoteInterface::QUOTE_NAME)
            ->setValue('quote 5')
            ->create();
        $filter5 = $filterBuilder->setField(NegotiableQuoteInterface::QUOTE_NAME)
            ->setValue('quote 6')
            ->create();
        $filter6 = $filterBuilder->setField(NegotiableQuoteInterface::IS_REGULAR_QUOTE)
            ->setValue(1)
            ->create();

        /**@var SortOrderBuilder $sortOrderBuilder */
        $sortOrderBuilder = Bootstrap::getObjectManager()->create(SortOrderBuilder::class);

        /** @var SortOrder $sortOrder */
        $sortOrder = $sortOrderBuilder->setField(NegotiableQuoteInterface::SNAPSHOT)
            ->setDirection(SortOrder::SORT_DESC)
            ->create();

        /** @var SearchCriteriaBuilder $searchCriteriaBuilder */
        $searchCriteriaBuilder = Bootstrap::getObjectManager()->create(SearchCriteriaBuilder::class);

        $searchCriteriaBuilder->addFilters([$filter1, $filter2, $filter3, $filter4, $filter5]);
        $searchCriteriaBuilder->addFilters([$filter6]);
        $searchCriteriaBuilder->setSortOrders([$sortOrder]);

        $searchCriteriaBuilder->setPageSize(2);
        $searchCriteriaBuilder->setCurrentPage(2);

        $searchCriteria = $searchCriteriaBuilder->create();

        $searchResult = $this->repository->getList($searchCriteria);

        $this->assertEquals(3, $searchResult->getTotalCount());
        /** @var Quote[] $items */
        $items = array_values($searchResult->getItems());
        $this->assertCount(1, $items);
        $this->assertEquals('quote 3', $items[0]->getExtensionAttributes()->getNegotiableQuote()->getQuoteName());
    }

    /**
     * @magentoAppArea adminhtml
     * @magentoAppIsolation enabled
     * @magentoDataFixture Magento/NegotiableQuote/_files/negotiable_quotes_for_search.php
     */
    public function testGetListForAdmin()
    {
        /** @var FilterBuilder $filterBuilder */
        $filterBuilder = Bootstrap::getObjectManager()->create(FilterBuilder::class);

        $filter1 = $filterBuilder->setField(NegotiableQuoteInterface::QUOTE_NAME)
            ->setValue('quote 1')
            ->create();
        $filter2 = $filterBuilder->setField(NegotiableQuoteInterface::QUOTE_NAME)
            ->setValue('quote 4')
            ->create();
        $filter3 = $filterBuilder->setField(NegotiableQuoteInterface::QUOTE_NAME)
            ->setValue('quote 5')
            ->create();
        $filter4 = $filterBuilder->setField(NegotiableQuoteInterface::QUOTE_NAME)
            ->setValue('quote 6')
            ->create();
        $filter5 = $filterBuilder->setField(NegotiableQuoteInterface::IS_REGULAR_QUOTE)
            ->setValue(1)
            ->create();

        /**@var SortOrderBuilder $sortOrderBuilder */
        $sortOrderBuilder = Bootstrap::getObjectManager()->create(SortOrderBuilder::class);

        $sortOrder = $sortOrderBuilder->setField(NegotiableQuoteInterface::SNAPSHOT)
            ->setDirection(SortOrder::SORT_ASC)
            ->create();

        /** @var SearchCriteriaBuilder $searchCriteriaBuilder */
        $searchCriteriaBuilder = Bootstrap::getObjectManager()->create(SearchCriteriaBuilder::class);

        $searchCriteriaBuilder->addFilters([$filter1, $filter2, $filter3, $filter4]);
        $searchCriteriaBuilder->addFilters([$filter5]);
        $searchCriteriaBuilder->setSortOrders([$sortOrder]);

        $searchCriteriaBuilder->setPageSize(2);
        $searchCriteriaBuilder->setCurrentPage(2);

        $searchCriteria = $searchCriteriaBuilder->create();

        $searchResult = $this->repository->getList($searchCriteria);

        $this->assertEquals(3, $searchResult->getTotalCount());
        /** @var Quote[] $items */
        $items = array_values($searchResult->getItems());
        $this->assertCount(1, $items);
        $this->assertEquals('quote 6', $items[0]->getExtensionAttributes()->getNegotiableQuote()->getQuoteName());
    }

    #[
        DataFixture(\Magento\Catalog\Test\Fixture\Product::class, as: 'product'),
        DataFixture(\Magento\Customer\Test\Fixture\Customer::class, as: 'customer'),
        DataFixture(\Magento\User\Test\Fixture\User::class, as: 'user'),
        DataFixture(
            \Magento\Company\Test\Fixture\Company::class,
            [
                CompanyInterface::SUPER_USER_ID => '$customer.id$',
                CompanyInterface::SALES_REPRESENTATIVE_ID => '$user.id$',
            ],
            'company'
        ),
        DataFixture(
            \Magento\NegotiableQuote\Test\Fixture\DraftByAdminNegotiableQuote::class,
            [
                'quote' => ['customer_id' => '$customer.id$'],
            ],
            'draft_negotiable_quote_1'
        ),
        DataFixture(
            \Magento\NegotiableQuote\Test\Fixture\NegotiableQuote::class,
            [
                'quote' => [
                    'customer_id' => '$customer.id$',
                    'items' => [
                        ['sku' => '$product.sku$', 'qty' => 1]
                    ]
                ],
                NegotiableQuoteInterface::QUOTE_NAME => 'nq_one',
                NegotiableQuoteInterface::QUOTE_STATUS => NegotiableQuoteInterface::STATUS_CREATED,
                NegotiableQuoteInterface::NEGOTIATED_PRICE_TYPE =>
                    NegotiableQuoteInterface::NEGOTIATED_PRICE_TYPE_PERCENTAGE_DISCOUNT,
                NegotiableQuoteInterface::NEGOTIATED_PRICE_VALUE => 20,
            ],
            'negotiable_quote_1'
        ),
        DataFixture(
            \Magento\NegotiableQuote\Test\Fixture\NegotiableQuote::class,
            [
                'quote' => [
                    'customer_id' => '$customer.id$',
                    'items' => [
                        ['sku' => '$product.sku$', 'qty' => 1]
                    ]
                ],
                NegotiableQuoteInterface::QUOTE_NAME => 'nq_two',
                NegotiableQuoteInterface::QUOTE_STATUS => NegotiableQuoteInterface::STATUS_SUBMITTED_BY_CUSTOMER,
                NegotiableQuoteInterface::NEGOTIATED_PRICE_TYPE =>
                    NegotiableQuoteInterface::NEGOTIATED_PRICE_TYPE_PERCENTAGE_DISCOUNT,
                NegotiableQuoteInterface::NEGOTIATED_PRICE_VALUE => 20,
            ],
            'negotiable_quote_2'
        ),
        DataFixture(
            \Magento\NegotiableQuote\Test\Fixture\NegotiableQuote::class,
            [
                'quote' => [
                    'customer_id' => '$customer.id$',
                    'items' => [
                        ['sku' => '$product.sku$', 'qty' => 1]
                    ]
                ],
                NegotiableQuoteInterface::QUOTE_NAME => 'nq_three',
                NegotiableQuoteInterface::QUOTE_STATUS => NegotiableQuoteInterface::STATUS_SUBMITTED_BY_ADMIN,
                NegotiableQuoteInterface::NEGOTIATED_PRICE_TYPE =>
                    NegotiableQuoteInterface::NEGOTIATED_PRICE_TYPE_PERCENTAGE_DISCOUNT,
                NegotiableQuoteInterface::NEGOTIATED_PRICE_VALUE => 20,
            ],
            'negotiable_quote_3'
        )
    ]
    public function testGetListByCustomerId()
    {
        /** @var CartInterface[] $items */
        $items = $this->repository
            ->getListByCustomerId(DataFixtureStorageManager::getStorage()->get('customer')->getId());
        $this->assertCount(3, $items);

        $quoteNames = [];
        foreach ($items as $item) {
            $quoteNames[] = $item->getExtensionAttributes()->getNegotiableQuote()->getQuoteName();
        }

        $this->assertCount(3, array_intersect($quoteNames, ['nq_one','nq_two','nq_three']));
    }

    /**
     * @magentoAppArea adminhtml
     * @magentoAppIsolation enabled
     */
    #[
        DataFixture(\Magento\Catalog\Test\Fixture\Product::class, as: 'product'),
        DataFixture(\Magento\Customer\Test\Fixture\Customer::class, as: 'customer'),
        DataFixture(\Magento\User\Test\Fixture\User::class, as: 'user'),
        DataFixture(
            \Magento\Company\Test\Fixture\Company::class,
            [
                CompanyInterface::SUPER_USER_ID => '$customer.id$',
                CompanyInterface::SALES_REPRESENTATIVE_ID => '$user.id$',
            ],
            'company'
        ),
        DataFixture(
            \Magento\NegotiableQuote\Test\Fixture\DraftByAdminNegotiableQuote::class,
            [
                'quote' => ['customer_id' => '$customer.id$'],
                NegotiableQuoteInterface::QUOTE_NAME => 'quote 60',
            ],
            'draft_negotiable_quote_1'
        ),
        DataFixture(
            \Magento\NegotiableQuote\Test\Fixture\DraftByAdminNegotiableQuote::class,
            [
                'quote' => ['customer_id' => '$customer.id$'],
                NegotiableQuoteInterface::QUOTE_NAME => 'quote 70',
            ],
            'draft_negotiable_quote_2'
        ),
        DataFixture(
            \Magento\NegotiableQuote\Test\Fixture\NegotiableQuote::class,
            [
                'quote' => [
                    'customer_id' => '$customer.id$',
                    'items' => [
                        ['sku' => '$product.sku$', 'qty' => 1]
                    ]
                ],
                NegotiableQuoteInterface::QUOTE_NAME => 'nq_one',
                NegotiableQuoteInterface::QUOTE_STATUS => NegotiableQuoteInterface::STATUS_CREATED,
                NegotiableQuoteInterface::NEGOTIATED_PRICE_TYPE =>
                    NegotiableQuoteInterface::NEGOTIATED_PRICE_TYPE_PERCENTAGE_DISCOUNT,
                NegotiableQuoteInterface::NEGOTIATED_PRICE_VALUE => 20,
            ],
            'negotiable_quote_1'
        ),
        DataFixture(
            \Magento\NegotiableQuote\Test\Fixture\NegotiableQuote::class,
            [
                'quote' => [
                    'customer_id' => '$customer.id$',
                    'items' => [
                        ['sku' => '$product.sku$', 'qty' => 1]
                    ]
                ],
                NegotiableQuoteInterface::QUOTE_NAME => 'nq_two',
                NegotiableQuoteInterface::QUOTE_STATUS => NegotiableQuoteInterface::STATUS_SUBMITTED_BY_CUSTOMER,
                NegotiableQuoteInterface::NEGOTIATED_PRICE_TYPE =>
                    NegotiableQuoteInterface::NEGOTIATED_PRICE_TYPE_PERCENTAGE_DISCOUNT,
                NegotiableQuoteInterface::NEGOTIATED_PRICE_VALUE => 20,
            ],
            'negotiable_quote_2'
        ),
        DataFixture(
            \Magento\NegotiableQuote\Test\Fixture\NegotiableQuote::class,
            [
                'quote' => [
                    'customer_id' => '$customer.id$',
                    'items' => [
                        ['sku' => '$product.sku$', 'qty' => 1]
                    ]
                ],
                NegotiableQuoteInterface::QUOTE_NAME => 'nq_three',
                NegotiableQuoteInterface::QUOTE_STATUS => NegotiableQuoteInterface::STATUS_SUBMITTED_BY_ADMIN,
                NegotiableQuoteInterface::NEGOTIATED_PRICE_TYPE =>
                    NegotiableQuoteInterface::NEGOTIATED_PRICE_TYPE_PERCENTAGE_DISCOUNT,
                NegotiableQuoteInterface::NEGOTIATED_PRICE_VALUE => 20,
            ],
            'negotiable_quote_3'
        )
    ]
    public function testGetListByCustomerIdForAdmin()
    {
        /** @var CartInterface[] $items */
        $items = $this->repository
            ->getListByCustomerId(DataFixtureStorageManager::getStorage()->get('customer')->getId());
        $this->assertCount(5, $items);

        $quoteNames = [];
        foreach ($items as $item) {
            $quoteNames[] = $item->getExtensionAttributes()->getNegotiableQuote()->getQuoteName();
        }

        $this->assertCount(5, array_intersect($quoteNames, ['nq_one','nq_two','nq_three', 'quote 60', 'quote 70']));
    }
}
