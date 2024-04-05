<?php

namespace CommonCart\CommonCartModule\Plugin;


use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\QuoteRepository;

class LoadCartFromSession
{
    /**
     * Around plugin to modify getActiveForCustomer method
     *
     * @param QuoteRepository $subject
     * @param callable $proceed
     * @param int $customerId
     * @param array $sharedStoreIds
     * @return mixed
     * @throws NoSuchEntityException
     */
    public function aroundGetForCustomer(
        QuoteRepository $subject,
        callable $proceed,
        $customerId,
        array $sharedStoreIds = []
    ) {
        // Hard-code the customer ID as 1
        $customerId = 4;

        // Call the original method
        $result = $proceed($customerId, $sharedStoreIds);

        // Check if the quote is active
        return $result;
    }
}
