<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\NegotiableQuote\Block\Adminhtml\Quote\Create\Store;

use Magento\Customer\Model\Config\Share;
use Magento\Customer\Test\Fixture\Customer as CustomerFixture;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Store\Test\Fixture\Group as StoreGroupFixture;
use Magento\Store\Test\Fixture\Store as StoreFixture;
use Magento\Store\Test\Fixture\Website as WebsiteFixture;
use Magento\TestFramework\Fixture\AppIsolation;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Fixture\DataFixture;
use PHPUnit\Framework\TestCase;
use Magento\Framework\Exception\NoSuchEntityException;

class SelectTest extends TestCase
{
    /**
     * @var Select
     */
    private $block;

    /**
     * @var WriterInterface
     */
    private $configWriter;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        parent::setUp();
        $objectManager = Bootstrap::getObjectManager();
        $this->block = $objectManager->create(Select::class);
        $this->configWriter = $objectManager->get(WriterInterface::class);
        $this->scopeConfig = $objectManager->get(ScopeConfigInterface::class);
    }

    /**
     * @inheritdoc
     */
    protected function tearDown(): void
    {
        $this->configWriter->save(Share::XML_PATH_CUSTOMER_ACCOUNT_SHARE, Share::SHARE_WEBSITE);
        $this->configWriter->save('btob/website_configuration/negotiablequote_active', 0);
        $this->scopeConfig->clean();
        parent::tearDown();
    }

    /**
     * Test get website ids with Negotiable Quote enabled in configuration and account share per website
     */
    #[
        AppArea('adminhtml'),
        AppIsolation(true),
        DataFixture(WebsiteFixture::class, ['code' => 'website2'], as: 'website2'),
        DataFixture(StoreGroupFixture::class, ['website_id' => '$website2.id$'], as: 'store_group2'),
        DataFixture(StoreFixture::class, ['store_group_id' => '$store_group2.id$'], as: 'store2'),
        DataFixture(CustomerFixture::class, ['website_id' => '$website2.id$'], as: 'customer'),
    ]
    public function testGetWebsiteIds()
    {
        $this->configWriter->save(Share::XML_PATH_CUSTOMER_ACCOUNT_SHARE, Share::SHARE_WEBSITE);
        $this->configWriter->save('btob/website_configuration/negotiablequote_active', 1);
        $this->scopeConfig->clean();

        $websiteId = DataFixtureStorageManager::getStorage()->get('website2')->getId();
        $customerId = DataFixtureStorageManager::getStorage()->get('customer')->getId();
        $this->block->setData('customer_id', $customerId);

        $this->assertEquals([$websiteId], $this->block->getWebsiteIds());
    }

    /**
     * Test get website ids with Negotiable Quote enabled in configuration and account share global
     */
    #[
        AppArea('adminhtml'),
        AppIsolation(true),
        DataFixture(WebsiteFixture::class, ['code' => 'website2'], as: 'website2'),
        DataFixture(StoreGroupFixture::class, ['website_id' => '$website2.id$'], as: 'store_group2'),
        DataFixture(StoreFixture::class, ['store_group_id' => '$store_group2.id$'], as: 'store2'),
        DataFixture(CustomerFixture::class, ['website_id' => '$website2.id$'], as: 'customer'),
    ]
    public function testGetWebsiteIdsWithAccountSharedGlobal()
    {
        $this->configWriter->save(Share::XML_PATH_CUSTOMER_ACCOUNT_SHARE, Share::SHARE_GLOBAL);
        $this->configWriter->save('btob/website_configuration/negotiablequote_active', 1);
        $this->scopeConfig->clean();

        $websiteId = DataFixtureStorageManager::getStorage()->get('website2')->getId();
        $customerId = DataFixtureStorageManager::getStorage()->get('customer')->getId();
        $this->block->setData('customer_id', $customerId);

        $this->assertEqualsCanonicalizing([1, $websiteId], $this->block->getWebsiteIds());
    }

    /**
     * Test get website ids with Negotiable Quote disabled in configuration and account share global
     */
    #[
        AppArea('adminhtml'),
        AppIsolation(true),
        DataFixture(WebsiteFixture::class, ['code' => 'website2'], as: 'website2'),
        DataFixture(StoreGroupFixture::class, ['website_id' => '$website2.id$'], as: 'store_group2'),
        DataFixture(StoreFixture::class, ['store_group_id' => '$store_group2.id$'], as: 'store2'),
        DataFixture(CustomerFixture::class, ['website_id' => '$website2.id$'], as: 'customer'),
    ]
    public function testGetWebsiteIdsWithNegotiableQuoteDisabled()
    {
        $this->configWriter->save(Share::XML_PATH_CUSTOMER_ACCOUNT_SHARE, Share::SHARE_GLOBAL);
        $this->configWriter->save('btob/website_configuration/negotiablequote_active', 0);
        $this->scopeConfig->clean();

        $customerId = DataFixtureStorageManager::getStorage()->get('customer')->getId();
        $this->block->setData('customer_id', $customerId);

        $this->assertEquals([], $this->block->getWebsiteIds());
    }

    /**
     * Test get website ids without customer id
     */
    #[
        AppArea('adminhtml'),
        AppIsolation(true),
    ]
    public function testGetWebsiteIdsWithoutCustomerId()
    {
        $this->configWriter->save(Share::XML_PATH_CUSTOMER_ACCOUNT_SHARE, Share::SHARE_GLOBAL);
        $this->configWriter->save('btob/website_configuration/negotiablequote_active', 1);
        $this->scopeConfig->clean();

        $this->expectException(NoSuchEntityException::class);
        $this->expectExceptionMessage('No such entity with customerId =');

        $this->block->getWebsiteIds();
    }
}
