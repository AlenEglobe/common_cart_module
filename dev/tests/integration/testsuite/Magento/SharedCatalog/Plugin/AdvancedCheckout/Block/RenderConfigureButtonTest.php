<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\SharedCatalog\Plugin\AdvancedCheckout\Block;

use Magento\AdvancedCheckout\Block\Adminhtml\Sku\Errors\Grid\Description;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\View\LayoutInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\Interception\PluginList;
use Magento\TestFramework\Quote\Model\GetQuoteByReservedOrderId;
use PHPUnit\Framework\TestCase;

/**
 * Checks configure button rendering
 *
 * @magentoAppArea adminhtml
 * @magentoDbIsolation enabled
 * @magentoAppIsolation enabled
 */
class RenderConfigureButtonTest extends TestCase
{
    /** @var ObjectManagerInterface */
    private $objectManager;

    /** @var Description */
    private $block;

    /** @var GetQuoteByReservedOrderId */
    private $getQuoteByReservedOrderId;

    /** @var RequestInterface */
    private $request;

    /** @var ProductRepositoryInterface */
    private $productRepository;

    /** @var DataObjectFactory */
    private $dataObjectFactory;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->objectManager = Bootstrap::getObjectManager();
        $this->request = $this->objectManager->get(RequestInterface::class);
        $this->block = $this->objectManager->get(LayoutInterface::class)->createBlock(Description::class);
        $this->getQuoteByReservedOrderId = $this->objectManager->get(GetQuoteByReservedOrderId::class);
        $this->dataObjectFactory = $this->objectManager->get(DataObjectFactory::class);
        $this->productRepository = $this->objectManager->get(ProductRepositoryInterface::class);
        $this->productRepository->cleanCache();
    }

    /**
     * @return void
     */
    public function testPluginIsRegistered(): void
    {
        $pluginInfo = $this->objectManager->get(PluginList::class)->get(Description::class);
        $this->assertSame(
            RenderConfigureButton::class,
            $pluginInfo['shared_catalog_configure_button']['instance']
        );
    }

    /**
     * @magentoDataFixture Magento/Checkout/_files/quote_with_taxable_product_and_customer.php
     *
     * @return void
     */
    public function testGetConfigureButtonHtml(): void
    {
        $this->prepareData('test_order_with_taxable_product', 'taxable_product');
        $result = $this->block->getConfigureButtonHtml();
        $this->assertStringContainsString('disabled="disabled', $result);
        $this->assertStringNotContainsString('onclick', $result);
        $this->assertStringContainsString((string)__('Configure'), strip_tags($result));
    }

    /**
     * @magentoDataFixture Magento/Checkout/_files/quote_with_items_and_custom_options_saved.php
     *
     * @return void
     */
    public function testGetConfigureButtonHtmlCompositeProduct(): void
    {
        $this->prepareData('test_order_item_with_items_and_custom_options', 'simple');
        $result = $this->block->getConfigureButtonHtml();
        $this->assertStringNotContainsString('disabled="disabled', $result);
        $this->assertStringContainsString('addBySku.configure', $result);
        $this->assertStringContainsString((string)__('Configure'), strip_tags($result));
    }

    /**
     * Prepare data for test execution
     *
     * @param string $reservedOrderId
     * @param string $productSku
     * @return void
     */
    private function prepareData(string $reservedOrderId, string $productSku): void
    {
        $quote = $this->getQuoteByReservedOrderId->execute($reservedOrderId);
        $this->request->setParams(['quote_id' => $quote->getId()]);
        $this->prepareBlock($productSku);
    }

    /**
     * Prepare block for test
     *
     * @param string $productSku
     * @return void
     */
    protected function prepareBlock(string $productSku): void
    {
        $product = $this->productRepository->get($productSku);
        $item = $this->dataObjectFactory->create();
        $item->setData($product->getData());
        $this->block->setProduct($product);
        $this->block->setItem($item);
    }
}
