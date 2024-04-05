<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Magento\PurchaseOrder\Controller\PurchaseOrder;

use Magento\Company\Api\CompanyRepositoryInterface;
use Magento\Company\Api\Data\CompanyInterface;
use Magento\Framework\App\Config\MutableScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\PurchaseOrder\Model\Company\Config\RepositoryInterface as CompanyConfigRepositoryInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\TestFramework\Helper\Bootstrap;

/**
 * Controller test class for the purchase order payment details page.
 *
 * @see \Magento\PurchaseOrder\Controller\PurchaseOrder\View
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.TooManyFields)
 * @magentoAppArea frontend
 * @magentoAppIsolation enabled
 */
class PaymentDetailsAbstract extends PurchaseOrderAbstract
{
    const URI = 'checkout/index/index';

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Enable company functionality for the website scope
        $this->setWebsiteConfig('btob/website_configuration/company_active', true);

        // Enable purchase order functionality for the website scope
        $this->setWebsiteConfig('btob/website_configuration/purchaseorder_enabled', true);
    }

    /**
     * Enable/Disable configuration for the website scope.
     *
     * @param string $path
     * @param bool $isEnabled
     */
    protected function setWebsiteConfig(string $path, bool $isEnabled)
    {
        /** @var MutableScopeConfigInterface $scopeConfig */
        $scopeConfig = Bootstrap::getObjectManager()->get(MutableScopeConfigInterface::class);
        $scopeConfig->setValue(
            $path,
            $isEnabled ? '1' : '0',
            ScopeInterface::SCOPE_WEBSITE
        );
    }

    /**
     * Enable/Disable purchase order functionality on a per company basis.
     *
     * @param string $companyName
     * @param bool $isEnabled
     * @throws LocalizedException
     */
    protected function setCompanyPurchaseOrderConfig(string $companyName, bool $isEnabled)
    {
        $this->searchCriteriaBuilder->addFilter('company_name', $companyName);
        $searchCriteria = $this->searchCriteriaBuilder->create();
        $results = $this->objectManager->get(CompanyRepositoryInterface::class)->getList($searchCriteria)->getItems();

        /** @var CompanyInterface $company */
        $company = reset($results);

        $companyConfig = $this->objectManager->get(CompanyConfigRepositoryInterface::class)->get($company->getId());
        $companyConfig->setIsPurchaseOrderEnabled($isEnabled);

        $this->objectManager->get(CompanyConfigRepositoryInterface::class)->save($companyConfig);
    }
}
