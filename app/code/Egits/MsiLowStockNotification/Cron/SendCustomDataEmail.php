<?php

namespace Egits\MsiLowStockNotification\Cron;

use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\App\ResourceConnection;
use Egits\MsiLowStockNotification\Model\ResourceModel\Stock\CollectionFactory;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\InventoryApi\Api\SourceItemRepositoryInterface;
use Psr\Log\LoggerInterface;

class SendCustomDataEmail
{
    /**
     * @var TransportBuilder
     */
    protected $transportBuilder;

    /**
     * @var SourceItemRepositoryInterface
     */
    protected $sourceItemRepository;

    /**
     * @var StateInterface
     */
    protected $inlineTranslation;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var ResourceConnection
     */
    protected $resourceConnection;

    /**
     * @var CollectionFactory
     */
    protected $custom404LogCollectionFactory;

    /**
     * @var CustomerRepositoryInterface
     */
    protected $customerRepository;

    /**
     * @var ProductRepositoryInterface
     */
    protected $productRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    protected $searchCriteriaBuilder;
    
    /**
     * @var LoggerInterface
     */
        protected $logger;

    /**
     * SendCustomDataEmail constructor.
     * @param TransportBuilder $transportBuilder
     * @param SourceItemRepositoryInterface $sourceItemRepository
     * @param StateInterface $inlineTranslation
     * @param ScopeConfigInterface $scopeConfig
     * @param ResourceConnection $resourceConnection
     * @param CollectionFactory $custom404LogCollectionFactory
     * @param CustomerRepositoryInterface $customerRepository
     * @param ProductRepositoryInterface $productRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param LoggerInterface $logger
     */
    public function __construct(
        TransportBuilder $transportBuilder,
        SourceItemRepositoryInterface $sourceItemRepository,
        StateInterface $inlineTranslation,
        ScopeConfigInterface $scopeConfig,
        ResourceConnection $resourceConnection,
        CollectionFactory $custom404LogCollectionFactory,
        CustomerRepositoryInterface $customerRepository,
        ProductRepositoryInterface $productRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        LoggerInterface $logger,
    ) {
        $this->transportBuilder = $transportBuilder;
        $this->sourceItemRepository = $sourceItemRepository;
        $this->inlineTranslation = $inlineTranslation;
        $this->scopeConfig = $scopeConfig;
        $this->resourceConnection = $resourceConnection;
        $this->custom404LogCollectionFactory = $custom404LogCollectionFactory;
        $this->customerRepository = $customerRepository;
        $this->productRepository = $productRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->logger = $logger;
    }

    /**
     * Execute controller action
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $adminEmail = $this->scopeConfig->getValue(
            'trans_email/ident_general/email',
            ScopeInterface::SCOPE_STORE
        );
        $adminName = $this->scopeConfig->getValue(
            'trans_email/ident_general/name',
            ScopeInterface::SCOPE_STORE
        );
        $ccEmails = $this->scopeConfig->getValue(
            'msi_low_stock_notification/email_configuration/cc_mail',
            ScopeInterface::SCOPE_STORE
        );

        $collection = $this->custom404LogCollectionFactory->create();
        $collection->load(); // Load all data from the collection

        $allData = $collection->getItems();

        // Group data by customer ID
        $groupedData = [];
        foreach ($allData as $item) {
            $customerId = $item->getCustomerId();
            if ($customerId) {
                if (!isset($groupedData[$customerId])) {
                    $groupedData[$customerId] = [];
                }
                $groupedData[$customerId][] = $item;
            }
        }

        // Check and send email notifications for low stock products
        foreach ($groupedData as $customerId => $data) {
            try {
                $customer = $this->customerRepository->getById($customerId);
                $customerEmail = $customer->getEmail();
                $customerName = $customer->getFirstname() . ' ' . $customer->getLastname();

                // Check for low stock products and send email if found
                $lowStockProducts = $this->checkLowStockProducts($data);
                if (!empty($lowStockProducts)) {
                    $this->inlineTranslation->suspend();
                    $templateIdentifier = 'custom_email_template'; // Set your email template identifier here
                    $transport = $this->transportBuilder
                        ->setTemplateIdentifier($templateIdentifier)
                        ->setTemplateOptions(['area' => \Magento\Framework\App\Area::AREA_FRONTEND,
                                    'store' => \Magento\Store\Model\Store::DEFAULT_STORE_ID])
                        ->setTemplateVars(['data' => $lowStockProducts,
                                    'customerName' => $customerName])
                        ->setFromByScope(['email' => $adminEmail, 'name' => $adminName])
                        ->addTo($customerEmail, $customerName)
                        ->addCc($ccEmails, $adminName)
                        ->getTransport();
                    $transport->sendMessage();
                    $this->inlineTranslation->resume();

                    // Delete the sent data for each customer here
                    foreach ($data as $item) {
                        $item->delete();
                    }
                }
            } catch (\Exception $e) {
                $this->logger->error("Error sending email: " . $e->getMessage());
            }
        }
    }

    /**
     * Check low stock products
     *
     * @param array $groupedData
     * @return array
     */
    protected function checkLowStockProducts($groupedData)
    {
        $thresholdValue = $this->scopeConfig->
                getValue(
                    'msi_low_stock_notification/low_stock_configuration/low_stock_threshold',
                    ScopeInterface::SCOPE_STORE
                );
        $lowStockProducts = []; // Store low stock products

        foreach ($groupedData as $data) {
            $productSku = $data->getProductItemSku();
            $customSourcename = $data->getSourceName();
            $searchCriteria = $this->searchCriteriaBuilder
                ->addFilter('sku', $productSku)
                ->addFilter('source_code', $customSourcename)
                ->create();
            $sourceItems = $this->sourceItemRepository->getList($searchCriteria);
            foreach ($sourceItems->getItems() as $sourceItem) {
                $quantity = (int) $sourceItem->getQuantity();
                
                if ($quantity <= $thresholdValue) {
                    $lowStockProducts[] = $data;
                    break; // Exit loop once a low stock product is found
                }
                
            }
        }
        return $lowStockProducts;
    }
}
