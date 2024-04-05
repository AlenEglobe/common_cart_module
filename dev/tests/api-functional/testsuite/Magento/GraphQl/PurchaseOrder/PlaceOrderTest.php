<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\GraphQl\PurchaseOrder;

use Magento\NegotiableQuote\Test\Fixture\QuoteIdMask;
use Magento\Catalog\Test\Fixture\Product;
use Magento\Catalog\Test\Fixture\Product as ProductFixture;
use Magento\Checkout\Test\Fixture\SetBillingAddress;
use Magento\Checkout\Test\Fixture\SetDeliveryMethod as SetDeliveryMethodFixture;
use Magento\Checkout\Test\Fixture\SetPaymentMethod as SetPaymentMethodFixture;
use Magento\Checkout\Test\Fixture\SetShippingAddress;
use Magento\Company\Test\Fixture\AssignCustomer;
use Magento\Company\Test\Fixture\Company;
use Magento\Customer\Test\Fixture\Customer;
use Magento\PurchaseOrder\Api\Data\PurchaseOrderInterface;
use Magento\PurchaseOrder\Api\PurchaseOrderRepositoryInterface;
use Magento\PurchaseOrder\Test\Encoder;
use Magento\PurchaseOrder\Test\Fixture\PurchaseOrderCompanyConfig;
use Magento\PurchaseOrder\Test\Fixture\PurchaseOrderFromQuote;
use Magento\PurchaseOrder\Test\GetCustomerHeaders;
use Magento\PurchaseOrderRule\Test\Fixture\PurchaseOrderValidate;
use Magento\Quote\Test\Fixture\AddProductToCart;
use Magento\Quote\Test\Fixture\CustomerCart;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\GraphQl\ResponseContainsErrorsException;
use Magento\TestFramework\TestCase\GraphQlAbstract;
use Magento\User\Test\Fixture\User;

/**
 * Verify placing order for a purchase order
 */
class PlaceOrderTest extends GraphQlAbstract
{
    /**
     * @var Encoder
     */
    private $encoder;

    /**
     * @var GetCustomerHeaders
     */
    private $getCustomerHeaders;

    /**
     * @return void
     */
    public function setUp(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $this->encoder = $objectManager->get(Encoder::class);
        $this->getCustomerHeaders = $objectManager->get(GetCustomerHeaders::class);
    }

