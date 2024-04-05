<?php

namespace CommonCart\CommonCartModule\Model;



// use Magento\Customer\Model\Session as CustomerSession;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Psr\Log\LoggerInterface;
use Magento\Company\Model\CompanyManagement;


class CommonQuote extends CheckoutSession
{
    const CHECKOUT_STATE_BEGIN = 'begin';

    protected $companyManagement;

    /**
     * Quote instance
     *
     * @var Quote
     */
    protected $_quote;

    /**
     * Customer Data Object
     *
     * @var CustomerInterface|null
     */
    protected $_customer;

    /**
     * Whether load only active quote
     *
     * @var bool
     */
    protected $_loadInactive = false;

    /**
     * A flag to track when the quote is being loaded and attached to the session object.
     *
     * Used in trigger_recollect infinite loop detection.
     *
     * @var bool
     */
    private $isLoading = false;



    /**
     * @var \Magento\Customer\Model\Session
     */
    protected $_customerSession;

    /**
     * @var \Magento\Quote\Api\CartRepositoryInterface
     */
    protected $quoteRepository;

    /**
     * @var \Magento\Framework\HTTP\PhpEnvironment\RemoteAddress
     */
    protected $_remoteAddress;

    /**
     * @var \Magento\Customer\Api\CustomerRepositoryInterface
     */
    protected $customerRepository;

    /**
     * @param QuoteIdMaskFactory
     */
    protected $quoteIdMaskFactory;

    /**
     * @param bool
     */
    protected $isQuoteMasked;

    /**
     * @var \Magento\Quote\Model\QuoteFactory
     */
    protected $quoteFactory;

    /**
     * @var LoggerInterface|null
     */
    private $logger;

    /**
     * @param \Magento\Framework\App\Request\Http $request
     * @param \Magento\Framework\Session\SidResolverInterface $sidResolver
     * @param \Magento\Framework\Session\Config\ConfigInterface $sessionConfig
     * @param \Magento\Framework\Session\SaveHandlerInterface $saveHandler
     * @param \Magento\Framework\Session\ValidatorInterface $validator
     * @param \Magento\Framework\Session\StorageInterface $storage
     * @param \Magento\Framework\Stdlib\CookieManagerInterface $cookieManager
     * @param \Magento\Framework\Stdlib\Cookie\CookieMetadataFactory $cookieMetadataFactory
     * @param \Magento\Framework\App\State $appState
     * @param \Magento\Sales\Model\OrderFactory $orderFactory
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \Magento\Quote\Api\CartRepositoryInterface $quoteRepository
     * @param \Magento\Framework\HTTP\PhpEnvironment\RemoteAddress $remoteAddress
     * @param \Magento\Framework\Event\ManagerInterface $eventManager
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     * @param \Magento\Quote\Model\QuoteFactory $quoteFactory
     * @param LoggerInterface|null $logger
     * @throws \Magento\Framework\Exception\SessionException
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */

    public function __construct(
        \Magento\Framework\App\Request\Http $request,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
        \Magento\Framework\HTTP\PhpEnvironment\RemoteAddress $remoteAddress,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository,
        QuoteIdMaskFactory $quoteIdMaskFactory,
        \Magento\Quote\Model\QuoteFactory $quoteFactory,
        LoggerInterface $logger = null,
        CompanyManagement $companyManagement,
        \Magento\Framework\Session\SidResolverInterface $sidResolver,
        \Magento\Framework\Session\Config\ConfigInterface $sessionConfig,
        \Magento\Framework\Session\SaveHandlerInterface $saveHandler,
        \Magento\Framework\Session\ValidatorInterface $validator,
        \Magento\Framework\Session\StorageInterface $storage,
        \Magento\Framework\Stdlib\CookieManagerInterface $cookieManager,
        \Magento\Framework\Stdlib\Cookie\CookieMetadataFactory $cookieMetadataFactory,
        \Magento\Framework\App\State $appState,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Framework\Event\ManagerInterface $eventManager
    ) {
        $this->_customerSession = $customerSession;
        $this->quoteFactory = $quoteFactory;
        $this->quoteRepository = $quoteRepository;
        $this->_storeManager = $storeManager;
        $this->customerRepository = $customerRepository;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->request = $request;
        $this->_remoteAddress = $remoteAddress;
        $this->companyManagement = $companyManagement;
        $this->_orderFactory = $orderFactory;
        $this->_eventManager = $eventManager;

        // Call parent constructor with all required arguments
        parent::__construct(
            $request,
            $sidResolver,
            $sessionConfig,
            $saveHandler,
            $validator,
            $storage,
            $cookieManager,
            $cookieMetadataFactory,
            $appState,
            $orderFactory,
            $customerSession,
            $quoteRepository,
            $remoteAddress,
            $eventManager,
            $storeManager,
            $customerRepository,
            $quoteIdMaskFactory,
            $quoteFactory,
            $logger
        );
    }




