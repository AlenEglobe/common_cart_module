<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Magento\PurchaseOrder\Controller\PurchaseOrder;

use Magento\Customer\Model\Session;
use Magento\PurchaseOrder\Model\CommentManagement;
use Magento\TestFramework\Helper\Bootstrap;

/**
 * Controller test class for the purchase order place order.
 *
 * @magentoAppArea frontend
 * @magentoAppIsolation enabled
 * @see \Magento\PurchaseOrder\Controller\PurchaseOrder\PlaceOrder
 */
class PlaceOrderAbstract extends PurchaseOrderAbstract
{
    /**
     * Url to dispatch.
     */
    protected const URI = 'purchaseorder/purchaseorder/placeorder';

    /**
     * @var CommentManagement
     */
    protected $commentManagement;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->objectManager = Bootstrap::getObjectManager();

        $this->session = $this->objectManager->get(Session::class);
        $this->commentManagement = $this->objectManager->get(CommentManagement::class);
    }
}
