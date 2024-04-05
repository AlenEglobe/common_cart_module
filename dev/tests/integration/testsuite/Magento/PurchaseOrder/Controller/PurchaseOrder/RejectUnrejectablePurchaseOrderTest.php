<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Magento\PurchaseOrder\Controller\PurchaseOrder;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\PurchaseOrder\Api\Data\PurchaseOrderInterface;
use Magento\PurchaseOrder\Api\PurchaseOrderRepositoryInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;
use Magento\Framework\Message\MessageInterface;

/**
 * Controller test class for rejecting purchase order which can't be rejected
 *
 * @see \Magento\PurchaseOrder\Controller\PurchaseOrder\Reject

 * @magentoAppArea frontend
 * @magentoAppIsolation enabled
 */
class RejectUnrejectablePurchaseOrderTest extends PurchaseOrderAbstract
{
    /**
     * Url to dispatch.
     */
    private const URI = 'purchaseorder/purchaseorder/reject';

    /**
     * @var Session
     */
    protected $session;

    /**
     * @var ObjectManager
     */
    protected $objectManager;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        /** @var ObjectManager $objectManager */
        $this->objectManager = Bootstrap::getObjectManager();
        $this->session = $this->objectManager->get(Session::class);
    }

    /**
     * @magentoDataFixture Magento/PurchaseOrder/_files/purchase_orders.php
     * @dataProvider unrejectablePurchaseOrderStatusDataProvider
     * @param string $status
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function testRejectAsCompanyAdminUnrejectablePurchaseOrder($status)
    {
        $companyAdmin = $this->objectManager->get(CustomerRepositoryInterface::class)->get('admin@magento.com');
        $this->session->loginById($companyAdmin->getId());

        $purchaseOrder = $this->getPurchaseOrderForCustomer('customer@example.com');
        $purchaseOrder->setStatus($status);
        $this->objectManager->get(PurchaseOrderRepositoryInterface::class)->save($purchaseOrder);

        $this->getRequest()->setMethod(Http::METHOD_POST);
        $this->dispatch(self::URI . '/request_id/' . $purchaseOrder->getEntityId());

        $message = 'Purchase order ' . $purchaseOrder->getIncrementId() . ' cannot be rejected.';
        $this->assertSessionMessages($this->equalTo([(string)__($message)]), MessageInterface::TYPE_ERROR);
        $this->session->logout();
    }

    /**
     * Data provider of purchase order statuses that do not allow rejection.
     *
     * @return string[]
     */
    public function unrejectablePurchaseOrderStatusDataProvider()
    {
        return [
            [PurchaseOrderInterface::STATUS_REJECTED],
            [PurchaseOrderInterface::STATUS_APPROVED],
            [PurchaseOrderInterface::STATUS_CANCELED],
            [PurchaseOrderInterface::STATUS_ORDER_IN_PROGRESS],
            [PurchaseOrderInterface::STATUS_ORDER_PLACED],
            [PurchaseOrderInterface::STATUS_ORDER_FAILED],
            [PurchaseOrderInterface::STATUS_APPROVED_PENDING_PAYMENT],
        ];
    }
}
