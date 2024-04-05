<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Magento\PurchaseOrder\Controller\PurchaseOrder;

use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\CustomerBalance\Model\BalanceFactory as CustomerBalanceFactory;
use Magento\CompanyCredit\Api\CreditLimitManagementInterface;

/**
 * Controller test class for the purchase order place order as company admin.
 *
 * @magentoAppArea frontend
 * @magentoAppIsolation enabled
 * @see \Magento\PurchaseOrder\Controller\PurchaseOrder\PlaceOrder
 */
class PlaceOrderApprovedPurchaseAbstract extends PurchaseOrderAbstract
{
    /**
     * Url to dispatch.
     */
    protected const URI = 'purchaseorder/purchaseorder/placeorder';

    /**
     * @var WriterInterface
     */
    protected $configWriter;

    /**
     * @var CustomerBalanceFactory
     */
    protected $customerBalanceFactory;

    /**
     * @var CreditLimitManagementInterface
     */
    protected $creditLimitManagement;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->configWriter = $this->objectManager->get(WriterInterface::class);
        $this->customerBalanceFactory = $this->objectManager->get(CustomerBalanceFactory::class);
        $this->creditLimitManagement = $this->objectManager->get(CreditLimitManagementInterface::class);
    }
}
