<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\CompanyShipping\Model\Source;

use PHPUnit\Framework\TestCase;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\Framework\ObjectManagerInterface;
use Magento\CompanyShipping\Model\Source\ShippingMethod as ShippingMethodSource;
use Magento\Framework\App\Config\ScopeConfigInterface;

/**
 * Test Class for Magento\CompanyShipping\Model\Source\ShippingMethod
 *
 * @magentoAppArea frontend
 */
class ShippingMethodTest extends TestCase
{
    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var ShippingMethod
     */
    private $shippingMethodSource;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->shippingMethodSource = $this->objectManager->get(ShippingMethod::class);
    }

    /**
     * Test available shipping methods list
     */
    public function testToOptionArray()
    {
        $options = [
            'dhl' => [
                'title' => 'DHL',
                'active' => 1
            ],
            'fedex' => [
                'title' => 'Federal Express',
                'active' => 1
            ],
            'flatrate' => [
                'title' => 'Flat Rate',
                'active' => 0
            ],
            'freeshipping' => [
                'title' => 'Free Shipping',
                'active' => 1
            ],
            'ups' => [
                'title' => 'United Parcel Service',
                'active' => 0
            ]
        ];

        $expected = [
            [
                'value' => 'dhl',
                'label' => 'DHL'
            ],
            [
                'value' => 'fedex',
                'label' => 'Federal Express'
            ],
            [
                'value' => 'flatrate',
                'label' => 'Flat Rate (disabled)'
            ],
            [
                'value' => 'freeshipping',
                'label' => 'Free Shipping'
            ],
            [
                'value' => 'ups',
                'label' => 'United Parcel Service (disabled)'
            ]
        ];

        /** @var PHPUnit\Framework\MockObject_MockObject $scopeConfigMock */
        $scopeConfigMock = $this->createMock(ScopeConfigInterface::class);

        $scopeConfigMock->expects($this->once())
            ->method('getValue')
            ->with('carriers')
            ->willReturn($options);

        /** @var ShippingMethodSource $shippingMethod */
        $shippingMethod = $this->objectManager->create(
            ShippingMethodSource::class,
            [
                'scopeConfig' => $scopeConfigMock
            ]
        );
        $shippingMethods = $shippingMethod->toOptionArray();
        static::assertCount(5, $shippingMethods);
        $this->assertEquals($expected, $shippingMethods);
    }
}
