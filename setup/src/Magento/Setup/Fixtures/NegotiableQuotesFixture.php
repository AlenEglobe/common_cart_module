<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\Setup\Fixtures;

use Magento\NegotiableQuote\Model\ResourceModel\QuoteGrid;
use Magento\NegotiableQuote\Api\Data\NegotiableQuoteInterface;
use Magento\NegotiableQuote\Api\Data\HistoryInterface;

/**
 * Fixture generator for Negotiable Quote entities.
 *
 * Converts already existing Quotes into Negotiable Quotes by assigning customers and companies to Quote.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.TooManyFields)
 */
class NegotiableQuotesFixture extends Fixture
{
    /**
     * Batch size for negotiable quote generation.
     *
     * @var int
     */
    public const BATCH_SIZE = 1000;

    /**
     * @inheritDoc
     */
    protected $priority = 138;

    /**
     * @var \Magento\NegotiableQuote\Model\ResourceModel\NegotiableQuote
     */
    private $negotiableQuoteResource;

    /**
     * @var \Magento\NegotiableQuote\Model\NegotiableQuoteFactory
     */
    private $negotiableQuoteFactory;

    /**
     * @var \Magento\Quote\Model\ResourceModel\Quote\CollectionFactory
     */
    private $quoteCollectionFactory;

    /**
     * @var Quote\QuoteConfigurationFactory
     */
    private $quoteConfigurationFactory;

    /**
     * @var Quote\QuoteGeneratorFactory
     */
    private $quoteGeneratorFactory;

    /**
     * @var \Magento\Company\Model\ResourceModel\Company\CollectionFactory
     */
    private $companyCollectionFactory;

    /**
     * @var \Magento\Company\Api\CompanyManagementInterface
     */
    private $companyManagement;

    /**
     * @var \Magento\NegotiableQuote\Api\Data\HistoryInterfaceFactory
     */
    private $historyLogFactory;

    /**
     * @var \Magento\Framework\Serialize\SerializerInterface
     */
    private $serializer;

    /**
     * @var \Magento\Framework\App\ResourceConnection
     */
    private $resources;

    /**
     * Array of used quote statuses for specific company and store
     *
     * @var int[]
     */
    private $usedCompanyStoreQuoteStatuses = [];

    /**
     * @var \Magento\Framework\DB\Sql\ColumnValueExpressionFactory
     */
    private $expressionFactory;

    /**
     * @var \Magento\Framework\DB\Adapter\AdapterInterface
     */
    private $connectionInstance;

    /**
     * @var string
     */
    private $quoteConnectionName = 'checkout';

    /**
     * Array with quote statuses and corresponding history statuses.
     *
     * @var array
     */
    private $quoteToHistoryStatusAssociation = [
        NegotiableQuoteInterface::STATUS_ORDERED => HistoryInterface::STATUS_UPDATED,
        NegotiableQuoteInterface::STATUS_SUBMITTED_BY_CUSTOMER => HistoryInterface::STATUS_CREATED,
        NegotiableQuoteInterface::STATUS_SUBMITTED_BY_ADMIN => HistoryInterface::STATUS_UPDATED,
    ];

    /**
     * Array with allowed quote statuses.
     *
     * @var array
     */
    private $quoteStatuses = [
        0 => NegotiableQuoteInterface::STATUS_SUBMITTED_BY_CUSTOMER,
        1 => NegotiableQuoteInterface::STATUS_SUBMITTED_BY_ADMIN,
        2 => NegotiableQuoteInterface::STATUS_ORDERED,
    ];

