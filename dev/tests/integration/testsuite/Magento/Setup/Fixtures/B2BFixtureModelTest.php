<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento\Setup\Fixtures;

use Magento\TestFramework\Helper\Bootstrap;
use Magento\Framework\Indexer\IndexerRegistry;
use Magento\Indexer\Model\Config;

/**
 * Test b2b fixtures
 *
 * @magentoDbIsolation disabled
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class B2BFixtureModelTest extends \Magento\TestFramework\Indexer\TestCase
{
    /**
     * Profile generator working directory
     *
     * @var string
     */
    protected static $_generatorWorkingDir;

    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var array
     */
    private $indexersState = [];

    /**
     * @var IndexerRegistry
     */
    private $indexerRegistry;

    /**
     * @var array
     */
    private $entityAsserts = [];

    /**
     * Set indexer mode to "scheduled" for do not perform reindex after creation entity
     */
    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->indexerRegistry = $this->objectManager->get(IndexerRegistry::class);

        $this->entityAsserts[] = $this->objectManager->get(FixturesAsserts\NegotiableQuotesAssert::class);
        $this->entityAsserts[] = $this->objectManager->get(FixturesAsserts\SharedCatalogAssert::class);

        foreach ($this->objectManager->get(Config::class)->getIndexers() as $indexerId) {
            $indexer = $this->indexerRegistry->get($indexerId['indexer_id']);
            $this->indexersState[$indexerId['indexer_id']] = $indexer->isScheduled();
            $indexer->setScheduled(true);
        }
    }

    /**
     * Return indexer to previous state
     */
    protected function tearDown(): void
    {
        foreach ($this->indexersState as $indexerId => $state) {
            $indexer = $this->indexerRegistry->get($indexerId);
            $indexer->setScheduled($state);
        }
        self::restoreFromDb();
        self::$dbRestored = true;

        parent::tearDown();
    }

    public static function setUpBeforeClass(): void
    {
        $db = Bootstrap::getInstance()->getBootstrap()
            ->getApplication()
            ->getDbInstance();
        if (!$db->isDbDumpExists()) {
            throw new \LogicException('DB dump does not exist.');
        }
        $db->restoreFromDbDump();

        parent::setUpBeforeClass();
    }

    /**
     * Generate test profile and performs assertions that generated entities are valid
     */
    public function testFixtureGeneration()
    {
        $reindexCommand = Bootstrap::getObjectManager()->get(
            \Magento\Indexer\Console\Command\IndexerReindexCommand::class
        );
        $itfApplication = Bootstrap::getInstance()->getBootstrap()->getApplication();
        $model = new FixtureModel($reindexCommand, $itfApplication->getInitParams());
        $model->loadConfig(__DIR__ . '/_files/b2b_small.xml');
        $model->initObjectManager();

        foreach ($model->loadFixtures()->getFixtures() as $fixture) {
            $fixture->execute();
        }

        foreach ($this->entityAsserts as $entityAssert) {
            try {
                $this->assertTrue($entityAssert->assert());
            } catch (\AssertionError $assertionError) {
                $this->assertTrue(false, $assertionError->getMessage());
            }
        }
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();

        /** @var $appCache \Magento\Framework\App\Cache */
        $appCache = Bootstrap::getObjectManager()->get(\Magento\Framework\App\Cache::class);
        $appCache->clean(
            [
                \Magento\Eav\Model\Cache\Type::CACHE_TAG,
                \Magento\Eav\Model\Entity\Attribute::CACHE_TAG,
            ]
        );
    }
}
