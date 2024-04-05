<?php

namespace Egits\MsiLowStockNotification\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Email\Model\ResourceModel\Template\CollectionFactory;

/**
 * Class EmailTemplate
 *
 * This class provides options for email templates.
 */
class EmailTemplate implements OptionSourceInterface
{
    /**
     * @var CollectionFactory
     */
    protected $templateCollectionFactory;

    /**
     * EmailTemplate constructor.
     *
     * @param CollectionFactory $templateCollectionFactory
     */
    public function __construct(
        CollectionFactory $templateCollectionFactory
    ) {
        $this->templateCollectionFactory = $templateCollectionFactory;
    }

    /**
     * Retrieve options array
     *
     * @return array
     */
    public function toOptionArray()
    {
        $options = [];
        $collection = $this->templateCollectionFactory->create();
        foreach ($collection as $template) {
            $options[] = [
                'label' => $template->getTemplateCode(),
                'value' => $template->getId()
            ];
        }
        return $options;
    }

    /**
     * Retrieve options
     *
     * @return array
     */
    public function getAllOptions()
    {
        return $this->toOptionArray();
    }
}
