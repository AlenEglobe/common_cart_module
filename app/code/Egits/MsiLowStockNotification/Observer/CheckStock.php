<?php

namespace Egits\MsiLowStockNotification\Observer;

use Egits\MsiLowStockNotification\Api\StockManagementInterface;
use Magento\Framework\Message\ManagerInterface;

class CheckStock
{
    /**
     * @var StockManagementInterface
     */
    protected $stockManager;

    /**
     * @var ManagerInterface
     */
    protected $messageManager;

    /**
     * Constructor.
     *
     * @param StockManagementInterface $stockManager
     * @param ManagerInterface $messageManager
     */
    public function __construct(
        StockManagementInterface $stockManager,
        ManagerInterface $messageManager
    ) {
        $this->messageManager = $messageManager;
        $this->stockManager = $stockManager;
    }

    /**
     * Execute the cron task.
     *
     * @return void
     */
    public function execute()
    {
        $configData = $this->stockManager->getModuleConfig();
        $isEnabled = (int) ($configData['general']['enable'] ?? 0);

        if ($isEnabled) {
            $wishlistData = $this->stockManager->getWishlistCollection();
            $lowStockSources = $this->stockManager->checkSources($wishlistData);
            $this->stockManager->saveLowStockItemData($lowStockSources);
        } else {
            $this->messageManager->addErrorMessage(__("The Module is not enabled."));
        }
    }
}
