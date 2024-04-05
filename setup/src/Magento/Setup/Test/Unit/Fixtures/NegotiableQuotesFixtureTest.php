<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\Setup\Test\Unit\Fixtures;

use Magento\Authorization\Model\UserContextInterface;
use Magento\Company\Api\CompanyManagementInterface;
use Magento\Company\Api\Data\CompanyInterface;
use Magento\Company\Model\ResourceModel\Company\Collection;
use Magento\Company\Model\ResourceModel\Company\CollectionFactory;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\DB\Statement\Pdo\Mysql;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\NegotiableQuote\Api\Data\HistoryInterface;
use Magento\NegotiableQuote\Api\Data\HistoryInterfaceFactory;
use Magento\NegotiableQuote\Model\NegotiableQuote;
use Magento\NegotiableQuote\Model\NegotiableQuoteFactory;
use Magento\NegotiableQuote\Model\ResourceModel\NegotiableQuote as ResourceModelNegotiableQuote;
use Magento\Quote\Model\ResourceModel\Quote\Collection as QuoteCollection;
use Magento\Setup\Fixtures\FixtureModel;
use Magento\Setup\Fixtures\NegotiableQuotesFixture;
use Magento\Setup\Fixtures\Quote\NegotiableQuoteConfiguration;
use Magento\Setup\Fixtures\Quote\NegotiableQuoteConfigurationFactory;
use Magento\Setup\Fixtures\Quote\QuoteGeneratorFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Magento\Quote\Model\ResourceModel\Quote\CollectionFactory as QuoteCollectionFactory;

