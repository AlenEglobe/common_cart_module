<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\RequisitionList\Block\Checkout\Cart\Addto;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Session;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\GroupedProduct\Model\Product\Type\Grouped;
use Magento\Quote\Model\Quote;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;
use PHPUnit\Framework\TestCase;

/**
 * Test for requisition block in checkout cart
 *
 * @magentoAppArea frontend
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class RequisitionTest extends TestCase
{
    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var Session
     */
    private $customerSession;

    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var Requisition
     */
    private $block;

    /**
     * @var ObjectManager
     */
    private $objectManager;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    private $checkoutSession;

    /**
     * @var Json
     */
    private $jsonEncoder;

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        /** @var ObjectManager $objectManager */
        $this->objectManager = Bootstrap::getObjectManager();
        $this->customerRepository = $this->objectManager->get(CustomerRepositoryInterface::class);
        $this->productRepository = $this->objectManager->get(ProductRepositoryInterface::class);
        $this->customerSession = $this->objectManager->get(Session::class);
        $this->cartRepository = $this->objectManager->get(CartRepositoryInterface::class);
        $this->checkoutSession = $this->objectManager->get(\Magento\Checkout\Model\Session::class);
        $this->jsonEncoder = $this->objectManager->get(Json::class);
        $this->block = $this->objectManager->get(Requisition::class);
        $this->block->setTemplate('catalog/product/list/item/addto/requisition.phtml');
    }

    /**
     * Test get product data for requisition block in cart
     * @magentoConfigFixture cataloginventory/options/enable_inventory_check 1
     * @magentoDataFixture Magento/Sales/_files/quote_with_customer.php
     * @magentoDataFixture Magento/GroupedProduct/_files/product_grouped_with_simple.php
     *
     * @return void
     */
    public function testGetProductData(): void
    {
        $childIds = [];
        $result = [];
        $childProducts = [
            'simple_11',
            'simple_22',
        ];
        /** @var Quote $quote */
        $quote = $this->objectManager->create(Quote::class)->load('test01', 'reserved_order_id');
        $parentProduct = $this->productRepository->get('grouped');
        $products = $this->prepareGroupedProduct($parentProduct->getSku());
        foreach ($products as $product) {
            $childIds[$product->getSku()] = $product->getId();
            $quote->addProduct($product);
        }
        $quoteRepository = $this->objectManager->get(CartRepositoryInterface::class);
        $quoteRepository->save($quote);
        $this->checkoutSession->setQuoteId($quote->getId());
        $this->customerSession->setCustomerId(1);
        $productData = $this->block->getProductData();
        $expectedGroupedOptions = $this->jsonEncoder->unserialize($productData);
        foreach ($expectedGroupedOptions as $options) {
            if (in_array($options['sku'], $childProducts)) {
                parse_str($options['options'], $output);
                $result[] = $output;
            }
        }
        $expected = [
            [
                'qty' => '1',
                'product' => $childIds['simple_11'],
                'super_product_config' => [
                    'product_code' => 'product_type',
                    'product_type' => 'grouped',
                    'product_id' => $parentProduct->getId(),
                ],
                'item' => $parentProduct->getId(),
            ],
            [
                'qty' => '1',
                'product' => $childIds['simple_22'],
                'super_product_config' => [
                    'product_code' => 'product_type',
                    'product_type' => 'grouped',
                    'product_id' => $parentProduct->getId(),
                ],
                'item' => $parentProduct->getId(),
            ],
        ];

        $this->assertEquals($expected, $result);
    }

    /**
     * Get prepared grouped product for checkout
     *
     * @param string $sku
     *
     * @return array
     */
    private function prepareGroupedProduct(string $sku): array
    {
        $buyRequest = $this->objectManager->create(
            \Magento\Framework\DataObject::class,
            ['data' => ['value' => ['qty' => 1]]]
        );
        $product = $this->productRepository->get($sku);
        /** @var Grouped $type */
        $type = $this->objectManager->get(Grouped::class);
        $processMode = Grouped::PROCESS_MODE_FULL;

        return $type->processConfiguration($buyRequest, $product, $processMode);
    }
}
