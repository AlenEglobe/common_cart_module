<?php

namespace Egits\MsiLowStockNotification\Cron;

use Egits\MsiLowStockNotification\Observer\CheckStock as CheckStockObserver;
use Egits\MsiLowStockNotification\Model\Config\CronConfig;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface; // Add this line if you're using a logger

class CheckStock
{
    /**
     * @var CheckStockObserver
     */
    protected $checkStockObserver;

    /**
     * @var CronConfig
     */
    protected $cronConfig;

    /**
     * @var LoggerInterface
     */
    protected $logger; // Add this property if you're using a logger

    /**
     * Constructor.
     *
     * @param CheckStockObserver $checkStockObserver
     * @param CronConfig $cronConfig
     * @param LoggerInterface $logger // Add this parameter if you're using a logger
     */
    public function __construct(
        CheckStockObserver $checkStockObserver,
        CronConfig $cronConfig,
        LoggerInterface $logger = null // Add this parameter if you're using a logger
    ) {
        $this->checkStockObserver = $checkStockObserver;
        $this->cronConfig = $cronConfig;
        $this->logger = $logger; // Initialize logger if provided
    }

    /**
     * Execute the cron job.
     *
     * @return void
     */
    public function execute()
    {
        try {
            $this->checkStockObserver->execute();
            $this->cronConfig->saveCronExpression();
        } catch (LocalizedException $e) {
            // Log or handle the localized exception
            if ($this->logger) {
                $this->logger->error($e->getMessage());
            }
        } catch (\Exception $e) {
            // Log or handle other exceptions
            if ($this->logger) {
                $this->logger->error($e->getMessage());
            }
        }
    }
}
