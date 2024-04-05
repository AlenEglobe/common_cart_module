<?php

namespace Egits\MsiLowStockNotification\Model\Config;

use Magento\Framework\Exception\LocalizedException;

class CronConfigEmail extends \Magento\Framework\App\Config\Value
{
    /**
     * Cron string path constant
     */
    public const CRON_STRING_PATH = 'crontab/default/jobs/testing_cron/schedule/cron_expr_email';

    /**
     * Cron model path constant
     */
    public const CRON_MODEL_PATH = 'crontab/default/jobs/testing_cron/run/model';

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
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource|null $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb|null $resourceCollection
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
        $frequency = $this->getData('groups/email_configuration/fields/cron_job_frequency_email/value');
        $notificationDateTime = $this->getData('groups/email_configuration/fields/notification_datetime/value');

        // Extracting date and time components from the combined field
        $notificationDateTimeObj = new \DateTime($notificationDateTime);
        $minute = (int) $notificationDateTimeObj->format('i'); // Minute
        $hour = (int) $notificationDateTimeObj->format('H'); // Hour
        $dayOfMonth = (int) $notificationDateTimeObj->format('d'); // Day of the Month
        $monthOfYear = (int) $notificationDateTimeObj->format('m'); // Month of the Year

        // Determine day of the week based on frequency
        $dayOfWeek = ($frequency == 'weekly') ? '1' : '*'; // Default to every day if not weekly

        // Constructing cron expression
        $cronExprString = "{$minute} {$hour} {$dayOfMonth} {$monthOfYear} {$dayOfWeek}";

        try {
            // Saving cron expression and model path
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
