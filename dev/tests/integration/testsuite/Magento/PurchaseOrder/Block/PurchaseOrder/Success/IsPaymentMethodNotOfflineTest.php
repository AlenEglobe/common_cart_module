<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento\PurchaseOrder\Block\PurchaseOrder\Success;

use Magento\Paypal\Model\Config;
use Magento\PurchaseOrder\Api\PurchaseOrderRepositoryInterface;
use Magento\PurchaseOrder\Block\PurchaseOrder\Success as SuccessBlock;
use Magento\PurchaseOrder\Block\PurchaseOrder\SuccessAbstract;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;
use Magento\PurchaseOrder\ViewModel\PurchaseOrder\Success as SuccessViewModel;

/**
 * Block test class for purchase order success page
 *
 * @see \Magento\PurchaseOrder\Block\PurchaseOrder\Success
 *
 * @magentoAppArea frontend
 */
class IsPaymentMethodNotOfflineTest extends SuccessAbstract
{
    /**
     * Test that the payment method set for purchase order is not offline
     *
     * @magentoDataFixture Magento/PurchaseOrder/_files/purchase_orders.php
     */
    public function testIsPaymentMethodNotOffline()
    {
        /** @var ObjectManager $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        $purchaseOrderRepository = $objectManager->get(PurchaseOrderRepositoryInterface::class);
        $successBlock = $objectManager->get(SuccessBlock::class);
        $viewModel = $objectManager->get(SuccessViewModel::class);

        $purchaseOrder = $this->getPurchaseOrderByIncrementId(900000001);
        $purchaseOrder->setPaymentMethod(Config::METHOD_BILLING_AGREEMENT);
        $purchaseOrderRepository->save($purchaseOrder);

        $successBlock->setViewModel($viewModel);
        /** @var \Magento\PurchaseOrder\ViewModel\PurchaseOrder\Success $viewModel1 */
        $viewModel1 = $successBlock->getViewModel();
        self::assertTrue($viewModel1->getPaymentStrategy()->isDeferredPayment($purchaseOrder));
    }
}
