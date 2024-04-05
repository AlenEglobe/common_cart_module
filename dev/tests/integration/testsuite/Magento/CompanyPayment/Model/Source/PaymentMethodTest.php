<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\CompanyPayment\Model\Source;

use Magento\CompanyPayment\Model\Source\PaymentMethod as PaymentMethodSource;
use Magento\Framework\ObjectManagerInterface;
use Magento\Payment\Api\Data\PaymentMethodInterface;
use Magento\Payment\Api\PaymentMethodListInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test Class for Magento\CompanyPayment\Model\Source\PaymentMethod
 *
 * @magentoAppArea frontend
 */
class PaymentMethodTest extends TestCase
{
    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var PaymentMethod
     */
    private $paymentMethodSource;

    /**
     * @var PaymentMethodListInterface|MockObject
     */
    private $paymentMethodListMock;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->paymentMethodSource = $this->objectManager->get(PaymentMethod::class);
        $this->paymentMethodListMock = $this->createMock(PaymentMethodListInterface::class);
    }

    /**
     * Test available payment methods list
     */
    public function testToOptionArray()
    {
        $paymentMethodList[] = $this->objectManager->create(
            PaymentMethodInterface::class,
            [
                'code' => 'banktransfer',
                'title' => 'Bank Transfer Payment',
                'storeId' => 1,
                'isActive' => false
            ]
        );
        $paymentMethodList[] = $this->objectManager->create(
            PaymentMethodInterface::class,
            [
                'code' => 'cashondelivery',
                'title' => 'Cash On Delivery',
                'storeId' => 1,
                'isActive' => false
            ]
        );
        $paymentMethodList[] = $this->objectManager->create(
            PaymentMethodInterface::class,
            [
                'code' => 'checkmo',
                'title' => 'Check / Money order',
                'storeId' => 1,
                'isActive' => true
            ]
        );
        $paymentMethodList[] = $this->objectManager->create(
            PaymentMethodInterface::class,
            [
                'code' => 'payflow_advanced',
                'title' => 'Credit Card (Payflow Advanced)',
                'storeId' => 1,
                'isActive' => true
            ]
        );
        $expected = [
            [
                'value' => 'banktransfer',
                'label' => 'Bank Transfer Payment (disabled)'
            ],
            [
                'value' => 'cashondelivery',
                'label' => 'Cash On Delivery (disabled)'
            ],
            [
                'value' => 'checkmo',
                'label' => 'Check / Money order'
            ],
            [
                'value' => 'payflow_advanced',
                'label' => 'Credit Card (Payflow Advanced)'
            ],
        ];

        /** @var PaymentMethodSource $paymentMethod */
        $paymentMethod = $this->objectManager->create(
            PaymentMethodSource::class,
            [
                'paymentMethodList' => $this->paymentMethodListMock
            ]
        );
        $this->paymentMethodListMock->expects($this->once())
            ->method('getList')
            ->with(1)
            ->willReturn($paymentMethodList);
        $paymentMethodsArray = $paymentMethod->toOptionArray();
        $this->assertCount(4, $paymentMethodsArray);
        $this->assertEquals($expected, $paymentMethodsArray);
    }
}
