<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Magento\PurchaseOrder\Controller\PurchaseOrder\AddItem;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Company\Api\Data\PermissionInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Message\MessageInterface;
use Magento\PurchaseOrder\Controller\PurchaseOrder\AddItemAbstract;

/**
 * Controller test class for adding purchase order items to the shopping cart.
 *
 * @see \Magento\PurchaseOrder\Controller\PurchaseOrder\AddItem
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @magentoAppArea frontend
 * @magentoAppIsolation enabled
 */
class AddItemActionNotAvaliableItemTest extends AddItemAbstract
{
    /**
     * Test that add items of a purchase order with not available item.
     *
     * @magentoDataFixture Magento/PurchaseOrder/_files/company_with_structure_and_purchase_orders.php
     */
    public function testAddItemActionNotAvaliableItem()
    {
        $customerRepository = $this->objectManager->get(CustomerRepositoryInterface::class);
        $productRepository = $this->objectManager->get(ProductRepositoryInterface::class);
        $session = $this->objectManager->get(Session::class);

        $this->setCompanyRolePermission(
            'Magento',
            'Default User',
            'Magento_PurchaseOrder::view_purchase_orders',
            PermissionInterface::ALLOW_PERMISSION
        );
        $customer = $customerRepository->get('veronica.costello@example.com');
        $session->loginById($customer->getId());
        $this->getRequest()->setMethod(Http::METHOD_POST);
        $purchaseOrder = $this->getPurchaseOrderForCustomer('veronica.costello@example.com');
        $product = $productRepository->get('virtual-product');
        $product->setStatus(\Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_DISABLED);
        $product->save();
        $this->dispatch(self::URI . '/request_id/' . $purchaseOrder->getEntityId());
        self::assertRedirect($this->stringContains('checkout/cart'));
        self::assertSessionMessages(
            $this->equalTo(['Some Item(s) are not available and are not added into the shopping cart.']),
            MessageInterface::TYPE_ERROR
        );

        $session->logout();
    }
}
