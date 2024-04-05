<?php

namespace Egits\MsiLowStockNotification\Block;

class DatePicker extends \Magento\Config\Block\System\Config\Form\Field
{
    /**
     * Render element
     *
     * @param \Magento\Framework\Data\Form\Element\AbstractElement $element
     * @return string
     */
    public function render(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
        // Use setData method to set additional data instead of using undeclared methods
        $element->setData('date_format', \Magento\Framework\Stdlib\DateTime::DATE_INTERNAL_FORMAT);
        $element->setData('time_format', 'HH:mm:ss'); // Set date and time format as per your need
        $element->setData('shows_time', true);
        return parent::render($element);
    }
}
