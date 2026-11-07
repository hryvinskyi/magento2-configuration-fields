<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ConfigurationFields\Block\Adminhtml\System\Config\Form\Field;

use Hryvinskyi\ConfigurationFields\Model\Field\AddonLabelWrapper;
use Magento\Config\Block\System\Config\Form\Field as MagentoField;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Element\Template;

/**
 * A configuration field drawn by one of this module's widget templates
 *
 * The field's element is replaced by the widget a subclass renders, placed between the HTML the field declares
 * before and after its element. The objects the widget needs (its view model, the label wrapper) arrive as items of
 * the block's `data` argument, declared per block type in the module's adminhtml `di.xml`; a missing or mistyped item
 * stops the render with a message naming it rather than printing a widget without its data.
 */
abstract class AbstractWidgetField extends MagentoField
{
    public const ADDON_LABELS = 'field_addon_labels';

    /**
     * The widget, with the field's before and after HTML around it
     *
     * @param AbstractElement $element
     * @return string
     * @throws LocalizedException When the block has no layout
     * @throws \LogicException When the view model or the label wrapper is not configured
     */
    protected function _getElementHtml(AbstractElement $element): string
    {
        $widgetHtml = $this->widgetHtml($element);

        return $this->requiredData(self::ADDON_LABELS, AddonLabelWrapper::class)->wrap($element, $widgetHtml);
    }

    /**
     * The widget that edits the field's value
     *
     * @param AbstractElement $element
     * @return string
     * @throws LocalizedException When the block has no layout
     * @throws \LogicException When an item the widget needs is not configured
     */
    abstract protected function widgetHtml(AbstractElement $element): string;

    /**
     * A widget template rendered with the given data
     *
     * @param string $template The template's file id
     * @param array<string,mixed> $data
     * @return string
     * @throws LocalizedException When the block has no layout
     */
    protected function renderWidget(string $template, array $data): string
    {
        $widget = $this->getLayout()->createBlock(Template::class);
        $widget->setTemplate($template);
        $widget->addData($data);

        return $widget->toHtml();
    }

    /**
     * An object the block's `data` argument must hold
     *
     * @template T of object
     * @param string $key
     * @param class-string<T> $type
     * @return T
     * @throws \LogicException When the item is missing or of another type
     */
    protected function requiredData(string $key, string $type): object
    {
        $item = $this->getData($key);
        if (!$item instanceof $type) {
            throw new \LogicException(sprintf(
                'The "%s" item of the %s block\'s data argument must be a %s; it is declared in di.xml for the'
                . ' block type.',
                $key,
                static::class,
                $type
            ));
        }

        return $item;
    }
}
