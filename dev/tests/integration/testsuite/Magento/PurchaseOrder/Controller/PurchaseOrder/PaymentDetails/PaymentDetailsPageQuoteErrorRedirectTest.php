<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Magento\PurchaseOrder\Controller\PurchaseOrder\PaymentDetails;

use Magento\Customer\Model\CustomerRegistry;
use Magento\Framework\Exception\LocalizedException;
use Magento\PurchaseOrder\Api\Data\PurchaseOrderInterface;
use Magento\PurchaseOrder\Api\PurchaseOrderRepositoryInterface;
use Magento\PurchaseOrder\Controller\PurchaseOrder\PaymentDetailsAbstract;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\CatalogInventory\Model\Stock\Status as StockStatus;
use Magento\Catalog\Model\Product\Attribute\Source\Status as ProductStatus;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\CatalogInventory\Model\StockRegistryStorage;
use Magento\Quote\Model\ResourceModel\Quote\Item\Collection as QuoteItemCollection;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\StateException;

/**
 * Controller test class for the purchase order payment details page.
 *
 * @see \Magento\PurchaseOrder\Controller\PurchaseOrder\View
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.TooManyFields)
 * @magentoAppArea frontend
 * @magentoAppIsolation enabled
 */
class PaymentDetailsPageQuoteErrorRedirectTest extends PaymentDetailsAbstract
{
    /**
     * Check redirect on payment details page with quote has an error
     *
     * @param string $customerEmail
     * @param string $purchaseOrderCreatorEmail
     * @param string $orderStatus
     * @param string $productStockStatus
     * @param string $productStatus
     * @param int $productQty
     * @param int $productCartQty
     * @param int $expectedHttpResponseCode
     * @param string $expectedRedirect
     *
     * @throws LocalizedException
     * @throws CouldNotSaveException
     * @throws InputException
     * @throws NoSuchEntityException
     * @throws StateException
     *
     * @dataProvider paymentDetailsPageQuoteErrorDataProvider
     * @magentoDataFixture Magento/PurchaseOrder/_files/company_with_structure_and_purchase_orders.php
     */
    public function testPaymentDetailsPageQuoteErrorRedirect(
        $customerEmail,
        $purchaseOrderCreatorEmail,
        $orderStatus,
        $productStockStatus,
        $productStatus,
        $productQty,
        $productCartQty,
        $expectedHttpResponseCode,
        $expectedRedirect
    ) {
        $this->setCompanyPurchaseOrderConfig('Magento', true);

        // Log in as the current user
        $purchaseOrderId = $this->getPurchaseOrderForCustomer($purchaseOrderCreatorEmail)->getEntityId();
        $currentUser = $this->objectManager->get(CustomerRegistry::class)->retrieveByEmail($customerEmail);

        $this->getRequest()->setParam('purchaseOrderId', $purchaseOrderId);
        $this->session->setCustomerAsLoggedIn($currentUser);

        $purchaseOrder = $this->objectManager->get(PurchaseOrderRepositoryInterface::class)->getById($purchaseOrderId);
        $quote = $this->objectManager->get(CartRepositoryInterface::class)->get($purchaseOrder->getQuoteId());
        $purchaseOrder->setStatus($orderStatus);
        $this->objectManager->get(PurchaseOrderRepositoryInterface::class)->save($purchaseOrder);

        //init quote product data
        $product = $this->objectManager->get(ProductRepositoryInterface::class)->get(
            'virtual-product',
            false,
            0
        );

        $product->setStockData(
            [
                'qty' => $productQty,
                'is_in_stock' => $productStockStatus
            ]
        );
        $product->setStatus($productStatus);
        $this->objectManager->get(ProductRepositoryInterface::class)->save($product);
        $this->objectManager->get(StockRegistryStorage::class)->clean();

        //init purchase order quote
        /** @var QuoteItemCollection $itemCollection */
        $itemCollection = $quote->getItemsCollection(false);
        $quoteItem = $itemCollection->getFirstItem();
        $quoteItem->setQty($productCartQty);
        $purchaseOrder->setSnapshotQuote($quote);
        $this->objectManager->get(PurchaseOrderRepositoryInterface::class)->save($purchaseOrder);

        // Dispatch the request to the view payment details page for the desired purchase order
        $this->dispatch(self::URI . '/purchaseOrderId/' . $purchaseOrderId);

        // Perform assertions
        $this->assertEquals($expectedHttpResponseCode, $this->getResponse()->getHttpResponseCode());

        if ($expectedRedirect) {
            $this->assertRedirect($this->stringContains($expectedRedirect));
        }

        $this->session->logout();
    }

