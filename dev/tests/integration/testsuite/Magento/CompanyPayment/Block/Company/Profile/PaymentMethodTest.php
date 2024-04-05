<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\CompanyPayment\Block\Company\Profile;

use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\ResourceModel\CustomerRepository;
use Magento\Customer\Model\Session;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * Test Class for Magento\CompanyPayment\Block\Company\Profile\PaymentMethod
 *
 * @magentoDataFixture Magento/Company/_files/company.php
 * @magentoAppArea frontend
 * @magentoAppIsolation enabled
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
    private $paymentMethodBlock;

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
        $this->paymentMethodBlock = $this->objectManager->get(PaymentMethod::class);
        $this->session = $this->objectManager->get(Session::class);
        $this->customerRepository = $this->objectManager->get(CustomerRepository::class);
        $this->customer = $this->customerRepository->get('admin@magento.com');
        $this->session->loginById($this->customer->getId());
    }

    /**
     * Test payment methods for company with: B2B Payment Methods
     * B2B applicable payment methods: Selected Payment Methods
     * B2B payment methods are: Check / Money order, Purchase Order
     * Global sales payment methods check / money order is enabled
     * Global sales payment methods cashondelivery is enabled
     * Global sales payment methods purchase order is enabled
     *
     * @magentoConfigFixture current_store payment/checkmo/active 1
     * @magentoConfigFixture current_store payment/cashondelivery/active 1
     * @magentoConfigFixture current_store payment/purchaseorder/active 1
     * @magentoConfigFixture btob/default_b2b_payment_methods/applicable_payment_methods 1
     * @magentoConfigFixture btob/default_b2b_payment_methods/available_payment_methods checkmo,purchaseorder
     */
    public function testCompanyWithB2bPaymentMethodsEqualToConfigDefaultB2bSelectedPaymentMethods()
    {
        $expected = [
            'Check / Money order',
            'Purchase Order'
        ];

        $paymentMethods = $this->paymentMethodBlock->getPaymentMethods();
        $this->assertCount(2, $paymentMethods);
        $this->assertEquals($expected, $paymentMethods);
    }

    /**
     * Test payment methods for company with: B2B Payment Methods
     * B2B applicable payment methods: All Payment Methods
     * B2B payment methods are: Check / Money order, Purchase Order
     * Global sales payment methods check / money order is enabled
     * Global sales payment methods cashondelivery is enabled
     * Global sales payment methods purchase order is enabled
     *
     * @magentoConfigFixture current_store payment/checkmo/active 1
     * @magentoConfigFixture current_store payment/cashondelivery/active 1
     * @magentoConfigFixture current_store payment/purchaseorder/active 1
     * @magentoConfigFixture current_store payment/banktransfer/active 1
     * @magentoConfigFixture current_store payment/free/active 0
     * @magentoConfigFixture current_store payment/paypal_billing_agreement/active 0
     * @magentoConfigFixture current_store payment/companycredit/active 0
     * @magentoConfigFixture current_store payment/fake/active 0
     * @magentoConfigFixture current_store payment/fake_vault/active 0
     * @magentoConfigFixture btob/default_b2b_payment_methods/applicable_payment_methods 0
     */
    public function testCompanyWithB2bPaymentMethodsEqualToConfigDefaultB2bAllPaymentMethods()
    {
        $expected = [
            'Bank Transfer Payment',
            'Cash On Delivery',
            'Check / Money order',
            'Purchase Order'
        ];

        $paymentMethods = $this->paymentMethodBlock->getPaymentMethods();
        $this->assertCount(4, $paymentMethods);
        $this->assertEquals($expected, $paymentMethods);
    }
}
