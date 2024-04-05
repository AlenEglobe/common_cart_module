<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\CompanyShipping\Controller\Adminhtml\Order\CreateWithSelectedShippingMethods;

use Magento\CompanyShipping\Controller\Adminhtml\Order\CreateWithSelectedShippingMethodsAbstract;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Backend\Model\Session\Quote as SessionQuote;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Company\Api\CompanyRepositoryInterface;
use Magento\CompanyShipping\Model\Source\CompanyApplicableShippingMethod;

/**
 * Test Class for B2B shipping method settings by admin create order flow with selected shipping methods
 *
 * @magentoAppArea adminhtml
 * @magentoDbIsolation enabled
 */
class LoadBlockShippingMethodWithCompanyCustomerB2BShippingMethodsAndSelectedShippingMethodsTest
    extends CreateWithSelectedShippingMethodsAbstract
{
    /**
     * Test available shipping rates for company customer quote by admin create order with:
     * Company B2B shipping methods is B2BShippingMethods
     * B2B settings selected shipping methods enabled
     * B2B settings selected shipping methods are: free shipping, table rate
     * Global sales shipping methods are: free shipping, flat rate shipping
     *
     * @param array $configData
     *
     * @magentoDataFixture Magento/Company/_files/company_with_structure.php
     * @magentoDataFixture Magento/Checkout/_files/quote_with_address_saved.php
     * @magentoDataFixture Magento/OfflineShipping/_files/tablerates.php
     * @dataProvider shippingConfigDataProviderWithSelectedShippingMethodsEnabled
     * @throws \Exception
     */
    public function testLoadBlockShippingMethodWithCompanyCustomerB2BShippingMethodsAndSelectedShippingMethods(
        $configData
    ) {
        $this->setConfigValues($configData);
        $quote = $this->_objectManager->get(CartRepositoryInterface::class)->getForCustomer(1);
        //replace quote customer to company customer
        $companyCustomer = $this->_objectManager->get(CustomerRepositoryInterface::class)
            ->get('alex.smith@example.com');

        $company = $this->_objectManager->get(CompanyRepositoryInterface::class)->get(
            $companyCustomer->getExtensionAttributes()->getCompanyAttributes()->getCompanyId()
        );

        $companyShippingSettings = $this->companyShippingMethodFactory->create()->addData(
            [
                'company_id' => $company->getId(),
                'applicable_shipping_method' => CompanyApplicableShippingMethod::B2B_SHIPPING_METHODS_VALUE,
                'use_config_settings' => 0
            ]
        );
        $companyShippingSettings->save();

        $quote->setCustomerId($companyCustomer->getId());
        $quote->setCustomer($companyCustomer);
        $quote->save();

        $session = $this->_objectManager->get(SessionQuote::class);
        $session->setQuoteId($quote->getId());

        $this->getRequest()->setMethod(HttpRequest::METHOD_POST);
        $this->getRequest()->setPostValue(
            [
                'customer_id' => $companyCustomer->getId(),
                'collect_shipping_rates' => 1,
                'store_id' => 1,
                'json' => true
            ]
        );
        $this->dispatch('backend/sales/order_create/loadBlock/block/shipping_method');
        $body = $this->getResponse()->getBody();

        self::assertStringContainsString('freeshipping_freeshipping', $body);
        self::assertStringContainsString('tablerate_bestway', $body);
        self::assertStringNotContainsString('flatrate_flatrate', $body);
    }
}
