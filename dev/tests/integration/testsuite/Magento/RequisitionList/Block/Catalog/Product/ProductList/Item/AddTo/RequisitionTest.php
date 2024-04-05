<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\RequisitionList\Block\Catalog\Product\ProductList\Item\AddTo;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Customer\Model\Context;
use Magento\Framework\Escaper;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @magentoAppArea frontend
 */
class RequisitionTest extends TestCase
{
    /*
     * Test escaping product sku
     */
    public function testToHtml(): void
    {
        $httpContext = new \Magento\Framework\App\Http\Context();
        $httpContext->setValue(Context::CONTEXT_AUTH, 1, 1);
        $block = Bootstrap::getObjectManager()->create(
            Requisition::class,
            [
                'httpContext' => $httpContext
            ]
        );
        $block->setTemplate(
            'catalog/product/list/item/addto/requisition.phtml'
        );

        /** @var $product Product */
        $product = Bootstrap::getObjectManager()->create(
            Product::class
        );
        $product->setTypeId(
            'simple'
        )->setId(
            1
        )->setAttributeSetId(
            4
        )->setWebsiteIds(
            [1]
        )->setName(
            'Test Simple Product'
        )->setSku(
            'test product sku with spaces'
        )->setPrice(
            10
        )->setVisibility(
            Visibility::VISIBILITY_BOTH
        )->setStatus(
            Status::STATUS_ENABLED
        );

        $block->setProduct($product);

        /** @var Escaper $escaper */
        $escaper = Bootstrap::getObjectManager()
            ->get(Escaper::class);

        $this->assertStringContainsString(
            "form[data-product-sku='" . $escaper->escapeJs($product->getSku()) . "']",
            $block->toHtml()
        );
    }
}
