<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Magento\PurchaseOrder\Controller\PurchaseOrder\Cancel;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\PurchaseOrder\Api\Data\PurchaseOrderInterface;
use Magento\PurchaseOrder\Api\PurchaseOrderRepositoryInterface;
use Magento\PurchaseOrder\Controller\PurchaseOrder\CancelAbstract;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;
use Magento\Framework\Message\MessageInterface;

/**
 * Controller test class for cancelling purchase order..
 *
 * @see \Magento\PurchaseOrder\Controller\PurchaseOrder\Cancel
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @magentoAppArea frontend
 * @magentoAppIsolation enabled
 */
class CancelAsCompanyAdminNonCancellablePurchaseOrderTest extends CancelAbstract
{
    /**
     * Test cancellation of purchase order in status that does not allow cancellation.
     *
     * @magentoDataFixture Magento/PurchaseOrder/_files/purchase_orders.php
     * @dataProvider nonCancellablePurchaseOrderStatusDataProvider
     * @param string $finalStatus
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \Magento\Framework\Exception\InputException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function testCancelAsCompanyAdminNonCancellablePurchaseOrder($finalStatus)
    {
        /** @var ObjectManager $objectManager */
        $objectManager = Bootstrap::getObjectManager();
        $customerRepository = $objectManager->get(CustomerRepositoryInterface::class);
        $purchaseOrderRepository = $objectManager->get(PurchaseOrderRepositoryInterface::class);
        $session = $objectManager->get(Session::class);

        $companyAdmin = $customerRepository->get('admin@magento.com');
        $session->loginById($companyAdmin->getId());

        $purchaseOrder = $this->getPurchaseOrderForCustomer('customer@example.com');
        $purchaseOrder->setStatus($finalStatus);
        $purchaseOrderRepository->save($purchaseOrder);

        $this->getRequest()->setMethod(Http::METHOD_POST);
        $this->dispatch(self::URI . '/request_id/' . $purchaseOrder->getEntityId());

        $message = 'Purchase order ' . $purchaseOrder->getIncrementId() . ' cannot be canceled.';
        self::assertSessionMessages($this->equalTo([(string)__($message)]), MessageInterface::TYPE_ERROR);
        $session->logout();
    }

    /**
     * Data provider of purchase order statuses that do not allow cancellation.
     *
     * @return array
     */
    public function nonCancellablePurchaseOrderStatusDataProvider()
    {
        return [
            [PurchaseOrderInterface::STATUS_CANCELED],
            [PurchaseOrderInterface::STATUS_APPROVED],
            [PurchaseOrderInterface::STATUS_REJECTED],
            [PurchaseOrderInterface::STATUS_ORDER_IN_PROGRESS],
            [PurchaseOrderInterface::STATUS_ORDER_PLACED],
        ];
    }
}