    /**
     * @param \Magento\NegotiableQuote\Model\ResourceModel\NegotiableQuote $negotiableQuoteResource
     * @param \Magento\NegotiableQuote\Model\NegotiableQuoteFactory $negotiableQuoteFactory
     * @param \Magento\Quote\Model\ResourceModel\Quote\CollectionFactory $quoteCollectionFactory
     * @param \Magento\Company\Model\ResourceModel\Company\CollectionFactory $companyCollectionFactory
     * @param \Magento\Company\Api\CompanyManagementInterface $companyManagement
     * @param Quote\NegotiableQuoteConfigurationFactory $quoteConfigFactory
     * @param Quote\QuoteGeneratorFactory $quoteGeneratorFactory
     * @param \Magento\NegotiableQuote\Api\Data\HistoryInterfaceFactory $historyLogFactory
     * @param \Magento\Framework\Serialize\SerializerInterface $serializer
     * @param \Magento\Framework\DB\Sql\ColumnValueExpressionFactory $expressionFactory
     * @param FixtureModel $fixtureModel
     * @param \Magento\Framework\App\ResourceConnection $resources
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        \Magento\NegotiableQuote\Model\ResourceModel\NegotiableQuote $negotiableQuoteResource,
        \Magento\NegotiableQuote\Model\NegotiableQuoteFactory $negotiableQuoteFactory,
        \Magento\Quote\Model\ResourceModel\Quote\CollectionFactory $quoteCollectionFactory,
        \Magento\Company\Model\ResourceModel\Company\CollectionFactory $companyCollectionFactory,
        \Magento\Company\Api\CompanyManagementInterface $companyManagement,
        Quote\NegotiableQuoteConfigurationFactory $quoteConfigFactory,
        Quote\QuoteGeneratorFactory $quoteGeneratorFactory,
        \Magento\NegotiableQuote\Api\Data\HistoryInterfaceFactory $historyLogFactory,
        \Magento\Framework\Serialize\SerializerInterface $serializer,
        \Magento\Framework\DB\Sql\ColumnValueExpressionFactory $expressionFactory,
        FixtureModel $fixtureModel,
        \Magento\Framework\App\ResourceConnection $resources
    ) {
        $this->negotiableQuoteResource = $negotiableQuoteResource;
        $this->negotiableQuoteFactory = $negotiableQuoteFactory;
        $this->quoteCollectionFactory = $quoteCollectionFactory;
        $this->companyCollectionFactory = $companyCollectionFactory;
        $this->companyManagement = $companyManagement;
        $this->quoteConfigurationFactory = $quoteConfigFactory;
        $this->quoteGeneratorFactory = $quoteGeneratorFactory;
        $this->historyLogFactory = $historyLogFactory;
        $this->serializer = $serializer;
        $this->expressionFactory = $expressionFactory;
        $this->resources = $resources;
        parent::__construct($fixtureModel);
    }

    /**
     * @inheritdoc
     *
     * Design of Performance Fixture Generators require generator classes to override Fixture Model's execute method.
     * @return void
     */
    public function execute()
    {
        $this->connectionInstance = $this->resources->getConnection($this->quoteConnectionName);
        $requestedNegotiableQuotes = (int)$this->fixtureModel->getValue('negotiable_quotes', 0);
        $select = $this->connectionInstance->select()
            ->from($this->resources->getTableName('negotiable_quote'), 'COUNT(*)');
        $existingNegotiableQuotes = (int)$this->connectionInstance->fetchOne($select);
        if ($requestedNegotiableQuotes <= 0) {
            return;
        }

        $companyCollection = $this->companyCollectionFactory->create();
        $companiesNumber = $companyCollection->getSize();
        if ($companiesNumber === 0) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('At least one Company entity is required to create Negotiable Quote')
            );
        }

        $config = $this->quoteConfigurationFactory->create(['fixtureModel' => $this->fixtureModel])->load();
        $config->setExistsQuoteQuantity($existingNegotiableQuotes);
        $config->setRequiredQuoteQuantity($requestedNegotiableQuotes);
        $quoteGenerator = $this->quoteGeneratorFactory->create([
            'config' => $config,
            'fixtureModel' => $this->fixtureModel
        ]);
        $quoteGenerator->generateQuotes();
        /** @var \Magento\NegotiableQuote\Model\NegotiableQuote $negotiableQuote */
        $negotiableQuote = $this->prepareNegotiableQuote();
        $quoteCollection = $this->prepareQuoteCollection();
        if ($quoteCollection->getSize() < $requestedNegotiableQuotes) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('Not enough Quotes to be converted into Negotiable Quotes')
            );
        }
        /** @var \InfiniteIterator $companyIds */
        $companyIds = new \InfiniteIterator($companyCollection->getIterator());
        $companyIds->rewind();
        $offset = 0;
        $companyAdminUserIds = [];
        while ($existingNegotiableQuotes <= $requestedNegotiableQuotes) {
            $quoteCollection->getSelect()->limit(self::BATCH_SIZE, $offset);
            $offset += self::BATCH_SIZE;
            $existingNegotiableQuotes += self::BATCH_SIZE;
            $quotes = $quoteCollection->getConnection()->fetchAll($quoteCollection->getSelect());
            $quoteAddresses = $this->getQuoteAddresses($quoteCollection->getAllIds());
            try {
                $quoteData = [];
                foreach ($quotes as $quote) {
                    $companyId = $companyIds->current()->getId();
                    $companyIds->next();
                    if (!isset($companyAdminUserIds[$companyId])) {
                        $customerId = $this->companyManagement->getAdminByCompanyId($companyId)->getId();
                        $companyAdminUserIds[$companyId] = $customerId;
                    }
                    $quoteData[] = [
                        'entity_id' => $quote['entity_id'],
                        'customer_id' => $companyAdminUserIds[$companyId],
                        'checkout_method' => 'customer',
                        'customer_is_guest' => false
                    ];
                    $quote['status'] = $this->getQuoteStatus($companyAdminUserIds[$companyId], $quote['store_id']);
                    $populatedQuoteGridData = $this->retrieveDefaultTemplate(
                        $quote,
                        $companyAdminUserIds,
                        $companyId
                    );
                    $snapshot = $this->getQuoteSnapshot($populatedQuoteGridData, $negotiableQuote, $quoteAddresses);
                    $negotiableQuote->setQuoteId($quote['entity_id'])
                        ->setQuoteName('Quote' . $quote['entity_id'])
                        ->setStatus($quote['status'])
                        ->setCreatorId($companyAdminUserIds[$companyId])
                        ->setSnapshot($this->serializer->serialize($snapshot));
                    $this->negotiableQuoteResource->saveNegotiatedQuoteData($negotiableQuote);
                    $this->saveNegotiatedQuoteItems($negotiableQuote);
                    $this->populateHistoryLogTable($negotiableQuote, $snapshot);
                    $this->populateNegotiableQuoteGridTable($populatedQuoteGridData);
                }
                $this->connectionInstance->insertOnDuplicate(
                    $this->resources->getTableName('quote'),
                    $quoteData,
                    ['entity_id', 'customer_id', 'checkout_method', 'customer_is_guest']
                );
            } catch (\Exception $lastException) {
                $this->rollBackBatch();
                throw $lastException;
            }
            $this->commitAndRenewBatch();
        }
        $this->commitBatch();
        $this->updateOrders();
    }

    /**
     * Retrieve array of quote addresses.
     *
     * @param array $quoteIds
     * @return array
     */
    private function getQuoteAddresses(array $quoteIds)
    {
        $select = $this->connectionInstance
            ->select()
            ->from(['qa' => $this->resources->getTableName('quote_address')])
            ->where('qa.quote_id IN (?)', $quoteIds);
        $quoteAddresses = $this->connectionInstance->fetchAll($select);
        $addresses = [];
        foreach ($quoteAddresses as $address) {
            $addresses[$address['quote_id']][$address['address_type']] = $address;
        }

        return $addresses;
    }

    /**
     * Prepare quote collection for converting to negotiable quotes.
     *
     * @return \Magento\Quote\Model\ResourceModel\Quote\Collection
     */
    private function prepareQuoteCollection()
    {
        $quoteCollection = $this->quoteCollectionFactory->create();
        $quoteIdFieldName = $quoteCollection->getResource()->getIdFieldName();
        $quoteCollection->removeAllFieldsFromSelect();
        $quoteCollection->addFieldToSelect([$quoteIdFieldName, 'store_id']);
        $quoteCollection->addFieldToSelect('checkout_method', 'guest');
        $quoteCollection->getSelect()
            ->joinLeft(
                ['order' => $quoteCollection->getTable('sales_order')],
                'main_table.entity_id = order.quote_id',
                ''
            )
            ->where('order.entity_id is NULL');

        return $quoteCollection;
    }

    /**
     * Prepare negotiable quote.
     *
     * @return \Magento\NegotiableQuote\Model\NegotiableQuote
     */
    private function prepareNegotiableQuote()
    {
        $negotiableQuote = $this->negotiableQuoteFactory->create();
        $negotiableQuote->setIsRegularQuote(true)
            ->setStatus(NegotiableQuoteInterface::STATUS_CREATED)
            ->setHasUnconfirmedChanges(0)
            ->setIsCustomerPriceChanged(0)
            ->setIsShippingTaxChanged(0)
            ->setEmailNotificationStatus(0)
            ->setCreatorType(\Magento\Authorization\Model\UserContextInterface::USER_TYPE_CUSTOMER)
            ->setIsAddressDraft(0);

        return $negotiableQuote;
    }

    /**
     * Save negotiable quote items for negotiable quote.
     *
     * @param NegotiableQuoteInterface $negotiableQuote
     * @return void
     */
    private function saveNegotiatedQuoteItems(NegotiableQuoteInterface $negotiableQuote)
    {
        $columns = [
            'quote_item_id' => 'quote_item.item_id',
            'original_price' => 'quote_item.base_price',
            'original_tax_amount' => $this->expressionFactory->create(['expression' => '0.0000',]),
            'original_discount_amount' => $this->expressionFactory->create(['expression' => '0.0000',]),
        ];
        $select = $this->connectionInstance
            ->select()
            ->from(['quote_item' => $this->resources->getTableName('quote_item')], [])
            ->columns($columns)
            ->where('quote_item.quote_id = ' . $negotiableQuote->getQuoteId());
        $this->connectionInstance->query(
            $select->insertFromSelect(
                $this->resources->getTableName('negotiable_quote_item'),
                array_keys($columns)
            )
        );
    }

    /**
     * Populate negotiable quote grid table with data.
     *
     * @param array $populatedQuoteGridData
     * @return void
     */
    private function populateNegotiableQuoteGridTable(array $populatedQuoteGridData)
    {
        $this->connectionInstance->insertOnDuplicate(
            $this->resources->getTableName(QuoteGrid::QUOTE_GRID_TABLE),
            $populatedQuoteGridData,
            array_keys($populatedQuoteGridData)
        );
    }

    /**
     * Populate history log table data.
     *
     * @param \Magento\NegotiableQuote\Api\Data\NegotiableQuoteInterface $negotiableQuote
     * @param array $snapshotData
     * @return void
     */
    private function populateHistoryLogTable(
        \Magento\NegotiableQuote\Api\Data\NegotiableQuoteInterface$negotiableQuote,
        array $snapshotData
    ) {
        $historyLogStatus = $this->convertQuoteStatusToHistoryStatus(
            $negotiableQuote->getStatus()
        );
        foreach ($snapshotData['items'] as $item) {
            $data['cart'][$item['item_id']] = $item['product_id'];
        }
        $data['comments'] = [];
        $data['status'] = $negotiableQuote->getStatus();

        /** @var \Magento\NegotiableQuote\Model\History $historyLog */
        $historyLog = $this->historyLogFactory->create();
        $historyLog->setQuoteId($negotiableQuote->getQuoteId())
            ->setIsSeller(true)
            ->setAuthorId($negotiableQuote->getCreatorId())
            ->setStatus($historyLogStatus);
        $historyLog->setLogData($this->serializer->serialize($data));
        $historyLog->setSnapshotData($this->serializer->serialize($snapshotData));

        $historyLogData = $historyLog->getData();
        $this->connectionInstance->insertOnDuplicate(
            $this->resources->getTableName(
                \Magento\NegotiableQuote\Model\ResourceModel\History::NEGOTIABLE_QUOTE_HISTORY_TABLE
            ),
            $historyLogData,
            array_keys($historyLogData)
        );
    }

    /**
     * Return history log status according to quote status.
     *
     * @param string $quoteStatus
     * @return string
     */
    private function convertQuoteStatusToHistoryStatus($quoteStatus)
    {
        return $this->quoteToHistoryStatusAssociation[$quoteStatus];
    }

    /**
     * Commit all active transactions at the end of the batch and restart transactions for future use.
     *
     * Many transactions may exist, since generation process creates a transaction per each available DB connection.
     *
     * @return void
     */
    private function commitAndRenewBatch()
    {
        if ($this->connectionInstance->getTransactionLevel() > 0) {
            $this->connectionInstance->commit();
            $this->connectionInstance->beginTransaction();
        }
    }

    /**
     * Commit all active transactions.
     *
     * @return void
     */
    private function commitBatch()
    {
        if ($this->connectionInstance->getTransactionLevel() > 0) {
            $this->connectionInstance->commit();
        }
    }

    /**
     * Commit all active transactions at the end of the batch.
     *
     * Many transactions may exist, since generation process creates a transaction per each available DB connection.
     *
     * @return void
     */
    private function rollbackBatch()
    {
        if ($this->connectionInstance->getTransactionLevel() > 0) {
            $this->connectionInstance->rollBack();
        }
    }

    /**
     * Get quote status by quote id according to required distribution.
     *
     * @param int $customerId
     * @param int $storeId
     * @return string
     */
    private function getQuoteStatus($customerId, $storeId)
    {
        if (!isset($this->usedCompanyStoreQuoteStatuses[$customerId])) {
            $this->usedCompanyStoreQuoteStatuses[$customerId] = [];
        }
        if (!isset($this->usedCompanyStoreQuoteStatuses[$customerId][$storeId])) {
            $statusIndex = 0;
        } else {
            $statusIndex = ($this->usedCompanyStoreQuoteStatuses[$customerId][$storeId] + 1)
                % count($this->quoteStatuses);
        }
        $this->usedCompanyStoreQuoteStatuses[$customerId][$storeId] = $statusIndex;

        return $this->quoteStatuses[$this->usedCompanyStoreQuoteStatuses[$customerId][$storeId]];
    }

    /**
     * Get default data for grid quotes.
     *
     * @param array $quote
     * @param array $companyAdminUserIds
     * @param int $companyId
     * @return array
     */
    private function retrieveDefaultTemplate(array $quote, array $companyAdminUserIds, $companyId)
    {
        return [
            QuoteGrid::QUOTE_ID => $quote['entity_id'],
            QuoteGrid::CREATED_AT => '1970-01-01 03:00:00',
            QuoteGrid::UPDATED_AT => '1970-01-01 03:00:00',
            QuoteGrid::BASE_GRAND_TOTAL => 9.99,
            QuoteGrid::GRAND_TOTAL => 9.99,
            QuoteGrid::QUOTE_NAME => 'Quote' . $quote['entity_id'],
            QuoteGrid::QUOTE_STATUS => $quote['status'],
            QuoteGrid::BASE_NEGOTIATED_GRAND_TOTAL => 9.99,
            QuoteGrid::NEGOTIATED_GRAND_TOTAL => 9.99,
            QuoteGrid::BASE_CURRENCY => 'USD',
            QuoteGrid::CURRENCY => 'USD',
            QuoteGrid::STORE_ID => $quote['store_id'],
            QuoteGrid::RATE => 1,
            QuoteGrid::CUSTOMER_ID => $companyAdminUserIds[$companyId],
            QuoteGrid::SUBMITTED_BY => 'John Doe',
            QuoteGrid::COMPANY_ID => $companyId,
            QuoteGrid::COMPANY_NAME => 'Company ' . $companyId
        ];
    }

    /**
     * @inheritDoc
     */
    public function getActionTitle()
    {
        return 'Generating Negotiable Quotes';
    }

    /**
     * @inheritDoc
     */
    public function introduceParamLabels()
    {
        return ['negotiable_quotes' => 'Negotiable Quotes'];
    }

    /**
     * Prepare snapshot for quote.
     *
     * @param array $quote
     * @param NegotiableQuoteInterface $negotiableQuote
     * @param array $quoteAddresses
     * @return string Serialized quote snapshot
     */
    private function getQuoteSnapshot(array $quote, NegotiableQuoteInterface $negotiableQuote, array $quoteAddresses)
    {
        $snapshot = [];
        $snapshot['quote'] = $quote;
        $snapshot['quote']['base_to_quote_rate'] = '1.0';
        $snapshot['negotiable_quote'] = $negotiableQuote->getData();
        unset($snapshot['negotiable_quote']['snapshot']);
        $snapshot['billing_address'] = $quoteAddresses[$quote['entity_id']]['billing'];
        $snapshot['shipping_address'] = $quoteAddresses[$quote['entity_id']]['shipping'];
        $snapshot['items'] = [];
        $items = $this->connectionInstance->fetchAll(
            $this->connectionInstance
                ->select()
                ->from($this->resources->getTableName('quote_item'))
                ->where('quote_id=?', $quote['entity_id'])
        );
        foreach ($items as $item) {
            $itemData = $item;
            $itemData['negotiable_quote_item'] = [
                'quote_item_id' => $item['item_id'],
                'original_price' => $item['base_price'],
                'original_tax_amount' => '0.0000',
                'original_discount_amount' => '0.0000',
            ];
            $itemOptions = [];
            $options = $this->connectionInstance->fetchAll(
                $this->connectionInstance
                    ->select()
                    ->from($this->resources->getTableName('quote_item'))
                    ->where('parent_item_id=?', $item['item_id'])
            );
            foreach ($options as $option) {
                $itemOption = $option;
                $productData = [];
                $itemOption['product'] = $productData;
                $itemOptions[] = $itemOption;
            }
            $itemData['options'] = $itemOptions;
            $snapshot['items'][] = $itemData;
        }

        return $snapshot;
    }

    /**
     * Update sales_order, quote and quote_address tables with customers data
     */
    private function updateOrders()
    {
        $requiredCustomers =  (int)$this->fixtureModel->getValue('customers', 0);
        if ($requiredCustomers === 0) {
            return;
        }

        $minCustomerId = (int)$this->resources->getConnection()
            ->query("SELECT MIN(`entity_id`) FROM `".
                $this->resources->getTableName('customer_entity') . "`;")
            ->fetchColumn(0);

        //mysql.increment.offset - 1
        $customerIncrement = $minCustomerId > 1 ? --$minCustomerId : 0;

        $query1 = 'UPDATE `%s` o, `%s` q, `%s` qa SET o.customer_id = IF(o.entity_id %% :customers_count = 0, ' .
            ':customers_count_with_increment, o.entity_id %% :customers_count + ' . $customerIncrement . '), ' .
            'o.customer_is_guest = 0, ' .
            'q.customer_id = IF(o.entity_id %% :customers_count = 0, :customers_count_with_increment, ' .
            'o.entity_id %% :customers_count + ' . $customerIncrement . '),' .
            'qa.customer_id = IF(o.entity_id %% :customers_count = 0, ' .
            ':customers_count_with_increment, o.entity_id %% :customers_count + ' . $customerIncrement . '), ' .
            'qa.save_in_address_book = 0 WHERE ' .
            'o.quote_id = q.entity_id AND q.entity_id = qa.quote_id;';
        $query1 = sprintf(
            $query1,
            $this->resources->getTableName('sales_order'),
            $this->resources->getTableName('quote'),
            $this->resources->getTableName('quote_address')
        );

        $query2 = 'UPDATE `%s` q, `%s` qa SET q.customer_id = IF(q.entity_id %% :customers_count = 0, ' .
            ':customers_count_with_increment, q.entity_id %% :customers_count + ' . $customerIncrement . ')' .
            ', qa.customer_id = IF(q.entity_id %% :customers_count = 0, :customers_count_with_increment, ' .
            'q.entity_id %% :customers_count + ' . $customerIncrement . '), '.
            'qa.save_in_address_book = 0 ' .
            'WHERE q.entity_id = qa.quote_id;';
        $query2 = sprintf(
            $query2,
            $this->resources->getTableName('quote'),
            $this->resources->getTableName('quote_address')
        );

        $this->connectionInstance->query(
            $query1,
            [
                'customers_count' => $requiredCustomers,
                'customers_count_with_increment' => $requiredCustomers + $customerIncrement
            ]
        );

        $this->connectionInstance->query(
            $query2,
            [
                'customers_count' => $requiredCustomers,
                'customers_count_with_increment' => $requiredCustomers + $customerIncrement
            ]
        );
    }
}
