<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\NegotiableQuote\Plugin\Customer;

use Magento\TestFramework\Helper\Bootstrap;

/**
 * Test for \Magento\NegotiableQuote\Plugin\Customer\SaveAddressPlugin class.
 */
class SaveAddressPluginTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    private $objectManager;
    /**
     * @var \Magento\Customer\Api\AddressRepositoryInterface
     */
    private $customerAddressRepository;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->customerAddressRepository = $this->objectManager->create(
            \Magento\Customer\Api\AddressRepositoryInterface::class
        );
    }

    /**
     * @magentoDataFixture Magento/Catalog/_files/products.php
     * @magentoDataFixture Magento/NegotiableQuote/_files/company_with_customer_for_quote.php
     * @magentoDataFixture Magento/NegotiableQuote/_files/negotiable_quote_with_shipping_address.php
     * @magentoAppIsolation enabled
     * @return void
     */
    public function testAroundSaveWithAllowedPermissions()
    {
        $customer = $this->objectManager->create(\Magento\Customer\Api\CustomerRepositoryInterface::class)
            ->get('email@companyquote.com');
        /** @var \Magento\NegotiableQuote\Model\NegotiableQuoteRepository $negotiableQuoteRepository */
        $negotiableQuoteRepository = $this->objectManager->create(
            \Magento\NegotiableQuote\Model\NegotiableQuoteRepository::class
        );
        $negotiableQuotes = $negotiableQuoteRepository->getListByCustomerId($customer->getId());
        $quote = array_shift($negotiableQuotes);
        $request = $this->objectManager->get(\Magento\TestFramework\Request::class)
            ->setParam('quoteId', $quote->getId());
        $context = $this->objectManager->get(
            \Magento\Framework\App\Action\Context::class,
            ['request' => $request]
        );
        $authorizationMock = $this->getMockBuilder(\Magento\Company\Model\Authorization::class)
            ->disableOriginalConstructor()
            ->setMethods(['isAllowed'])
            ->getMock();
        $authorizationMock->expects($this->any())
            ->method('isAllowed')
            ->with('Magento_NegotiableQuote::manage')
            ->willReturn(true);
        $restriction = $this->getMockBuilder(\Magento\NegotiableQuote\Model\Restriction\Customer::class)
            ->disableOriginalConstructor()
            ->setMethods(['canSubmit'])
            ->getMock();
        $restriction->expects($this->any())->method('canSubmit')->willReturn(true);
        $this->objectManager->addSharedInstance($authorizationMock, \Magento\Company\Model\Authorization::class);
        $this->objectManager->addSharedInstance(
            $restriction,
            \Magento\NegotiableQuote\Model\Restriction\Customer::class
        );
        /** @var \Magento\Customer\Api\AddressRepositoryInterface $addressRepository */
        $addressRepository = $this->objectManager->get(
            \Magento\Customer\Api\AddressRepositoryInterface::class,
            ['context' => $context]
        );
        /** @var \Magento\Customer\Api\Data\AddressInterfaceFactory $addressFactory */
        $addressFactory = $this->objectManager->create(\Magento\Customer\Api\Data\AddressInterfaceFactory::class);
        $customerAddress = $addressFactory->create()
            ->setCustomerId($customer->getId())
            ->setTelephone(3468676)
            ->setPostcode(75477)
            ->setCountryId('US')
            ->setCity('CityM')
            ->setCompany('CompanyName')
            ->setStreet(['Green str, 67'])
            ->setLastname('Smith')
            ->setFirstname('John')
            ->setRegionId(1);
        $savedCustomerAddress = $addressRepository->save($customerAddress);
        $quoteAddress = $quote->getShippingAddress();

        $this->assertEquals($customerAddress->getTelephone(), $savedCustomerAddress->getTelephone());
        $this->assertEquals($customerAddress->getPostcode(), $savedCustomerAddress->getPostcode());
        $this->assertEquals($customerAddress->getCountryId(), $savedCustomerAddress->getCountryId());
        $this->assertEquals($customerAddress->getCity(), $savedCustomerAddress->getCity());
        $this->assertEquals($customerAddress->getCompany(), $savedCustomerAddress->getCompany());
        $this->assertEquals($customerAddress->getStreet(), $savedCustomerAddress->getStreet());
        $this->assertEquals($customerAddress->getLastname(), $savedCustomerAddress->getLastname());
        $this->assertEquals($customerAddress->getFirstname(), $savedCustomerAddress->getFirstname());
        $this->assertEquals($customerAddress->getRegionId(), $savedCustomerAddress->getRegionId());

        $this->assertEquals($customerAddress->getTelephone(), $quoteAddress->getTelephone());
        $this->assertEquals($customerAddress->getPostcode(), $quoteAddress->getPostcode());
        $this->assertEquals($customerAddress->getCountryId(), $quoteAddress->getCountryId());
        $this->assertEquals($customerAddress->getCity(), $quoteAddress->getCity());
        $this->assertEquals($customerAddress->getCompany(), $quoteAddress->getCompany());
        $this->assertEquals($customerAddress->getStreet(), $quoteAddress->getStreet());
        $this->assertEquals($customerAddress->getLastname(), $quoteAddress->getLastname());
        $this->assertEquals($customerAddress->getFirstname(), $quoteAddress->getFirstname());
        $this->assertEquals($customerAddress->getRegionId(), $quoteAddress->getRegionId());
    }

    /**
     * @magentoDataFixture Magento/Catalog/_files/products.php
     * @magentoDataFixture Magento/NegotiableQuote/_files/company_with_customer_for_quote.php
     * @magentoDataFixture Magento/NegotiableQuote/_files/negotiable_quote_with_shipping_address.php
     * @magentoAppIsolation enabled
     * @return void
     */
    public function testAroundSaveWithDeniedPermissions()
    {
        $customer = $this->objectManager->create(\Magento\Customer\Api\CustomerRepositoryInterface::class)
            ->get('email@companyquote.com');
        /** @var \Magento\NegotiableQuote\Model\NegotiableQuoteRepository $negotiableQuoteRepository */
        $negotiableQuoteRepository = $this->objectManager->create(
            \Magento\NegotiableQuote\Model\NegotiableQuoteRepository::class
        );
        $negotiableQuotes = $negotiableQuoteRepository->getListByCustomerId($customer->getId());
        $quote = array_shift($negotiableQuotes);
        $request = $this->objectManager->get(\Magento\TestFramework\Request::class)
            ->setParam('quoteId', $quote->getId());
        $context = $this->objectManager->get(
            \Magento\Framework\App\Action\Context::class,
            ['request' => $request]
        );
        $authorizationMock = $this->getMockBuilder(\Magento\Company\Model\Authorization::class)
            ->disableOriginalConstructor()
            ->setMethods(['isAllowed'])
            ->getMock();
        $authorizationMock->expects($this->any())->method('isAllowed')->with('Magento_NegotiableQuote::manage')
            ->willReturn(false);
        $this->objectManager->addSharedInstance($authorizationMock, \Magento\Company\Model\Authorization::class);

        /** @var \Magento\Customer\Api\AddressRepositoryInterface $addressRepository */
        $addressRepository = $this->objectManager->get(
            \Magento\Customer\Api\AddressRepositoryInterface::class,
            ['context' => $context]
        );
        /** @var \Magento\Customer\Api\Data\AddressInterfaceFactory $addressFactory */
        $addressFactory = $this->objectManager->create(\Magento\Customer\Api\Data\AddressInterfaceFactory::class);
        $customerAddress = $addressFactory->create()
            ->setCustomerId($customer->getId())
            ->setTelephone(3468676)
            ->setPostcode(75477)
            ->setCountryId('US')
            ->setCity('CityM')
            ->setCompany('CompanyName')
            ->setStreet(['Green str, 67'])
            ->setLastname('Smith')
            ->setFirstname('John')
            ->setRegionId(1);
        $savedCustomerAddress = $addressRepository->save($customerAddress);
        $quoteAddress = $quote->getShippingAddress();

        $this->assertEquals($customerAddress->getTelephone(), $savedCustomerAddress->getTelephone());
        $this->assertEquals($customerAddress->getPostcode(), $savedCustomerAddress->getPostcode());
        $this->assertEquals($customerAddress->getCountryId(), $savedCustomerAddress->getCountryId());
        $this->assertEquals($customerAddress->getCity(), $savedCustomerAddress->getCity());
        $this->assertEquals($customerAddress->getCompany(), $savedCustomerAddress->getCompany());
        $this->assertEquals($customerAddress->getStreet(), $savedCustomerAddress->getStreet());
        $this->assertEquals($customerAddress->getLastname(), $savedCustomerAddress->getLastname());
        $this->assertEquals($customerAddress->getFirstname(), $savedCustomerAddress->getFirstname());
        $this->assertEquals($customerAddress->getRegionId(), $savedCustomerAddress->getRegionId());

        $this->assertNotEquals($customerAddress->getTelephone(), $quoteAddress->getTelephone());
        $this->assertNotEquals($customerAddress->getPostcode(), $quoteAddress->getPostcode());
        $this->assertNotEquals($customerAddress->getCity(), $quoteAddress->getCity());
        $this->assertNotEquals($customerAddress->getCompany(), $quoteAddress->getCompany());
        $this->assertNotEquals($customerAddress->getStreet(), $quoteAddress->getStreet());
        $this->assertNotEquals($customerAddress->getLastname(), $quoteAddress->getLastname());
        $this->assertNotEquals($customerAddress->getFirstname(), $quoteAddress->getFirstname());
        $this->assertNotEquals($customerAddress->getRegionId(), $quoteAddress->getRegionId());
    }
}