    /**
     * Data provider for various quote errors redirect.
     *
     * @return array
     */
    public function paymentDetailsPageQuoteErrorDataProvider()
    {
        return [
            'product_enabled_in_stock_right_item_qty' => [
                'current_customer' => 'john.doe@example.com',
                'purchase_order_creator_email' => 'john.doe@example.com',
                'order_status' => PurchaseOrderInterface::STATUS_APPROVED_PENDING_PAYMENT,
                'product_stock_status' => StockStatus::STATUS_IN_STOCK,
                'product_status' => ProductStatus::STATUS_ENABLED,
                'product_qty' => 10,
                'product_cart_qty' => 2,
                'expected_http_response_code' => 200,
                'expected_redirect' => '',
            ],
            'product_disabled_in_stock_right_item_qty' => [
                'current_customer' => 'john.doe@example.com',
                'purchase_order_creator_email' => 'john.doe@example.com',
                'order_status' => PurchaseOrderInterface::STATUS_APPROVED_PENDING_PAYMENT,
                'product_stock_status' => StockStatus::STATUS_IN_STOCK,
                'product_status' => ProductStatus::STATUS_DISABLED,
                'product_qty' => 10,
                'product_cart_qty' => 2,
                'expected_http_response_code' => 302,
                'expected_redirect' => 'purchaseorder/purchaseorder/view',
            ],
            'product_enabled_out_of_stock_right_item_qty' => [
                'current_customer' => 'john.doe@example.com',
                'purchase_order_creator_email' => 'john.doe@example.com',
                'order_status' => PurchaseOrderInterface::STATUS_APPROVED_PENDING_PAYMENT,
                'product_stock_status' => StockStatus::STATUS_OUT_OF_STOCK,
                'product_status' => ProductStatus::STATUS_ENABLED,
                'product_qty' => 10,
                'product_cart_qty' => 2,
                'expected_http_response_code' => 302,
                'expected_redirect' => 'purchaseorder/purchaseorder/view',
            ],
            'product_enabled_in_stock_zero_item_qty' => [
                'current_customer' => 'john.doe@example.com',
                'purchase_order_creator_email' => 'john.doe@example.com',
                'order_status' => PurchaseOrderInterface::STATUS_APPROVED_PENDING_PAYMENT,
                'product_stock_status' => StockStatus::STATUS_IN_STOCK,
                'product_status' => ProductStatus::STATUS_ENABLED,
                'product_qty' => 0,
                'product_cart_qty' => 2,
                'expected_http_response_code' => 302,
                'expected_redirect' => 'purchaseorder/purchaseorder/view',
            ],
            'product_enabled_in_stock_wrong_item_qty' => [
                'current_customer' => 'john.doe@example.com',
                'purchase_order_creator_email' => 'john.doe@example.com',
                'order_status' => PurchaseOrderInterface::STATUS_APPROVED_PENDING_PAYMENT,
                'product_stock_status' => StockStatus::STATUS_IN_STOCK,
                'product_status' => ProductStatus::STATUS_ENABLED,
                'product_qty' => 1,
                'product_cart_qty' => 2,
                'expected_http_response_code' => 302,
                'expected_redirect' => 'purchaseorder/purchaseorder/view',
            ],
        ];
    }
}