    #[
        Config('btob/website_configuration/company_active', 1),
        Config('btob/website_configuration/purchaseorder_enabled', 1),
        DataFixture(ProductFixture::class, as: 'product'),
        DataFixture(
            Customer::class,
            [
                'addresses' => [
                    [
                        'country_id' => 'US',
                        'region_id' => 32,
                        'city' => 'Boston',
                        'street' => ['10 Milk Street'],
                        'postcode' => '02108',
                        'telephone' => '1234567890',
                        'default_billing' => true,
                        'default_shipping' => true
                    ]
                ]
            ],
            as: 'customer'
        ),
        DataFixture(User::class, as: 'user'),
        DataFixture(
            Company::class,
            [
                'sales_representative_id' => '$user.id$',
                'super_user_id' => '$customer.id$'
            ],
            'company'
        ),
        DataFixture(PurchaseOrderCompanyConfig::class, ['company_id' => '$company.id$']),
        DataFixture(CustomerCart::class, ['customer_id' => '$customer.id$'], as: 'quote'),
        DataFixture(AddProductToCart::class, ['cart_id' => '$quote.id$', 'product_id' => '$product.id$', 'qty' => 1]),
        DataFixture(SetBillingAddress::class, ['cart_id' => '$quote.id$']),
        DataFixture(SetShippingAddress::class, ['cart_id' => '$quote.id$']),
        DataFixture(SetDeliveryMethodFixture::class, ['cart_id' => '$quote.id$']),
        DataFixture(SetPaymentMethodFixture::class, ['cart_id' => '$quote.id$']),
        DataFixture(QuoteIdMask::class, ['cart_id' => '$quote.id$'], 'quoteIdMask'),
        DataFixture(PurchaseOrderFromQuote::class, ['cart_id' => '$quote.id$'], 'purchase_order'),
        DataFixture(PurchaseOrderValidate::class, ['purchase_order_id' => '$purchase_order.entity_id$',])
    ]
    public function testPlaceOrder()
    {
        /** @var PurchaseOrderInterface $purchaseOrder */
        $purchaseOrder = DataFixtureStorageManager::getStorage()->get('purchase_order');

        $result = $this->graphQlMutation(
            $this->getQuery($this->encoder->encode((string) $purchaseOrder->getEntityId())),
            [],
            '',
            $this->getCustomerHeaders->execute('customer')
        );

        $this->assertTrue(isset($result['placeOrderForPurchaseOrder']['order']['id']));

        /** @var OrderRepositoryInterface $orderRepository */
        $orderRepository = Bootstrap::getObjectManager()->get(OrderRepositoryInterface::class);

        $order = $orderRepository->get(base64_decode($result['placeOrderForPurchaseOrder']['order']['id']));

        $purchaseOrderRepository = Bootstrap::getObjectManager()->get(PurchaseOrderRepositoryInterface::class);
        $updatedPurchaseOrder = $purchaseOrderRepository->getById($purchaseOrder->getEntityId());
        $this->assertEquals($updatedPurchaseOrder->getOrderId(), $order->getEntityId());
        $this->assertEquals($updatedPurchaseOrder->getOrderIncrementId(), $order->getIncrementId());

        $this->assertEquals(
            [
                'placeOrderForPurchaseOrder' => [
                    'order' => [
                        'id' => base64_encode((string)$order->getEntityId()),
                        'order_number' => $order->getIncrementId()
                    ]
                ]
            ],
            $result
        );
    }

    #[
        Config('btob/website_configuration/company_active', 1),
        Config('btob/website_configuration/purchaseorder_enabled', 1),
        DataFixture(ProductFixture::class, as: 'product'),
        DataFixture(
            Customer::class,
            [
                'addresses' => [
                    [
                        'country_id' => 'US',
                        'region_id' => 32,
                        'city' => 'Boston',
                        'street' => ['10 Milk Street'],
                        'postcode' => '02108',
                        'telephone' => '1234567890',
                        'default_billing' => true,
                        'default_shipping' => true
                    ]
                ]
            ],
            as: 'customer'
        ),
        DataFixture(User::class, as: 'user'),
        DataFixture(
            Company::class,
            [
                'sales_representative_id' => '$user.id$',
                'super_user_id' => '$customer.id$'
            ],
            'company'
        ),
        DataFixture(Customer::class, as: 'unauthorized_customer'),
        DataFixture(
            AssignCustomer::class,
            [
                'company_id' => '$company.entity_id$',
                'customer_id' => '$unauthorized_customer.id$'
            ]
        ),
        DataFixture(PurchaseOrderCompanyConfig::class, ['company_id' => '$company.id$']),
        DataFixture(CustomerCart::class, ['customer_id' => '$customer.id$'], as: 'quote'),
        DataFixture(AddProductToCart::class, ['cart_id' => '$quote.id$', 'product_id' => '$product.id$', 'qty' => 1]),
        DataFixture(SetBillingAddress::class, ['cart_id' => '$quote.id$']),
        DataFixture(SetShippingAddress::class, ['cart_id' => '$quote.id$']),
        DataFixture(SetDeliveryMethodFixture::class, ['cart_id' => '$quote.id$']),
        DataFixture(SetPaymentMethodFixture::class, ['cart_id' => '$quote.id$']),
        DataFixture(QuoteIdMask::class, ['cart_id' => '$quote.id$'], 'quoteIdMask'),
        DataFixture(PurchaseOrderFromQuote::class, ['cart_id' => '$quote.id$'], 'purchase_order'),
        DataFixture(PurchaseOrderValidate::class, ['purchase_order_id' => '$purchase_order.entity_id$',])
    ]
    public function testUnauthorizedPlaceOrder()
    {
        $this->expectException(ResponseContainsErrorsException::class);
        $this->expectExceptionMessage('The current customer is not authorized to place the purchase order');
        /** @var PurchaseOrderInterface $purchaseOrder */
        $purchaseOrder = DataFixtureStorageManager::getStorage()->get('purchase_order');

        $result = $this->graphQlMutation(
            $this->getQuery($this->encoder->encode((string) $purchaseOrder->getEntityId())),
            [],
            '',
            $this->getCustomerHeaders->execute('unauthorized_customer')
        );

        $this->assertTrue(isset($result['placeOrderForPurchaseOrder']['order']['id']));
    }

    /**
     * @param string $purchaseOrderUid
     * @return string
     */
    private function getQuery(string $purchaseOrderUid): string
    {
        return <<<QUERY
mutation {
  placeOrderForPurchaseOrder(input: {purchase_order_uid: "{$purchaseOrderUid}"}) {
    order {
      id
      order_number
    }
  }
}
QUERY;
    }
}
