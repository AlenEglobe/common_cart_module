<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\RequisitionList\Block\Cart\Item\Renderer\Actions;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Session;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\GroupedProduct\Model\Product\Type\Grouped;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;
use PHPUnit\Framework\TestCase;

/**
 * Integration Test for AddToRequisition Block
 *
 * @magentoAppArea frontend
 * @magentoAppIsolation enabled
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class AddToRequisitionTest extends TestCase
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
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var AddToRequisition
     */
    private $block;

    /**
     * @var ObjectManager
     */
    private $objectManager;

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var Json
     */
    private $jsonEncoder;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        /** @var ObjectManager $objectManager */
        $this->objectManager = Bootstrap::getObjectManager();

        $this->customerRepository = $this->objectManager->get(CustomerRepositoryInterface::class);
        $this->customerSession = $this->objectManager->get(Session::class);
        $this->cartRepository = $this->objectManager->get(CartRepositoryInterface::class);
        $this->searchCriteriaBuilder = $this->objectManager->get(SearchCriteriaBuilder::class);
        $this->productRepository = $this->objectManager->get(ProductRepositoryInterface::class);
        $this->jsonEncoder = $this->objectManager->get(Json::class);

        $this->block = $this->objectManager->get(AddToRequisition::class);
        $this->block->setTemplate('Magento_RequisitionList::cart/item/renderer/actions/add_to_requisition_list.phtml');
    }

    /**
     * Given I am a guest
     * When this block gets rendered
     * Then the guest will receive empty string as output
     */
    public function testNothingIsOutputForGuestCart()
    {
        $this->assertEquals('', $this->block->toHtml());
    }

    /**
     * Given I am a customer
     * When this block gets a cart item assigned to it
     * Then the init script rendered by the block is namespaced by cart item ID
     *
     * @magentoDataFixture Magento/Sales/_files/quote_with_customer.php
     */
    public function testThatScriptIsNamespacedByCartItemIdWithLoggedInCustomer()
    {
        $carts = $this->cartRepository->getList(
            $this->searchCriteriaBuilder->addFilter('reserved_order_id', 'test01')->create()
        )->getItems();

        $cart = array_pop($carts);

        $customer = $this->customerRepository->getById(1);
        $this->customerSession->loginById($customer->getId());
        $cartItem = $cart->getItems()[0];
        $this->block->setItem($cartItem);

        $this->assertStringContainsString(
            "requisition_{$cartItem->getItemId()}",
            $this->block->toHtml()
        );
    }

    /**
     * Test get options
     *
     * @magentoDataFixture Magento/Sales/_files/quote_with_customer.php
     * @magentoDataFixture Magento/GroupedProduct/_files/product_grouped_with_simple.php
     *
     * @return void
     */
    public function testGetOptionsGroupedProduct(): void
    {
        /** @var Quote $quote */
        $quote = $this->objectManager->create(Quote::class)->load('test01', 'reserved_order_id');
        $parentProduct = $this->productRepository->get('grouped');
        $products = $this->prepareGroupedProduct($parentProduct->getSku());
        $childIds = [];
        foreach ($products as $product) {
            $childIds[$product->getSku()] = $product->getId();
            $quote->addProduct($product);
        }
        $quoteRepository = $this->objectManager->get(CartRepositoryInterface::class);
        $quoteRepository->save($quote);
        $customer = $this->customerRepository->getById(1);
        $this->customerSession->loginById($customer->getId());
        $cartItem = $quote->getAllItems()[1];
        $this->block->setItem($cartItem);
        $options = $this->block->getOptions();
        $expectedGroupedOptions = $this->jsonEncoder->unserialize($options);
        parse_str($expectedGroupedOptions, $output);
        $expected = [
            'qty' => '1',
            'product' => $childIds['simple_11'],
            'super_product_config' => [
                'product_code' => 'product_type',
                'product_type' => 'grouped',
                'product_id' => $parentProduct->getId(),
            ],
            'item' => $parentProduct->getId(),
        ];

        $this->assertEquals($expected, $output);
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