/**
 * Test Magento\Setup\Fixtures\NegotiableQuotesFixture class.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class NegotiableQuotesFixtureTest extends TestCase
{
    private const QUOTE_ID = 2;

    private const COMPANY_ID = 11;

    private const CUSTOMER_ID = 1;

    /**
     * @var ResourceModelNegotiableQuote|MockObject
     */
    private $negotiableQuoteResource;

    /**
     * @var NegotiableQuoteFactory|MockObject
     */
    private $negotiableQuoteFactory;

    /**
     * @var QuoteCollection|MockObject
     */
    private $quoteCollectionFactory;

    /**
     * @var Collection|MockObject
     */
    private $companyCollectionFactory;

    /**
     * @var CompanyManagementInterface|MockObject
     */
    private $companyManagement;

    /**
     * @var NegotiableQuoteConfigurationFactory|MockObject
     */
    private $quoteConfigFactory;

    /**
     * @var QuoteGeneratorFactory|MockObject
     */
    private $quoteGeneratorFactory;

    /**
     * @var HistoryInterfaceFactory|MockObject
     */
    private $historyLogFactory;

    /**
     * @var SerializerInterface|MockObject
     */
    private $serializer;

    /**
     * @var FixtureModel|MockObject
     */
    private $fixtureModel;

    /**
     * @var ResourceConnection|MockObject
     */
    private $resources;

    /**
     * @var NegotiableQuotesFixture
     */
    private $fixture;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->negotiableQuoteResource = $this->getMockBuilder(ResourceModelNegotiableQuote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getTable', 'saveNegotiatedQuoteData'])
            ->getMock();
        $this->negotiableQuoteFactory = $this->getMockBuilder(NegotiableQuoteFactory::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['create'])
            ->getMock();
        $this->quoteCollectionFactory = $this->getMockBuilder(QuoteCollectionFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->companyCollectionFactory = $this->getMockBuilder(CollectionFactory::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['create'])
            ->getMock();
        $this->companyManagement = $this
            ->getMockBuilder(CompanyManagementInterface::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
        $this->quoteConfigFactory = $this->getMockBuilder(
            NegotiableQuoteConfigurationFactory::class
        )
            ->disableOriginalConstructor()
            ->getMock();
        $this->quoteGeneratorFactory = $this->getMockBuilder(QuoteGeneratorFactory::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['create'])
            ->addMethods(['generateQuotes'])
            ->getMock();
        $this->historyLogFactory = $this->getMockBuilder(HistoryInterfaceFactory::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['create'])
            ->getMockForAbstractClass();
        $this->serializer = $this->getMockBuilder(SerializerInterface::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
        $this->fixtureModel = $this->getMockBuilder(FixtureModel::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getObjectManager', 'getValue'])
            ->getMock();
        $this->resources = $this->getMockBuilder(ResourceConnection::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getConnection', 'getTableName'])
            ->getMock();

        $objectManagerHelper = new ObjectManager($this);
        $this->fixture = $objectManagerHelper->getObject(
            NegotiableQuotesFixture::class,
            [
                'negotiableQuoteResource' => $this->negotiableQuoteResource,
                'negotiableQuoteFactory' => $this->negotiableQuoteFactory,
                'quoteCollectionFactory' => $this->quoteCollectionFactory,
                'companyCollectionFactory' => $this->companyCollectionFactory,
                'companyManagement' => $this->companyManagement,
                'quoteConfigurationFactory' => $this->quoteConfigFactory,
                'quoteGeneratorFactory' => $this->quoteGeneratorFactory,
                'historyLogFactory' => $this->historyLogFactory,
                'serializer' => $this->serializer,
                'fixtureModel' => $this->fixtureModel,
                'resources' => $this->resources
            ]
        );
    }

    /**
     * Test execute method.
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     *
     * @return void
     */
    public function testExecute(): void
    {
        $connection = $this->mockConnection();
        $select = $this->getMockBuilder(Select::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['where', 'insertFromSelect'])
            ->onlyMethods(['from', 'columns'])
            ->getMock();

        $this->resources->expects($this->atLeastOnce())
            ->method('getConnection')
            ->willReturn($connection);

        $statement = $this->getMockBuilder(Mysql::class)
            ->disableOriginalConstructor()
            ->getMock();

        $connection->expects($this->atLeastOnce())
            ->method('query')
            ->willReturn($statement);

        $statement->expects($this->atLeastOnce())->method('fetchColumn')->willReturn(1);

        $this->fixtureModel->method('getValue')
            ->withConsecutive(['negotiable_quotes', 0], ['customers', 0])
            ->willReturnOnConsecutiveCalls(100, 42);

        $connection->expects($this->atLeastOnce())->method('select')->willReturn($select);
        $select->expects($this->atLeastOnce())->method('from')
            ->withConsecutive(
                ['negotiable_quote',  'COUNT(*)'],
                [['qa' => 'quote_address']],
                ['quote_item'],
                ['quote_item'],
                [['quote_item' => 'quote_item'], []]
            )
            ->willReturnSelf();
        $connection->expects($this->atLeastOnce())
            ->method('fetchOne')
            ->with($select)
            ->willReturn(99);
        $this->initCompanyCollection(1, self::COMPANY_ID);
        $this->prepareQuoteConfiguration();
        $negotiableQuote = $this->prepareNegotiableQuoteMockCommonData();
        $quoteCollection = $this->prepareQuoteCollection(100);
        $quoteCollection->expects($this->atLeastOnce())->method('getConnection')->willReturn($connection);
        $quoteCollection->expects($this->once())->method('getAllIds')->willReturn([self::QUOTE_ID]);
        $select->expects($this->atLeastOnce())
            ->method('where')
            ->withConsecutive(
                ['qa.quote_id IN (?)', [2]],
                ['quote_id=?', 2],
                ['parent_item_id=?', 44],
                ['quote_item.quote_id = 2']
            )
            ->willReturnSelf();
        $select->expects($this->atLeastOnce())
            ->method('columns')
            ->with(
                [
                    'quote_item_id' => 'quote_item.item_id',
                    'original_price' => 'quote_item.base_price',
                    'original_tax_amount' => null,
                    'original_discount_amount' => null
                ]
            )
            ->willReturnSelf();
        $customer = $this->getMockBuilder(CustomerInterface::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
        $customer->expects($this->once())->method('getId')->willReturn(self::CUSTOMER_ID);
        $this->companyManagement->expects($this->once())
            ->method('getAdminByCompanyId')
            ->with(self::COMPANY_ID)
            ->willReturn($customer);
        $this->serializer->expects($this->atLeastOnce())
            ->method('serialize')
            ->willReturnOnConsecutiveCalls('{Serialized Data 1}', '{Serialized Data 2}', '{Serialized Data 3}');
        $negotiableQuote->expects($this->once())
            ->method('setQuoteId')
            ->with(self::QUOTE_ID)
            ->willReturnSelf();

        $negotiableQuote->expects($this->once())
            ->method('setQuoteName')
            ->with('Quote2')
            ->willReturnSelf();
        $negotiableQuote->expects($this->once())
            ->method('setCreatorId')
            ->with(self::CUSTOMER_ID)
            ->willReturnSelf();
        $negotiableQuote->expects($this->atLeastOnce())
            ->method('getQuoteId')
            ->willReturn(self::QUOTE_ID);
        $negotiableQuote->expects($this->atLeastOnce())
            ->method('getStatus')
            ->willReturn('submitted_by_customer');
        $negotiableQuote->expects($this->atLeastOnce())
            ->method('getCreatorId')
            ->willReturn(self::CUSTOMER_ID);
        $this->negotiableQuoteResource->expects($this->once())
            ->method('saveNegotiatedQuoteData')
            ->with($negotiableQuote)
            ->willReturnSelf();
        $select->expects($this->atLeastOnce())
            ->method('insertFromSelect')
            ->with(
                'negotiable_quote_item',
                ['quote_item_id', 'original_price', 'original_tax_amount', 'original_discount_amount']
            )
            ->willReturnSelf();
        $this->populateHistoryLog();

        $this->fixture->execute();
    }

    /**
     * Prepare connection mock objects and expectations.
     *
     * @return MockObject
     */
    private function mockConnection(): MockObject
    {
        $connection = $this->getMockBuilder(AdapterInterface::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
        $this->resources->expects($this->atLeastOnce())
            ->method('getTableName')
            ->withConsecutive(
                ['negotiable_quote'],
                ['quote_address'],
                ['quote_item'],
                ['quote_item'],
                ['quote_item'],
                ['negotiable_quote_item'],
                ['negotiable_quote_history'],
                ['negotiable_quote_grid'],
                ['quote']
            )
            ->willReturnOnConsecutiveCalls(
                'negotiable_quote',
                'quote_address',
                'quote_item',
                'quote_item',
                'quote_item',
                'negotiable_quote_item',
                'negotiable_quote_history',
                'negotiable_quote_grid',
                'quote'
            );
        $connection->expects($this->atLeastOnce())
            ->method('fetchAll')
            ->willReturnOnConsecutiveCalls(
                [['entity_id' => self::QUOTE_ID, 'store_id' => 1, 'status' => 'created']],
                [
                    ['quote_id' => self::QUOTE_ID, 'address_type' => 'billing'],
                    ['quote_id' => self::QUOTE_ID, 'address_type' => 'shipping']
                ],
                [['item_id' => 44, 'base_price' => '99.00', 'product_id' => 144]],
                [[
                    'option_id' => '750001',
                    'item_id' => '750001',
                    'product_id' => 1,
                    'code' => 'info_buyRequest',
                    'value' => '{"product":"1","qty":"1","uenc":"aHR0cDovL21hZ2UyLmNvbS9jYXRlZ29yeS0xLmh0bWw"}'
                ]]
            );
        $connection->expects($this->atLeastOnce())
            ->method('insertOnDuplicate')
            ->withConsecutive(
                [
                    'negotiable_quote_history',
                    $this->getNegotiableQuoteHistorySampleData(),
                    array_keys($this->getNegotiableQuoteHistorySampleData())
                ],
                [
                    'negotiable_quote_grid',
                    $this->getNegotiableQuoteGridSampleData(),
                    array_keys($this->getNegotiableQuoteGridSampleData())
                ],
                [
                    'quote',
                    [$this->getQuoteSampleData()],
                    array_keys($this->getQuoteSampleData())
                ]
            )
            ->willReturn(1);
        $connection->expects($this->atLeastOnce())
            ->method('getTransactionLevel')
            ->willReturnOnConsecutiveCalls(1, 0);
        $connection->expects($this->once())->method('commit')->willReturnSelf();
        $connection->expects($this->once())->method('beginTransaction')->willReturnSelf();

        return $connection;
    }

    /**
     * Prepare history log mock objects and expectations.
     *
     * @return void
     */
    private function populateHistoryLog(): void
    {
        $historyLog = $this->getMockBuilder(HistoryInterface::class)
            ->disableOriginalConstructor()
            ->onlyMethods(
                [
                    'setQuoteId',
                    'setIsSeller',
                    'setAuthorId',
                    'setStatus',
                    'setLogData',
                    'setSnapshotData'
                ]
            )
            ->addMethods(['getData'])
            ->getMockForAbstractClass();
        $historyLog->expects($this->once())
            ->method('setQuoteId')
            ->with(self::QUOTE_ID)
            ->willReturnSelf();
        $historyLog->expects($this->once())
            ->method('setIsSeller')
            ->with(true)
            ->willReturnSelf();
        $historyLog->expects($this->once())
            ->method('setAuthorId')
            ->with(self::CUSTOMER_ID)
            ->willReturnSelf();
        $historyLog->expects($this->once())
            ->method('setStatus')
            ->with('created')
            ->willReturnSelf();
        $historyLog->expects($this->once())
            ->method('setLogData')
            ->with('{Serialized Data 2}')
            ->willReturnSelf();
        $historyLog->expects($this->once())
            ->method('setSnapshotData')
            ->with('{Serialized Data 3}')
            ->willReturnSelf();
        $historyLog->expects($this->once())
            ->method('getData')
            ->willReturn([
                'quote_id' => self::QUOTE_ID,
                'is_seller' => true,
                'author_id' => self::CUSTOMER_ID,
                'status' => 'created',
                'log_data' => '{Serialized Data 1}',
                'snapshot_data' => '{Serialized Data 2}'
            ]);
        $this->historyLogFactory->expects($this->once())
            ->method('create')
            ->willReturn($historyLog);
    }

    /**
     * Prepare company collection mock for negotiable quotes.
     *
     * @param int $count
     * @param int $companyId
     *
     * @return void
     */
    private function initCompanyCollection($count, $companyId): void
    {
        $companyCollection = $this
            ->getMockBuilder(Collection::class)
            ->disableOriginalConstructor()
            ->getMock();
        $companyCollection->expects($this->once())->method('getSize')->willReturn($count);
        $company = $this->getMockBuilder(CompanyInterface::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
        if ($count > 0) {
            $company->expects($this->once())->method('getId')->willReturn($companyId);
            $companyCollection->expects($this->atLeastOnce())
                ->method('getIterator')
                ->willReturn(new \ArrayIterator([$company]));
        }
        $this->companyCollectionFactory->expects($this->once())
            ->method('create')
            ->willReturn($companyCollection);
    }

    /**
     * Prepare quote collection mock for negotiable quotes generation.
     *
     * @param int $collectionSize
     * @return QuoteCollection|MockObject
     */
    private function prepareQuoteCollection($collectionSize): QuoteCollection
    {
        $quoteCollection = $this->getMockBuilder(QuoteCollection::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->quoteCollectionFactory->expects($this->atLeastOnce())
            ->method('create')
            ->willReturn($quoteCollection);
        $resource = $this->getMockBuilder(AbstractDb::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getIdFieldName'])
            ->getMockForAbstractClass();
        $resource->expects($this->once())->method('getIdFieldName')->willReturn('entity_id');
        $select = $this->getMockBuilder(Select::class)
            ->disableOriginalConstructor()
            ->getMock();
        $quoteCollection->expects($this->once())->method('getResource')->willReturn($resource);
        $quoteCollection->expects($this->once())->method('removeAllFieldsFromSelect')->willReturnSelf();
        $quoteCollection->expects($this->atLeastOnce())
            ->method('addFieldToSelect')
            ->withConsecutive([['entity_id', 'store_id']], ['checkout_method', 'guest'])
            ->willReturnSelf();
        $quoteCollection->expects($this->atLeastOnce())
            ->method('getTable')
            ->with('sales_order')
            ->willReturn('sales_order');
        $quoteCollection->expects($this->atLeastOnce())->method('getSelect')->willReturn($select);
        $quoteCollection->expects($this->once())->method('getSize')->willReturn($collectionSize);
        $select->expects($this->once())
            ->method('joinLeft')
            ->with(
                ['order' => 'sales_order'],
                'main_table.entity_id = order.quote_id',
                ''
            )
            ->willReturnSelf();
        $select->expects($this->once())
            ->method('where')
            ->with('order.entity_id is NULL')
            ->willReturnSelf();

        return $quoteCollection;
    }

    /**
     * Prepare quote configuration mock and quote generation mock.
     *
     * @return void
     */
    private function prepareQuoteConfiguration(): void
    {
        $quoteConfig = $this->getMockBuilder(NegotiableQuoteConfiguration::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['load'])
            ->addMethods(['setExistsQuoteQuantity', 'setRequiredQuoteQuantity'])
            ->getMock();
        $this->quoteConfigFactory->expects($this->once())
            ->method('create')
            ->willReturn($quoteConfig);
        $quoteConfig->expects($this->once())
            ->method('load')
            ->willReturnSelf();
        $quoteConfig->expects($this->once())
            ->method('setExistsQuoteQuantity')
            ->willReturnSelf();
        $quoteConfig->expects($this->once())
            ->method('setRequiredQuoteQuantity')
            ->willReturnSelf();
        $this->quoteGeneratorFactory->expects($this->once())
            ->method('create')
            ->with(['config' => $quoteConfig, 'fixtureModel' => $this->fixtureModel])
            ->willReturnSelf();
        $this->quoteGeneratorFactory->expects($this->once())
            ->method('generateQuotes')
            ->willReturnSelf();
    }

    /**
     * Test execute method if companies collection is empty.
     *
     * @return void
     */
    public function testExecuteWithEmptyCompaniesCollection(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('At least one Company entity is required to create Negotiable Quote');

        $connection = $this->getMockBuilder(AdapterInterface::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
        $select = $this->getMockBuilder(Select::class)->disableOriginalConstructor()
            ->onlyMethods(['where', 'insertFromSelect'])
            ->onlyMethods(['from', 'columns'])
            ->getMock();
        $this->resources->expects($this->atLeastOnce())
            ->method('getConnection')
            ->with('checkout')
            ->willReturn($connection);
        $connection->expects($this->atLeastOnce())->method('select')->willReturn($select);
        $this->fixtureModel->expects($this->once())
            ->method('getValue')->with('negotiable_quotes', 0)->willReturn(100);
        $this->initCompanyCollection(0, 0);

        $this->fixture->execute();
    }

    /**
     * Test execute method when there is not enough quotes to convert into negotiable quotes.
     *
     * @return void
     */
    public function testExecuteWithNotEnoughQuotes(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Not enough Quotes to be converted into Negotiable Quotes');

        $connection = $this->getMockBuilder(AdapterInterface::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
        $select = $this->getMockBuilder(Select::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['where', 'insertFromSelect'])
            ->onlyMethods(['from', 'columns'])
            ->getMock();
        $this->resources->expects($this->atLeastOnce())
            ->method('getConnection')
            ->with('checkout')
            ->willReturn($connection);
        $this->fixtureModel->expects($this->once())
            ->method('getValue')->with('negotiable_quotes', 0)->willReturn(100);
        $connection->expects($this->atLeastOnce())->method('select')->willReturn($select);
        $this->resources->expects($this->atLeastOnce())->method('getTableName')
            ->with('negotiable_quote')->willReturn('negotiable_quote');
        $select->expects($this->once())
            ->method('from')
            ->with('negotiable_quote', 'COUNT(*)')
            ->willReturnSelf();

        $connection->expects($this->atLeastOnce())->method('fetchOne')->with($select)->willReturn(99);
        $companyCollection = $this
            ->getMockBuilder(Collection::class)
            ->disableOriginalConstructor()
            ->getMock();
        $companyCollection->expects($this->once())->method('getSize')->willReturn(1);

        $this->companyCollectionFactory->expects($this->once())
            ->method('create')
            ->willReturn($companyCollection);

        $this->prepareNegotiableQuoteMockCommonData();
        $this->prepareQuoteConfiguration();
        $this->prepareQuoteCollection(99);
        $this->fixture->execute();
    }

    /**
     * Prepare Negotiable Quote mock object.
     *
     * @return MockObject
     */
    private function prepareNegotiableQuoteMockCommonData(): MockObject
    {
        $negotiableQuote = $this->getMockBuilder(NegotiableQuote::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->negotiableQuoteFactory->expects($this->once())->method('create')->willReturn($negotiableQuote);

        $negotiableQuote->expects($this->once())
            ->method('setIsRegularQuote')
            ->with(true)
            ->willReturnSelf();

        $negotiableQuote->expects($this->atLeastOnce())->method('setStatus')->willReturnSelf();

        $negotiableQuote->expects($this->once())
            ->method('setHasUnconfirmedChanges')
            ->with(0)
            ->willReturnSelf();

        $negotiableQuote->expects($this->once())
            ->method('setIsCustomerPriceChanged')
            ->with(0)
            ->willReturnSelf();

        $negotiableQuote->expects($this->once())
            ->method('setIsShippingTaxChanged')
            ->with(0)
            ->willReturnSelf();

        $negotiableQuote->expects($this->once())
            ->method('setEmailNotificationStatus')
            ->with(0)
            ->willReturnSelf();

        $negotiableQuote->expects($this->once())
            ->method('setCreatorType')
            ->with(UserContextInterface::USER_TYPE_CUSTOMER)
            ->willReturnSelf();

        $negotiableQuote->expects($this->once())
            ->method('setIsAddressDraft')
            ->with(0)
            ->willReturnSelf();

        return $negotiableQuote;
    }

    /**
     * Prepare sample data for quote.
     *
     * @return array
     */
    private function getQuoteSampleData(): array
    {
        return [
            'entity_id' => self::QUOTE_ID,
            'customer_id' => self::CUSTOMER_ID,
            'checkout_method' => 'customer',
            'customer_is_guest' => false
        ];
    }

    /**
     * Get negotiable quote history data array that should be saved.
     *
     * @return array
     */
    private function getNegotiableQuoteHistorySampleData(): array
    {
        return [
            'quote_id' => self::QUOTE_ID,
            'is_seller' => true,
            'author_id' => self::CUSTOMER_ID,
            'status' => 'created',
            'log_data' => '{Serialized Data 1}',
            'snapshot_data' => '{Serialized Data 2}'
        ];
    }

    /**
     * Get negotiable quote grid data array that should be saved.
     *
     * @return array
     */
    private function getNegotiableQuoteGridSampleData(): array
    {
        return [
            'entity_id' => self::QUOTE_ID,
            'created_at' => '1970-01-01 03:00:00',
            'updated_at' => '1970-01-01 03:00:00',
            'base_grand_total' => 9.9900000000000002,
            'grand_total' => 9.9900000000000002,
            'quote_name' => 'Quote2',
            'status' => 'submitted_by_customer',
            'base_negotiated_grand_total' => 9.9900000000000002,
            'negotiated_grand_total' => 9.9900000000000002,
            'base_currency_code' => 'USD',
            'quote_currency_code' => 'USD',
            'store_id' => 1,
            'rate' => 1,
            'customer_id' => self::CUSTOMER_ID,
            'submitted_by' => 'John Doe',
            'company_id' => self::COMPANY_ID,
            'company_name' => 'Company 11'
        ];
    }

    /**
     * Test getActionTitle method.
     *
     * @return void
     */
    public function testGetActionTitle(): void
    {
        $this->assertEquals('Generating Negotiable Quotes', $this->fixture->getActionTitle());
    }

    /**
     * Test introduceParamLabels method.
     *
     * @return void
     */
    public function testIntroduceParamLabels(): void
    {
        $this->assertEquals(['negotiable_quotes' => 'Negotiable Quotes'], $this->fixture->introduceParamLabels());
    }
}
