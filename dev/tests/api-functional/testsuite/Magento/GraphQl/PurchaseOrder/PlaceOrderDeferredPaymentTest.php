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
use Magento\Checkout\Test\Fixture\SetShippingAddress;
use Magento\Company\Test\Fixture\Company;
use Magento\Customer\Test\Fixture\Customer;
use Magento\Framework\Exception\AuthenticationException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\PurchaseOrder\Api\PurchaseOrderRepositoryInterface;
use Magento\PurchaseOrder\Test\Encoder;
use Magento\PurchaseOrder\Test\Fixture\PurchaseOrderCompanyConfig;
use Magento\PurchaseOrder\Test\GetCustomerHeaders;
use Magento\Quote\Test\Fixture\AddProductToCart;
use Magento\Quote\Test\Fixture\CustomerCart;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\TestFramework\Fixture\Config;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\Fixture\DataFixtureStorageManager;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\GraphQlAbstract;
use Magento\User\Test\Fixture\User;

/**
 * Verify placing order for a purchase order
 */
class PlaceOrderDeferredPaymentTest extends GraphQlAbstract
{
    private const QUERY_SET_PAYMENT = <<<QRY
mutation {
  setPaymentMethodOnCart(
      input: {
        cart_id: "%s",
        payment_method: {
            code: "%s"
        }
      }
  ) {
    cart {
        selected_payment_method {
            code
        }
    }
  }
}
QRY;
    private const QUERY_PLACE_PURCHASE_ORDER = <<<QRY
mutation {
  placePurchaseOrder(input: {cart_id: "%s"}) {
    purchase_order {
      uid
      status
    }
  }
}
QRY;
    private const QUERY_PLACE_ORDER = <<<QRY
mutation {
  placeOrder(input: {cart_id: "%s"}) {
    order {
      order_number
    }
  }
}
QRY;

    /**
     * @var Encoder
     */
    private $encoder;

    /**
     * @var GetCustomerHeaders
     */
    private $getCustomerHeaders;

    public function setUp(): void
    {
        $this->encoder = Bootstrap::getObjectManager()->get(Encoder::class);
        $this->getCustomerHeaders = Bootstrap::getObjectManager()->get(GetCustomerHeaders::class);
    }

    #[
        Config('btob/website_configuration/company_active', 1),
        Config('btob/website_configuration/purchaseorder_enabled', 1),
        Config('payment/paypal_express/active', 1),
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
        DataFixture(QuoteIdMask::class, ['cart_id' => '$quote.id$'], 'quoteIdMask')
    ]
    public function testPlaceOrder()
    {
        $maskedQuoteId = DataFixtureStorageManager::getStorage()->get('quoteIdMask')->getMaskedId();

        $this->setPayment($maskedQuoteId, 'paypal_express');

        $purchaseOrderId = $this->placePurchaseOrder($maskedQuoteId);

        // Avoiding external PayPal API connection and excluding testing of PayPal implementation details
        $this->setPayment($maskedQuoteId, 'checkmo');

        $placeOrderResponse = $this->graphQlMutation(
            sprintf(self::QUERY_PLACE_ORDER, $maskedQuoteId),
            [],
            '',
            $this->getCustomerHeaders->execute()
        );

        $this->verifyPlacedOrder($purchaseOrderId, $placeOrderResponse);
    }

    /**
     * Set payment method via GraphQL and verify the response
     *
     * @param string $maskedQuoteId
     * @param string $code
     * @return void
     * @throws AuthenticationException
     * @throws LocalizedException
     */
    private function setPayment(string $maskedQuoteId, string $code): void
    {
        $this->assertEquals(
            [
                'setPaymentMethodOnCart' => [
                    'cart' => [
                        'selected_payment_method' => [
                            'code' => $code
                        ]
                    ]
                ]
            ],
            $this->graphQlMutation(
                sprintf(self::QUERY_SET_PAYMENT, $maskedQuoteId, $code),
                [],
                '',
                $this->getCustomerHeaders->execute()
            )
        );
    }

    /**
     * Place purchase order via GraphQL, verify the response and return the id
     *
     * @param string $maskedQuoteId
     * @return string
     * @throws AuthenticationException
     * @throws LocalizedException
     */
    private function placePurchaseOrder(string $maskedQuoteId): string
    {
        $purchaseOrderResponse = $this->graphQlMutation(
            sprintf(self::QUERY_PLACE_PURCHASE_ORDER, $maskedQuoteId),
            [],
            '',
            $this->getCustomerHeaders->execute()
        );

        $this->assertTrue(isset($purchaseOrderResponse['placePurchaseOrder']['purchase_order']['uid']));
        $this->assertTrue(isset($purchaseOrderResponse['placePurchaseOrder']['purchase_order']['status']));
        $this->assertEquals(
            'APPROVED_PENDING_PAYMENT',
            $purchaseOrderResponse['placePurchaseOrder']['purchase_order']['status']
        );
        return $purchaseOrderResponse['placePurchaseOrder']['purchase_order']['uid'];
    }

    /**
     * Verify place order GraphQL response
     *
     * @param string $purchaseOrderId
     * @param array $placeOrderResponse
     * @return void
     * @throws NoSuchEntityException|GraphQlInputException
     */
    private function verifyPlacedOrder(string $purchaseOrderId, array $placeOrderResponse): void
    {
        $this->assertTrue(isset($placeOrderResponse['placeOrder']['order']['order_number']));
        $orderNumber = $placeOrderResponse['placeOrder']['order']['order_number'];

        /** @var PurchaseOrderRepositoryInterface $purchaseOrderRepository */
        $purchaseOrderRepository = Bootstrap::getObjectManager()->get(PurchaseOrderRepositoryInterface::class);
        $purchaseOrder = $purchaseOrderRepository->getById($this->encoder->decode($purchaseOrderId));
        $this->assertEquals($purchaseOrder->getOrderIncrementId(), $orderNumber);

        /** @var OrderRepositoryInterface $orderRepository */
        $orderRepository = Bootstrap::getObjectManager()->get(OrderRepositoryInterface::class);
        $this->assertEquals($orderRepository->get($purchaseOrder->getOrderId())->getIncrementId(), $orderNumber);
    }
}
