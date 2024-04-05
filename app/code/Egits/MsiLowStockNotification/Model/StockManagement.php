<?php

namespace Egits\MsiLowStockNotification\Model;

use Egits\MsiLowStockNotification\Api\StockManagementInterface;
use Exception;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Wishlist\Model\ResourceModel\Item\Collection; // Wishlist doesn't have a repository
use Magento\Wishlist\Model\ResourceModel\Item\CollectionFactory as WishlistItemCollectionFactory;
use Magento\Wishlist\Model\ResourceModel\Item as ItemResourceModel;
use Magento\Wishlist\Model\Wishlist;
use Magento\Wishlist\Model\ItemFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Egits\MsiLowStockNotification\Model\StockFactory as StockModelFactory;
use Egits\MsiLowStockNotification\Model\ResourceModel\Stock as StockResource;
use Egits\MsiLowStockNotification\Model\ResourceModel\Stock\CollectionFactory as StockCollectionFactory;
use Magento\Wishlist\Model\WishlistFactory;
use Magento\InventoryApi\Api\SourceItemRepositoryInterface;
use Magento\Wishlist\Model\ResourceModel\Wishlist as WishlistResource;
use Magento\Wishlist\Model\ResourceModel\Wishlist\CollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;

class StockManagement implements StockManagementInterface
{

    /**
     * @var WishlistFactory
     */
    protected WishlistFactory $wishlistFactory;

    /**
     * @var SourceItemRepositoryInterface
     */
    protected $sourceItemRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var StockCollectionFactory
     */
    protected StockCollectionFactory $stockCollectionFactory;

    /**
     * @var StockResource
     */
    protected StockResource $StockResource;

    /**
     * @var StockModelFactory
     */
    protected StockModelFactory $StockModelFactory;

    /**
     * @var WishlistResource
     */
    protected WishlistResource $wishlistResource;

    /**
     * @var CollectionFactory
     */
    protected CollectionFactory $CollectionFactory;

    /**
     * @var ItemFactory
     */
    private ItemFactory $wishlistItemFactory;

    /**
     * @var Wishlist
     */
    private Wishlist $Wishlist;

    /**
     * @var ScopeConfigInterface
     */
    protected ScopeConfigInterface $scopeConfig;

    /**
     * @var ProductRepositoryInterface
     */
    private ProductRepositoryInterface $productRepository;

    /**
     * @var WishlistItemCollectionFactory
     */
    private WishlistItemCollectionFactory $wishlistItemCollectionFactory;

    /**
     * @var ItemResourceModel
     */
    private ItemResourceModel $ItemResourceModel;

    /**
     * @var ManagerInterface
     */
    private ManagerInterface $messageManager;

    /**
     * TableRepository constructor.
     *
     * @param WishlistItemCollectionFactory $wishlistItemCollectionFactory
     * @param SourceItemRepositoryInterface $sourceItemRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param StockModelFactory $StockModelFactory
     * @param StockResource $StockResource
     * @param ProductRepositoryInterface $productRepository
     * @param ItemResourceModel $ItemResourceModel
     * @param StockCollectionFactory $stockCollectionFactory
     * @param ScopeConfigInterface $scopeConfig
     * @param Wishlist $Wishlist
     * @param ItemFactory $wishlistItemFactory
     * @param CollectionFactory $CollectionFactory
     * @param WishlistResource $wishlistResource
     * @param WishlistFactory $wishlistFactory
     * @param ManagerInterface $messageManager
     */
    public function __construct(
        WishlistItemCollectionFactory $wishlistItemCollectionFactory,
        SourceItemRepositoryInterface $sourceItemRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        StockModelFactory $StockModelFactory,
        StockResource $StockResource,
        ProductRepositoryInterface $productRepository,
        ItemResourceModel $ItemResourceModel,
        StockCollectionFactory $stockCollectionFactory,
        ScopeConfigInterface $scopeConfig,
        Wishlist $Wishlist,
        ItemFactory $wishlistItemFactory,
        CollectionFactory $CollectionFactory,
        WishlistResource $wishlistResource,
        WishlistFactory $wishlistFactory,
        ManagerInterface $messageManager
    ) {
        $this->wishlistItemCollectionFactory = $wishlistItemCollectionFactory;
        $this->sourceItemRepository = $sourceItemRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->productRepository = $productRepository;
        $this->ItemResourceModel = $ItemResourceModel;
        $this->Wishlist = $Wishlist;
        $this->scopeConfig = $scopeConfig;
        $this->StockResource = $StockResource;
        $this->StockModelFactory = $StockModelFactory;
        $this->CollectionFactory = $CollectionFactory;
        $this->stockCollectionFactory = $stockCollectionFactory;
        $this->messageManager = $messageManager;
        $this->wishlistItemFactory = $wishlistItemFactory;
        $this->wishlistResource = $wishlistResource;
        $this->wishlistFactory = $wishlistFactory;
    }

    /**
     * Method used to create a new wishlist collection instance
     *
     * @return Collection
     */
    public function getWishlistCollection(): Collection
    {
        return $this->wishlistItemCollectionFactory->create();
    }

    /**
     * Returns the product using the product id
     *
     * @param int $productId
     * @return ProductInterface
     * @throws NoSuchEntityException
     */
    public function getProductFromProductId($productId): ProductInterface
    {
        return $this->productRepository->getById($productId);
    }

