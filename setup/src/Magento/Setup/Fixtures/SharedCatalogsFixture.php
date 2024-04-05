<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento\Setup\Fixtures;

use \Magento\Rule\Model\Condition\Sql\Expression;

/**
 * Generates Shared Catalogs fixtures.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class SharedCatalogsFixture extends Fixture
{
    /**
     * Unregistered user role id.
     *
     * @var int
     */
    public const SHARED_CATALOG_UNREGISTERED_USER_ROLE_ID = 0;

    /**
     * @var string
     */
    public const SHARED_CATALOG_PRODUCT_ITEM_TABLE_NAME = 'shared_catalog_product_item';

    /**
     * @var int
     */
    public const DEFAULT_TAX_CLASS_ID = 3;

    /**
     * @var int
     */
    protected $priority = 150;

    /**
     * @var int
     */
    private $percentOfProductsInSharedCatalog = 75;

    /**
     * @var int
     */
    private $percentOfSharedCatalogsWithProducts = 100;

    /**
     * @var \Magento\SharedCatalog\Api\SharedCatalogRepositoryInterface
     */
    private $sharedCatalogRepository;

    /**
     * @var \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory
     */
    private $categoryCollectionFactory;

    /**
     * @var \Magento\Framework\App\ResourceConnection
     */
    private $resourceConnection;

    /**
     * @var \Magento\Framework\DB\Adapter\AdapterInterface
     */
    private $dbConnection;

    /**
     * @var \Magento\Framework\DB\Sql\ColumnValueExpressionFactory
     */
    private $expressionFactory;

    /**
     * @var array
     */
    private $tableCache = [];

    /**
     * @var \Magento\Framework\EntityManager\MetadataPool
     */
    private $metadataPool;

    /**
     * @var \Magento\SharedCatalog\Model\ResourceModel\SharedCatalog\CollectionFactory
     */
    private $sharedCatalogCollectionFactory;

    /**
     * @param FixtureModel $fixtureModel
     * @param \Magento\SharedCatalog\Api\SharedCatalogRepositoryInterface $sharedCatalogRepository
     * @param \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory $categoryCollectionFactory
     * @param \Magento\Framework\App\ResourceConnection $resourceConnection
     * @param \Magento\Framework\DB\Sql\ColumnValueExpressionFactory $expressionFactory
     * @param \Magento\Framework\EntityManager\MetadataPool $metadataPool
     * @param \Magento\SharedCatalog\Model\ResourceModel\SharedCatalog\CollectionFactory $sharedCatalogCollectionFactory
     */
    public function __construct(
        FixtureModel $fixtureModel,
        \Magento\SharedCatalog\Api\SharedCatalogRepositoryInterface $sharedCatalogRepository,
        \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory $categoryCollectionFactory,
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \Magento\Framework\DB\Sql\ColumnValueExpressionFactory $expressionFactory,
        \Magento\Framework\EntityManager\MetadataPool $metadataPool,
        \Magento\SharedCatalog\Model\ResourceModel\SharedCatalog\CollectionFactory $sharedCatalogCollectionFactory
    ) {
        parent::__construct($fixtureModel);

        $this->sharedCatalogRepository = $sharedCatalogRepository;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->resourceConnection = $resourceConnection;
        $this->expressionFactory = $expressionFactory;
        $this->metadataPool = $metadataPool;
        $this->sharedCatalogCollectionFactory = $sharedCatalogCollectionFactory;
    }

    /**
     * Generate shared catalogs and assign products to them.
     *
     * {@inheritdoc}
     */
    public function execute()
    {
        $this->updateExistsSharedCatalog();

        $sharedCatalogsCount = $this->getSharedCatalogsAmount();
        if (!$sharedCatalogsCount) {
            return;
        }
        $customerGroupId = [];
        $adminUserId = $this->getDbConnection()->fetchOne(
            $this->getDbConnection()->select()
                ->from($this->getTable('admin_user'))
                ->columns('user_id')
                ->limit(1)
        );

        for ($sharedCatalogIndex = 1; $sharedCatalogIndex <= $sharedCatalogsCount; $sharedCatalogIndex++) {
            $customerGroupId[] = $this->createSharedCatalog($sharedCatalogIndex, $adminUserId);
        }

        $this->assignProductsToSharedCatalogs($customerGroupId, $this->percentOfProductsInSharedCatalog);
        $this->setSharedCatalogPrices($customerGroupId);
        $this->assignPermissionsToCategories();
        $this->assignCategoriesPermissionsToIndex();

        // forcing mysql to calculate statistic for tier price table
        $this->getDbConnection()->query(
            sprintf('ANALYZE TABLE %s', $this->getTable('catalog_product_entity_tier_price'))
        );
    }

    /**
     * Assign default permissions to shared catalogs.
     *
     * @return void
     */
    private function updateExistsSharedCatalog()
    {
        $scTable = $this->getTable('shared_catalog');
        $scProductItemTable = $this->getTable(
            'shared_catalog_product_item'
        );
        $scPermissionsTable = $this->getTable('sharedcatalog_category_permissions');

        $select = $this->getDbConnection()->select()
            ->from(['sc' => $scTable], [])
            ->joinLeft(
                ['scpi' => $scProductItemTable],
                'scpi.customer_group_id = sc.customer_group_id',
                []
            )
            ->columns(['customer_group_id'])
            ->where('scpi.entity_id IS NULL');
        $customerGroupIds = $this->getDbConnection()->fetchCol($select);
        $select = $this->getDbConnection()->select()
            ->from($scProductItemTable)
            ->columns(['entity_id'])
            ->where('customer_group_id = 0')
            ->limit(1);
        if ($this->getDbConnection()->fetchOne($select) == 0) {
            $customerGroupIds[] = '0';
        }

        $this->assignProductsToSharedCatalogs($customerGroupIds, 100);

        $this->getDbConnection()->update(
            $scPermissionsTable,
            ['permission' => \Magento\CatalogPermissions\Model\Permission::PERMISSION_ALLOW],
            'permission = ' . \Magento\CatalogPermissions\Model\Permission::PERMISSION_DENY
        );
        $this->getDbConnection()->update(
            $scTable = $this->getTable('magento_catalogpermissions'),
            [
                'grant_catalog_category_view' => \Magento\CatalogPermissions\Model\Permission::PERMISSION_ALLOW,
                'grant_catalog_product_price' => \Magento\CatalogPermissions\Model\Permission::PERMISSION_ALLOW,
                'grant_checkout_items' => \Magento\CatalogPermissions\Model\Permission::PERMISSION_ALLOW,
            ],
            'grant_catalog_category_view = ' . \Magento\CatalogPermissions\Model\Permission::PERMISSION_DENY
        );
    }

    /**
     * Get amount of shared catalog to be generated.
     *
     * @return int
     */
    private function getSharedCatalogsAmount()
    {
        $sharedCatalogCollection = $this->sharedCatalogCollectionFactory->create();
        $value = (int) $this->fixtureModel->getValue('shared_catalogs', 0) - ($sharedCatalogCollection->getSize() - 1);

        // minus default shared catalog
        return max(0, $value);
    }

    /**
     * Create shared catalog.
     *
     * Uses $index to generate unique name and description.
     *
     * @param int $index
     * @param int $adminUserId
     * @return \Magento\SharedCatalog\Api\Data\SharedCatalogInterface
     */
    private function createSharedCatalog($index, $adminUserId)
    {
        $this->getDbConnection()->insert(
            $this->getTable('customer_group'),
            [
                'customer_group_code' => 'Shared catalog ' . $index . ' ' . uniqid(),
                'tax_class_id' => self::DEFAULT_TAX_CLASS_ID,
            ]
        );

        $customerGroupId = $this->getDbConnection()
            ->lastInsertId($this->getTable('customer_group'));

        $this->getDbConnection()->insert(
            $this->getTable('shared_catalog'),
            [
                'name' => 'Shared catalog ' . $index . ' ' . uniqid(),
                'description' => 'Shared catalog description ' . $index,
                'type' => \Magento\SharedCatalog\Api\Data\SharedCatalogInterface::TYPE_CUSTOM,
                'created_by' => $adminUserId,
                'created_at' => date(\Magento\Framework\Stdlib\DateTime::DATETIME_PHP_FORMAT),
                'customer_group_id' => $customerGroupId
            ]
        );

        return $customerGroupId;
    }

    /**
     * Assign products to shared catalog.
     *
     * Creates INSERT ... FROM SELECT query
     * where insert is made to table that links shared catalogs and products
     * and select retrieves percent of products that will be assigned to shared catalog
     *
     * @param array $customerGroupIds
     * @param int $percentage
     * @return void
     */
    private function assignProductsToSharedCatalogs(array $customerGroupIds, $percentage)
    {
        $simpleProductsSelect = $this->getTrueSimpleProductsSelect($percentage);
        $nonSimpleProductsSelect = $this->getNonSimpleProductsSelect($percentage);

        $selects = [];

        $customerGroupIds = $this->limitSharedCatalogIds($customerGroupIds);

        foreach ($customerGroupIds as $customerGroupId) {
            $selects[] = new Expression((clone $simpleProductsSelect)
                ->columns(
                    ['customer_group_id' => $this->expressionFactory->create(['expression' => $customerGroupId])]
                ));

            $selects[] = new Expression((clone $nonSimpleProductsSelect)
                ->columns(
                    ['customer_group_id' => $this->expressionFactory->create(['expression' => $customerGroupId])]
                ));
        }

        $generalSelect = $this->getDbConnection()->select()->union(
            $selects,
            \Magento\Framework\DB\Select::SQL_UNION_ALL
        );

        $insert = $this->getDbConnection()
            ->insertFromSelect(
                $generalSelect,
                $this->getTable('shared_catalog_product_item'),
                ['sku', 'customer_group_id']
            );

        $this->getDbConnection()->query($insert);
    }

    /**
     * Retrieve current connection to DB.
     *
     * Method is required to eliminate multiple calls to ResourceConnection class.
     *
     * @return \Magento\Framework\DB\Adapter\AdapterInterface
     */
    private function getDbConnection()
    {
        if ($this->dbConnection === null) {
            $this->dbConnection = $this->resourceConnection->getConnection();
        }

        return $this->dbConnection;
    }

    /**
     * Retrieve real table name.
     *
     * Method act like a cache for already retrieved table names
     * is required to eliminate multiple calls to ResourceConnection class.
     *
     * @param string $tableName
     * @return string
     */
    private function getTable($tableName)
    {
        if (!isset($this->tableCache[$tableName])) {
            $this->tableCache[$tableName] = $this->resourceConnection->getTableName($tableName);
        }

        return $this->tableCache[$tableName];
    }

    /**
     * Cut array of categories according to $categoriesPercentInSharedCatalog.
     *
     * @param array $fullCategoriesList
     * @return array
     */
    private function limitSharedCatalogIds(array $fullCategoriesList)
    {
        $necessaryCount = round(count($fullCategoriesList) * $this->percentOfSharedCatalogsWithProducts / 100);

        return array_slice($fullCategoriesList, 0, $necessaryCount);
    }

    /**
     * @inheritdoc
     */
    public function getActionTitle()
    {
        return 'Generating shared catalogs';
    }

    /**
     * @inheritdoc
     */
    public function introduceParamLabels()
    {
        return ['shared_catalogs' => 'Shared Catalogs'];
    }

    /**
     * Set random price for shared catalog items. Set random discount in 75-100 % from product base price.
     *
     * @param array $customerGroupId
     * @return void
     * @throws \Exception
     */
    private function setSharedCatalogPrices(array $customerGroupId)
    {
        $connection = $this->getDbConnection();
        $linkFieldId = $this->metadataPool->getMetadata(
            \Magento\Catalog\Api\Data\ProductInterface::class
        )->getLinkField();
        foreach ($customerGroupId as $customerGroup) {
            $columns = [
                $linkFieldId => 'product.' . $linkFieldId,
                'all_groups' => $this->expressionFactory->create([
                    'expression' => 0
                ]),
                'customer_group_id' => 'product_item.customer_group_id',
                'qty' => $this->expressionFactory->create([
                    'expression' => 1
                ]),
                'percentage_value' => $this->expressionFactory->create([
                    'expression' => 'FLOOR(75 + RAND(' . $customerGroup . ') * 25)'
                ]),
                'website_id' => $this->expressionFactory->create([
                    'expression' => 0
                ]),
            ];
            $select = $connection->select()
                ->from(
                    [
                        'product_item' => $this->getTable(
                            self::SHARED_CATALOG_PRODUCT_ITEM_TABLE_NAME
                        )
                    ],
                    []
                )
                ->columns($columns)
                ->join(
                    ['product' => $this->getTable('catalog_product_entity')],
                    'product_item.sku = product.sku',
                    []
                )->where('product_item.customer_group_id = ?', $customerGroup);

            $connection->query(
                $select->insertFromSelect(
                    $this->getTable('catalog_product_entity_tier_price'),
                    array_keys($columns)
                )
            );
        }
    }

    /**
     * Assign permissions to categories of shared catalog.
     *
     * @return void
     */
    private function assignPermissionsToCategories()
    {
        $select = $this->getDbConnection()
            ->select()
            ->distinct(true)
            ->from(
                ['sc' => $this->getTable('shared_catalog')],
                ['customer_group_id']
            );

        $customerGroupIds = $this->getDbConnection()->fetchCol($select);
        array_unshift($customerGroupIds, self::SHARED_CATALOG_UNREGISTERED_USER_ROLE_ID);

        $connection = $this->getDbConnection();
        $columns = [
            'permission' => $this->expressionFactory->create([
                'expression' => \Magento\CatalogPermissions\Model\Permission::PERMISSION_ALLOW,
            ]),
            'website_id' => $this->expressionFactory->create([
                'expression' => 'NULL'
            ]),
            'category_id' => 'category.entity_id',
        ];
        foreach ($customerGroupIds as $customerGroupId) {
            $columns['customer_group_id'] = $this->expressionFactory->create(['expression' => $customerGroupId]);
            $select = $connection->select()
                ->from(['category' => $this->getTable('catalog_category_entity')], [])
                ->distinct()
                ->columns($columns)
                ->joinLeft(
                    ['perm' => $this->getTable(
                        'sharedcatalog_category_permissions'
                    )],
                    'perm.category_id = category.entity_id AND perm.customer_group_id = '.$customerGroupId,
                    []
                )
                ->where('perm.category_id IS NULL');

            $connection->query(
                $select->insertFromSelect(
                    $this->getTable('sharedcatalog_category_permissions'),
                    array_keys($columns)
                )
            );
        }
    }

    /**
     * Assign permissions to category permissions table.
     *
     * @return void
     */
    private function assignCategoriesPermissionsToIndex()
    {
        $connection = $this->getDbConnection();
        $columns = [
            'category_id' => 'shared_catalog.category_id',
            'website_id' => 'shared_catalog.website_id',
            'customer_group_id' => 'shared_catalog.customer_group_id',
            'grant_catalog_category_view' => 'shared_catalog.permission',
            'grant_catalog_product_price' => 'shared_catalog.permission',
            'grant_checkout_items' => 'shared_catalog.permission',
        ];
        $select = $connection->select()
            ->from(
                [
                    'shared_catalog' => $this->getTable(
                        'sharedcatalog_category_permissions'
                    )
                ],
                []
            )
            ->columns($columns);

        $connection->query(
            $select->insertFromSelect(
                $this->getTable('magento_catalogpermissions'),
                array_keys($columns)
            )
        );
    }

    /**
     * Creates Select object that can select specific percent of random simple products
     *
     * Is used to select random simple products that will be assigned to shared catalog
     *
     * @param int $percentage
     * @return \Magento\Framework\DB\Select
     */
    private function getTrueSimpleProductsSelect($percentage)
    {
        $select = $this->getDbConnection()->select()
            ->distinct()
            ->from(
                ['cpe' => $this->getTable('catalog_product_entity')],
                ''
            )
            ->columns(['sku' => 'cpe.sku'])
            ->joinLeft(
                ['cpsl' => $this->getTable('catalog_product_super_link')],
                'cpsl.product_id = cpe.row_id',
                ''
            )
            ->where('cpe.type_id = ?', 'simple')
            ->where('cpsl.link_id IS NULL');

        $totalAmountSelect = (clone $select)
            ->reset(\Magento\Framework\DB\Select::COLUMNS)
            ->columns('count(distinct cpe.sku)');

        $totalAmount = (int) $this->getDbConnection()->fetchOne($totalAmountSelect);

        $select->order('rand(42)')
            ->limit(round($totalAmount/100*$percentage));

        return $select;
    }

    /**
     * Create DB Select
     *
     * Creates Select object that can select specific percent of random non simple products (configurable, bundled)
     * with all their simple products. Is used to select random products that will be assigned to shared catalog
     *
     * @param int $percentage
     * @return \Magento\Framework\DB\Select
     */
    private function getNonSimpleProductsSelect($percentage)
    {
        $select1 = $this->getDbConnection()->select()
            ->distinct()
            ->from($this->getTable('catalog_product_relation'), '')
            ->columns(['parent_id' => 'parent_id', 'child_id' => 'parent_id']);

        $select2 = $this->getDbConnection()->select()
            ->from($this->getTable('catalog_product_relation'), '')
            ->columns(['parent_id' => 'parent_id', 'child_id' => 'child_id']);

        $subSelect = $this->getDbConnection()->select()
            ->union([$select1, $select2], \Magento\Framework\DB\Select::SQL_UNION);

        $totalAmountSelect = $this->getDbConnection()->select()
            ->from($this->getTable('catalog_product_relation'), '')
            ->columns(['count(DISTINCT parent_id)']);

        $totalAmount = (int) $this->getDbConnection()->fetchOne($totalAmountSelect);

        $limitSelect = $this->getDbConnection()->select()
            ->distinct()
            ->from($this->getTable('catalog_product_relation'), '')
            ->columns(['parent_id'])
            ->order('rand(42)')
            ->limit(round($totalAmount/100*$percentage));

        $select = $this->getDbConnection()->select()
            ->from(['sub' => $subSelect], '')
            ->columns(['cpe.sku'])
            ->joinInner(
                ['cpr' => $limitSelect],
                'cpr.parent_id = sub.parent_id',
                ''
            )
            ->joinLeft(
                ['cpe' => $this->getTable('catalog_product_entity')],
                'sub.child_id = cpe.row_id',
                ''
            );

        return $select;
    }
}
