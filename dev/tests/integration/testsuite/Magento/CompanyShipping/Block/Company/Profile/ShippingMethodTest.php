<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\CompanyShipping\Block\Company\Profile;

use Magento\Customer\Api\Data\CustomerInterface;
use PHPUnit\Framework\TestCase;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\Framework\ObjectManagerInterface;
use Magento\CompanyShipping\Block\Company\Profile\ShippingMethod;
use Magento\Customer\Model\Session;
use Magento\Customer\Model\ResourceModel\CustomerRepository;

/**
 * Test Class for Magento\CompanyShipping\Block\Company\Profile\ShippingMethod
 *
 * @magentoDataFixture Magento/Company/_files/company.php
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
    private $shippingMethodBlock;

    /**
     * @var Session
     */
    private $session;

    /**
     * @var CustomerRepository
     */
    private $customerRepository;

    /**
     * @var CustomerInterface
     */
    private $customer;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->shippingMethodBlock = $this->objectManager->create(ShippingMethod::class);
        $this->session = $this->objectManager->get(Session::class);
        $this->customerRepository = $this->objectManager->get(CustomerRepository::class);
        $this->customer = $this->customerRepository->get('admin@magento.com');
        $this->session->loginById($this->customer->getId());
    }

    /**
     * Test shipping methods for company with:
     * B2B applicable shipping methods: Selected Shipping Methods
     * B2B applicable shipping methods are: free shipping
     * Global sales shipping methods free shipping is enabled
     * Global sales shipping methods flatrate shipping is enabled
     *
     * @magentoConfigFixture current_store carriers/flatrate/active 1
     * @magentoConfigFixture current_store carriers/freeshipping/active 1
     * @magentoConfigFixture btob/default_b2b_shipping_methods/applicable_shipping_methods 1
     * @magentoConfigFixture btob/default_b2b_shipping_methods/available_shipping_methods freeshipping
     */
    public function testGetShippingMethodsWithSelectedShippingMethodsEnabled()
    {
        $shippingMethods = $this->shippingMethodBlock->getShippingMethods();
        static::assertCount(1, $shippingMethods);
        static::assertEquals('Free Shipping', $shippingMethods[0]);
    }

    /**
     * Test shipping methods for company with:
     * B2B applicable shipping methods: All Shipping Methods
     * Global sales shipping methods free shipping is enabled and sort order 10
     * Global sales shipping methods flatrate shipping is enabled and sort order 20
     *
     * @magentoConfigFixture current_store carriers/flatrate/active 1
     * @magentoConfigFixture current_store carriers/flatrate/sort_order 20
     * @magentoConfigFixture current_store carriers/freeshipping/active 1
     * @magentoConfigFixture current_store carriers/freeshipping/sort_order 10
     * @magentoConfigFixture btob/default_b2b_shipping_methods/applicable_shipping_methods 0
     */
    public function testGetShippingMethodsWithAllShippingMethodsAndCustomSortOrder()
    {
        $shippingMethods = $this->shippingMethodBlock->getShippingMethods();
        static::assertCount(2, $shippingMethods);
        static::assertEquals('Free Shipping', $shippingMethods[0]);
        static::assertEquals('Flat Rate', $shippingMethods[1]);
    }
}