    /**
     * Get the wishlist model using the wishlist ID
     *
     * @param int $wishlistId
     * @return WishlistResource
     */
    public function getWishlistFromWishlistId($wishlistId): WishlistResource
    {
        $wishlistModel = $this->wishlistFactory->create();
        return  $this->wishlistResource->load($wishlistModel, $wishlistId);
    }

    /**
     * Get the customer id from the wishlsit table using wishlist id
     *
     * @param int $wishlistId
     * @return int|null
     */
    public function getCustomerIdFromWishlistId($wishlistId): ?int
    {
        return $this->CollectionFactory->create()
            ->addFieldToFilter('wishlist_id', $wishlistId)
            ->getColumnValues('customer_id')[0];
    }

    /**
     * Get the configuration settings for the data
     *
     * @return array
     */
    public function getModuleConfig(): array
    {
        return $this->scopeConfig
            ->getValue('msi_low_stock_notification');
    }

    /**
     * Get wishlist ID using the customer ID
     *
     * @param int $customerId
     * @return int|null
     */
    public function getWishlistIdByCustomerId($customerId): ?int
    {
        $wishlist = $this->wishlistFactory->create()
            ->loadByCustomerId($customerId);
        if ($wishlist->getId()) {
            return $wishlist->getId();
        }
        // Return null if the wishlist does not exist
        return null;
    }

    /**
     * Check if any source quantity is lower than the threshold
     *
     * @param array $wishlistData
     * @return array
     * @throws NoSuchEntityException
     */
    public function checkSources($wishlistData): array
    {
        $lowStockSources = [];

        foreach ($wishlistData as $wishlistItem) {
            $productId = $wishlistItem->getProductId();
            $product = $this->productRepository->getById($productId);
            $productSku = $product->getSku();
            $customerId = $this->getCustomerIdFromWishlistId($wishlistItem->getWishlistId());

            // Build search criteria
            $searchCriteria = $this->searchCriteriaBuilder
                ->addFilter('sku', $productSku)
                ->create();

            // Get source items based on search criteria
            $sourceItems = $this->sourceItemRepository->getList($searchCriteria);

            // Process or return the source item data as needed
            foreach ($sourceItems->getItems() as $sourceItem) {
                $quantity = (int) $sourceItem->getQuantity();
                $sourceCode = $sourceItem->getSourceCode();

                $threshold = (int) $this->scopeConfig
                    ->getValue('msi_low_stock_notification/low_stock_configuration/low_stock_threshold');

                if ($quantity <= $threshold) {
                    $lowStockSources[] = [
                        'customer_id' => $customerId,
                        'product_id' => $productId,
                        'product_Sku' => $productSku,
                        'source_code' => $sourceCode,
                        'quantity' => $quantity,
                    ];
                }
            }
        }

        return $lowStockSources;
    }

    /**
     * This method is called to save the low stock sources data to the table
     *
     * @param array $lowStockSources
     * @throws Exception
     */
    public function saveLowStockItemData(array $lowStockSources): void
    {
        try {

            if (empty($lowStockSources)) {
                $this->messageManager
                    ->addNoticeMessage(__("There are currently no low stock sources for the wishlist items."));
                return;
            }

            // Flush the table so only the latest entry is saved
            $this->StockResource->flushTable();

            foreach ($lowStockSources as $lowStockSource) {
                $customer_id = (int) $lowStockSource['customer_id'];
                $productSku = $lowStockSource['product_Sku'];
                $source_code = $lowStockSource['source_code'];
                $quantity = (int) $lowStockSource['quantity'];

                // Check if the SKU already exists in the database

                $stockCollection = $this->getLowStockCollection();

                $existingEntry = $stockCollection->addFieldToFilter('sku', $productSku)
                    ->addFieldToFilter('source_name', $source_code)
                    ->addFieldToFilter('customer_id', $customer_id)
                    ->getFirstItem();

                // If the entry exists, update the quantity
                if ($existingEntry->getId()) {
                    $existingEntry->setSourceQuantity($quantity);
                    $this->StockResource->save($existingEntry);
                    $this->messageManager
                        ->addSuccessMessage(__(
                            "Quantity updated for product with SKU %1 and source %2.",
                            $productSku,
                            $source_code
                        ));
                } else {
                    // If the entry doesn't exist, create a new entry
                    $model = $this->StockModelFactory->create();
                    $model->setCustomerId($customer_id);
                    $model->setProductItemSku($productSku);
                    $model->setSourceName($source_code);
                    $model->setSourceQuantity($quantity);
                    $this->StockResource->save($model);
                    $this->messageManager
                        ->addSuccessMessage(__(
                            "Data Saved Successfully for product with SKU %1.",
                            $productSku
                        ));
                }
            }
        } catch (Exception $e) {
            // Handle the exception, log it, or rethrow if needed
            throw new Exception("Error saving low stock item data: " . $e->getMessage());
        }
    }

    /**
     * This method returns the current list of low stock values
     *
     * @return \Egits\MsiLowStockNotification\Model\ResourceModel\Stock\Collection
     */
    public function getLowStockCollection()
    {
        return $this->stockCollectionFactory->create();
    }

    /**
     * This method returns the extracted low stock values
     *
     * @param array $lowStockSource
     * @return array
     */
    public function extractLowStockSourceValues($lowStockSource): array
    {
        return [
            'customer_id' => $lowStockSource['customer_id'],
            'product_id' => $lowStockSource['product_id'],
            'product_Sku' => $lowStockSource['product_Sku'],
            'source_code' => $lowStockSource['source_code'],
            'quantity' => $lowStockSource['quantity'],
        ];
    }
}
