<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\SharedCatalog\Model\SearchAdapter\Aggregation\Builder;

use Magento\Config\Model\Config\Factory as ConfigFactory;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Search\AdapterInterface;
use Magento\Framework\Search\Request\Builder;
use Magento\Framework\Search\Request\Config;
use Magento\Framework\Search\Request\Config\Converter;
use Magento\Framework\Search\Response\QueryResponse;
use Magento\Indexer\Model\Indexer;
use Magento\Search\Model\AdapterFactory;
use Magento\SharedCatalog\Model\Config as SharedCatalogConfig;
use Magento\Framework\Search\RequestInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * Data provider test for shared catalog with elasticsearch
 */
class DataProviderTest extends TestCase
{
    /**
     * @var AdapterInterface
     */
    private $adapter;

    /**
     * @var Builder
     */
    private $requestBuilder;

    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->enableSharedCatalog();
        $converter = $this->objectManager->create(Converter::class);

        $document = new \DOMDocument();
        $document->load($this->getRequestConfigPath());
        $requestConfig = $converter->convert($document);

        $config = $this->objectManager->create(Config::class);
        $config->merge($requestConfig);

        $this->requestBuilder = $this->objectManager->create(
            Builder::class,
            ['config' => $config]
        );

        $this->adapter = $this->createAdapter();

        $indexer = $this->objectManager->create(Indexer::class);
        $indexer->load('catalogsearch_fulltext');
        $indexer->reindexAll();
    }

    /**
     * @inheritdoc
     */
    public function tearDown(): void
    {
        $config = Bootstrap::getObjectManager()->get(ConfigFactory::class)
            ->create();
        $config->setDataByPath(SharedCatalogConfig::CONFIG_SHARED_CATALOG, 0);
        $config->save();
        parent::tearDown();
    }

    /**
     * Get request config path
     *
     * @return string
     */
    protected function getRequestConfigPath(): string
    {
        return __DIR__ . '/../../../../_files/requests.xml';
    }

    /**
     * @return AdapterInterface
     */
    protected function createAdapter()
    {
        return $this->objectManager->create(AdapterFactory::class)->create();
    }

    /**
     * Execute query
     *
     * @return QueryResponse
     */
    private function executeQuery(): QueryResponse
    {
        /** @var RequestInterface $queryRequest */
        $queryRequest = $this->requestBuilder->create();

        $queryResponse = $this->adapter->query($queryRequest);

        return $queryResponse;
    }

    /**
     * Test elastic aggregations with shared catalog
     *
     * @magentoAppIsolation enabled
     * @magentoDbIsolation disabled
     * @magentoAppArea adminhtml
     * @magentoConfigFixture btob/website_configuration/company_active 1
     * @magentoDataFixture Magento/SharedCatalog/_files/products_with_layered_navigation_attribute.php
     * @magentoConfigFixture current_store catalog/search/elasticsearch_index_prefix adaptertest
     */
    public function testElasticAggregationsWithSharedCatalog(): void
    {
        $this->requestBuilder->bind('category_ids', 333);
        $this->requestBuilder->setRequestName('category');
        $queryResponse = $this->executeQuery();
        $result = $queryResponse->getAggregations()
            ->getBuckets()['test_filtered_attr']
            ->getValues()[0]
            ->getMetrics()['count'];
        $this->assertEquals(14, $result);
    }

    /**
     * Enable shared catalog
     *
     * @return void
     */
    private function enableSharedCatalog(): void
    {
        $config = Bootstrap::getObjectManager()->get(ConfigFactory::class)
            ->create();
        $config->setDataByPath(SharedCatalogConfig::CONFIG_SHARED_CATALOG, 1);
        $config->save();
    }
}
