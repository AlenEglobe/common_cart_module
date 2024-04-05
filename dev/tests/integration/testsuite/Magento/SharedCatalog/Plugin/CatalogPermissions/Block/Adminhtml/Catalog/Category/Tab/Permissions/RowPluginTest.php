<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\SharedCatalog\Plugin\CatalogPermissions\Block\Adminhtml\Catalog\Category\Tab\Permissions;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\CatalogPermissions\Block\Adminhtml\Catalog\Category\Tab\Permissions\Row;
use Magento\Framework\App\Config\MutableScopeConfigInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Registry;
use Magento\Framework\View\LayoutInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * Test plugin model for catalog category permission row block
 *
 * @magentoAppArea adminhtml
 * @magentoAppIsolation enabled
 */
class RowPluginTest extends TestCase
{
    private const CONFIG_PATH_SHARED_CATALOG_ACTIVE = 'btob/website_configuration/sharedcatalog_active';
    /**
     * @var Row
     */
    private $block;
    /**
     * @var Registry
     */
    private $registry;
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;
    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;
    /**
     * @var MutableScopeConfigInterface
     */
    private $mutableScopeConfig;
    /**
     * @var array
     */
    private $defaultConfig = [];

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();
        $objectManager = Bootstrap::getObjectManager();
        $this->storeManager = $objectManager->get(StoreManagerInterface::class);
        $defaultCategory = $objectManager->get(CategoryRepositoryInterface::class)->get(2);
        $this->registry = $objectManager->get(Registry::class);
        $this->registry->register('category', $defaultCategory);
        $this->block = $objectManager->get(LayoutInterface::class)->createBlock(Row::class);
        $this->scopeConfig = $objectManager->get(ScopeConfigInterface::class);
        $this->mutableScopeConfig = $objectManager->get(MutableScopeConfigInterface::class);
    }

    /**
     * @inheritDoc
     */
    protected function tearDown(): void
    {
        $this->registry->unregister('category');
        foreach ($this->defaultConfig as $path => $scopes) {
            foreach ($scopes as $scopeType => $scope) {
                foreach ($scope as $scopeId => $value) {
                    $this->setConfig($path, $value, $scopeType, $scopeId);
                }
            }
        }
        $this->defaultConfig = [];
        parent::tearDown();
    }

    /**
     * Test that website selector is shown
     */
    public function testShouldShowWebsiteSelector()
    {
        $this->setConfig(
            self::CONFIG_PATH_SHARED_CATALOG_ACTIVE,
            (string) 1,
            ScopeInterface::SCOPE_WEBSITE,
            $this->storeManager->getWebsite()->getCode()
        );
        $this->assertTrue($this->storeManager->hasSingleStore());
        $this->assertTrue($this->block->canEditWebsites());
        $this->assertStringContainsString('All Websites', $this->block->toHtml());
    }

    /**
     * Test that website selector is not shown
     */
    public function testShouldNotShowWebsiteSelector()
    {
        $this->setConfig(
            self::CONFIG_PATH_SHARED_CATALOG_ACTIVE,
            (string) 0,
            ScopeInterface::SCOPE_WEBSITE,
            $this->storeManager->getWebsite()->getCode()
        );
        $this->assertTrue($this->storeManager->hasSingleStore());
        $this->assertFalse($this->block->canEditWebsites());
        $this->assertStringNotContainsString('All Websites', $this->block->toHtml());
    }

    /**
     * @param string $path
     * @param string|null $value
     * @param string $scopeType
     * @param string|null $scopeId
     */
    private function setConfig(
        string $path,
        ?string $value,
        string $scopeType = ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
        ?string $scopeId = null
    ): void {
        if (!array_key_exists($path, $this->defaultConfig)
            || !array_key_exists($scopeType, $this->defaultConfig[$path])
            || !array_key_exists($scopeId, $this->defaultConfig[$path][$scopeType])
        ) {
            $this->defaultConfig[$path][$scopeType][$scopeId] = $this->scopeConfig->getValue(
                $path,
                $scopeType,
                $scopeId
            );
        }
        $this->mutableScopeConfig->setValue($path, $value, $scopeType, $scopeId);
    }
}
