<?php

namespace Egits\MsiLowStockNotification\Model\Config;

use Magento\Framework\Exception\LocalizedException;

class CronConfig extends \Magento\Framework\App\Config\Value
{
    /**
     * Cron string path constant
     */
    public const CRON_STRING_PATH =
        'crontab/default/jobs/egits_msi_low_stock_notification_check_stocks/schedule/cron_expr';

    /**
     * Cron model path constant
     */
    public const CRON_MODEL_PATH = 'crontab/default/jobs/egits_msi_low_stock_notification_check_stock/run/model';

    /**
     * @var \Magento\Framework\App\Config\ValueFactory
     */
    protected $_configValueFactory;

    /**
     * @var string
     */
    protected $_runModelPath = '';

    /**
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $config
     * @param \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList
     * @param \Magento\Framework\App\Config\ValueFactory $configValueFactory
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb $resourceCollection
     * @param string $runModelPath
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\App\Config\ScopeConfigInterface $config,
        \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList,
        \Magento\Framework\App\Config\ValueFactory $configValueFactory,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        $runModelPath = '',
        array $data = []
    ) {
        $this->_runModelPath = $runModelPath;
        $this->_configValueFactory = $configValueFactory;
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    /**
     * @inheritdoc
     *
     * @return $this
     * @throws \Exception
     */
    public function afterSave()
    {
        $time = $this->getData('groups/low_stock_configuration/fields/time/value');
        $frequency = $this->getData('groups/low_stock_configuration/fields/cron_job_frequency/value');

        // Define the values directly instead of using non-API class and constants
        $cronMonthlyValue = 0; // Example value for monthly
        $cronWeeklyValue = 1; // Example value for weekly

        $cronExprArray = [
            (int) ($time[1] ?? 0), // Minute
            (int) ($time[0] ?? 0), // Hour
            $frequency == $cronMonthlyValue ? '1' : '*', // Day of the Month
            '*', // Month of the Year
            $frequency == $cronWeeklyValue ? '1' : '*', // Day of the Week
        ];

        $cronExprString = join(' ', $cronExprArray);

        try {
            $this->_configValueFactory->create()->load(
                self::CRON_STRING_PATH,
                'path'
            )->setValue(
                $cronExprString
            )->setPath(
                self::CRON_STRING_PATH
            )->save();
            $this->_configValueFactory->create()->load(
                self::CRON_MODEL_PATH,
                'path'
            )->setValue(
                $this->_runModelPath
            )->setPath(
                self::CRON_MODEL_PATH
            )->save();
        } catch (LocalizedException $localizedException) {
            throw $localizedException;
        } catch (\Exception $e) {
            throw new LocalizedException(__('An error occurred while saving the cron expression.'));
        }
        return parent::afterSave();
    }
}