    /**
     * Get checkout quote instance by current session
     *
     * @return Quote
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws NoSuchEntityException
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function getQuote()
    {
        $this->_eventManager->dispatch('custom_quote_process', ['checkout_session' => $this]);

        if ($this->_quote === null) {
            if ($this->isLoading) {
                throw new \LogicException("Infinite loop detected, review the trace for the looping path");
            }
            $this->isLoading = true;
            $quote = $this->quoteFactory->create();
            if ($this->getQuoteId()) {
                try {
                    if ($this->_loadInactive) {
                        $quote = $this->quoteRepository->get($this->getQuoteId());
                    } else {
                        $quote = $this->quoteRepository->getActive($this->getQuoteId());
                    }
                    $customerId = $this->_customerSession->getCustomerId();
                    $company = $this->companyManagement->getByCustomerId($customerId);
                    if ($company != null) {
                        $adminId = $company->getSuperUserId();
                        $customerId = $this->_customer
                            ? $this->_customer->getId()
                            : $adminId;
                    } else {
                        $customerId = $this->_customer
                            ? $this->_customer->getId()
                            : $this->_customerSession->getCustomerId();
                    }



                    if ($quote->getData('customer_id') && (int)$quote->getData('customer_id') !== (int)$customerId) {
                        $quote = $this->quoteFactory->create();
                        $this->setQuoteId(null);
                    }

                    /**
                     * If current currency code of quote is not equal current currency code of store,
                     * need recalculate totals of quote. It is possible if customer use currency switcher or
                     * store switcher.
                     */
                    if ($quote->getQuoteCurrencyCode() != $this->_storeManager->getStore()->getCurrentCurrencyCode()) {
                        $quote->setStore($this->_storeManager->getStore());
                        $this->quoteRepository->save($quote->collectTotals());
                        /*
                     * We mast to create new quote object, because collectTotals()
                     * can to create links with other objects.
                     */
                        $quote = $this->quoteRepository->get($this->getQuoteId());
                    }

                    if ($quote->getTotalsCollectedFlag() === false) {
                        $quote->collectTotals();
                    }
                } catch (NoSuchEntityException $e) {
                    $this->setQuoteId(null);
                }
            }

            if (!$this->getQuoteId()) {
                if ($this->_customerSession->isLoggedIn() || $this->_customer) {
                    $quoteByCustomer = $this->getQuoteByCustomer();
                    if ($quoteByCustomer !== null) {
                        $this->setQuoteId($quoteByCustomer->getId());
                        $quote = $quoteByCustomer;
                    }
                } else {
                    $quote->setIsCheckoutCart(true);
                    $quote->setCustomerIsGuest(1);
                    $this->_eventManager->dispatch('checkout_quote_init', ['quote' => $quote]);
                }
            }

            if ($this->_customerSession->isLoggedIn()) {
                $customerId = $this->_customerSession->getCustomerId();
                $company = $this->companyManagement->getByCustomerId($customerId);
                if ($company != null) {
                    $adminId = $company->getSuperUserId();
                    $quote->setCustomer($this->customerRepository->getById($adminId));
                } else {
                    $quote->setCustomer($this->customerRepository->getById($customerId));
                }
            }

            $quote->setStore($this->_storeManager->getStore());
            $this->_quote = $quote;
            $this->isLoading = false;
        }

        if (!$this->isQuoteMasked() && !$this->_customerSession->isLoggedIn() && $this->getQuoteId()) {
            $quoteId = $this->getQuoteId();
            /** @var $quoteIdMask \Magento\Quote\Model\QuoteIdMask */
            $quoteIdMask = $this->quoteIdMaskFactory->create()->load($quoteId, 'quote_id');
            if ($quoteIdMask->getMaskedId() === null) {
                $quoteIdMask->setQuoteId($quoteId)->save();
            }
            $this->setIsQuoteMasked(true);
        }

        $remoteAddress = $this->_remoteAddress->getRemoteAddress();
        if ($remoteAddress) {
            $this->_quote->setRemoteIp($remoteAddress);
            $xForwardIp = $this->request->getServer('HTTP_X_FORWARDED_FOR');
            $this->_quote->setXForwardedFor($xForwardIp);
        }

        return $this->_quote;
    }


    public function loadCustomerQuote()
    {
        if (!$this->_customerSession->getCustomerId()) {
            return $this;
        }

        $this->_eventManager->dispatch('load_customer_quote_before', ['checkout_session' => $this]);

        try {
            $customerId = $this->_customerSession->getCustomerId();
            $company = $this->companyManagement->getByCustomerId($customerId);
            $adminId = $company->getSuperUserId();
            $customerQuote = $this->quoteRepository->getForCustomer($adminId);
        } catch (NoSuchEntityException $e) {
            $customerQuote = $this->quoteFactory->create();
        }
        $customerQuote->setStoreId($this->_storeManager->getStore()->getId());

        if ($customerQuote->getId() && $this->getQuoteId() != $customerQuote->getId()) {
            if ($this->getQuoteId()) {
                $quote = $this->getQuote();
                $quote->setCustomerIsGuest(0);
                $this->quoteRepository->save(
                    $customerQuote->merge($quote)->collectTotals()
                );
                $newQuote = $this->quoteRepository->get($customerQuote->getId());
                $this->quoteRepository->save(
                    $newQuote->collectTotals()
                );
                $customerQuote = $newQuote;
            }

            $this->setQuoteId($customerQuote->getId());

            if ($this->_quote) {
                $this->quoteRepository->delete($this->_quote);
            }
            $this->_quote = $customerQuote;
        } else {
            $this->getQuote()->getBillingAddress();
            $this->getQuote()->getShippingAddress();
            $this->getQuote()->setCustomer($this->_customerSession->getCustomerDataObject())
                ->setCustomerIsGuest(0)
                ->setTotalsCollectedFlag(false)
                ->collectTotals();
            $this->quoteRepository->save($this->getQuote());
        }
        return $this;
    }
}
