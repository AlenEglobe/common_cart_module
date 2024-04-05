<?php

namespace CommonCart\CommonCartModule\Plugin;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\Quote;
use Magento\Customer\Model\Session;
use Magento\Company\Model\CompanyManagement;
use Magento\Company\Api\Data\CompanyInterface;

class LoadCartForSingleCustomer

{
    /**
     * @var \Magento\Customer\Model\Session
     */
    protected $_customerSession;

    /**
     * @var \Magento\Company\Model\CompanyManagement
     */
    protected $_companyManagement;


    public function __construct(
        \Magento\Customer\Model\Session $customerSession,
        CompanyManagement $companyManagement,
    ) {
        $this->_customerSession = $customerSession;
        $this->_companyManagement = $companyManagement;
    }

    public function getCompanyAdminId()
    {
        $customerId = $this->_customerSession->getCustomerId();
        $company  = $this->_companyManagement->getByCustomerId($customerId);
        $companyId = ($company instanceof CompanyInterface) ? $company->getId() : null;
        $adminId = $company->getSuperUserId();

        return $adminId;
    }
    /**
     * Around plugin to modify loadByCustomer method
     *
     * @param Quote $subject
     * @param \Closure $proceed
     * @param mixed $customer
     * @return Quote
     */
    public function aroundLoadByCustomer(
        Quote $subject,
        \Closure $proceed,
        $customer
    ) {
        // Hard-code the customer ID as 1
        $customer = $this->getCompanyAdminId();

        // Call the original method with the modified customer ID
        $result = $proceed($customer);

        // Perform any additional logic if needed

        return $result;
    }
}
