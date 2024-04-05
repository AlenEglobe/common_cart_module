<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Magento\PurchaseOrderRule\Model\Rule;

use Magento\Framework\App\ObjectManager;
use PHPUnit\Framework\TestCase;

/**
 * @magentoAppArea frontend
 * @magentoAppIsolation enabled
 */
class ConditionBuilderTest extends TestCase
{
    /**
     * @var ObjectManager
     */
    private $objectManager;

    /**
     * @var ConditionBuilderFactory
     */
    private $conditionBuilderFactory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->objectManager = ObjectManager::getInstance();
        $this->conditionBuilderFactory = $this->objectManager->get(ConditionBuilderFactory::class);
    }

    /**
     * Test a simple condition which only one level of condition
     */
    public function testSimpleCondition()
    {
        $condition = $this->conditionBuilderFactory->create()
            ->setType('Test\Type\Combine')
            ->setAttribute(null)
            ->setOperator(null)
            ->setValue('1')
            ->setIsValueProcessed(null)
            ->setAggregator('all')
            ->create()
            ->toString();

        $this->assertEquals(
            [
                'type' => 'Test\Type\Combine',
                'attribute' => null,
                'operator' => null,
                'value' => '1',
                'is_value_processed' => null,
                'aggregator' => 'all'
            ],
            json_decode($condition, true)
        );
    }

    /**
     * Test a condition with a nested condition within
     */
    public function testNestedCondition()
    {
        $condition = $this->conditionBuilderFactory->create()
            ->setType('Test\Type\Combine')
            ->setAttribute(null)
            ->setOperator(null)
            ->setValue('1')
            ->setIsValueProcessed(null)
            ->setAggregator('all')
            ->addCondition(
                $this->conditionBuilderFactory->create()
                    ->setType('Test\Type\Address')
                    ->setAttribute('order_total')
                    ->setOperator('>')
                    ->setValue('100')
                    ->setCurrencyCode('USD')
                    ->setIsValueProcessed(false)
                    ->create()
            )
            ->create()
            ->toString();

        $this->assertEquals(
            [
                'type' => 'Test\Type\Combine',
                'attribute' => null,
                'operator' => null,
                'value' => '1',
                'is_value_processed' => null,
                'aggregator' => 'all',
                'conditions' => [
                    [
                        'type' => 'Test\Type\Address',
                        'attribute' => 'order_total',
                        'operator' => '>',
                        'value' => '100',
                        'currency_code' => 'USD',
                        'is_value_processed' => false
                    ]
                ]
            ],
            json_decode($condition, true)
        );
    }
}
