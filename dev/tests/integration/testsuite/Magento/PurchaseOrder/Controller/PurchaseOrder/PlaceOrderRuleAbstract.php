<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Magento\PurchaseOrder\Controller\PurchaseOrder;

use Magento\CatalogRule\Model\ResourceModel\Rule\CollectionFactory as CatalogRuleCollectionFactory;
use Magento\CatalogRule\Model\Indexer\IndexBuilder as CatalogRuleIndexBuilder;

/**
 * Controller test class for the purchase order place order as company admin with price rules.
 *
 * @magentoAppArea frontend
 * @magentoAppIsolation enabled
 * @see \Magento\PurchaseOrder\Controller\PurchaseOrder\PlaceOrder
 */
class PlaceOrderRuleAbstract extends PurchaseOrderAbstract
{
    /**
     * Url to dispatch.
     */
    protected const URI = 'purchaseorder/purchaseorder/placeorder';

    /**
     * @var CatalogRuleCollectionFactory
     */
    protected $catalogRuleCollectionFactory;

    /**
     * @var CatalogRuleIndexBuilder
     */
    protected $catalogRuleIndexBuilder;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->catalogRuleCollectionFactory = $this->objectManager->get(CatalogRuleCollectionFactory::class);
        $this->catalogRuleIndexBuilder = $this->objectManager->get(CatalogRuleIndexBuilder::class);
    }
}
