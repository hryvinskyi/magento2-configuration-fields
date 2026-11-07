<?php
/**
 * Copyright (c) 2025-2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ConfigurationFields\Block\Adminhtml\System\Config\Form\Field;

use Hryvinskyi\ConfigurationFields\ViewModel\UiSelectField;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Exception\LocalizedException;

/**
 * Category select configuration field
 *
 * Draws a `select` or `multiselect` field as Magento's searchable category tree. The field stores one category id, or
 * several joined by commas. Any other field type is drawn as a notice naming it.
 *
 * The view model and the label wrapper arrive as items of the block's `data` argument, declared for this type in the
 * module's adminhtml `di.xml`; Magento merges a type's arguments into its subclasses and virtual types, so a subclass
 * that declares its own constructor must forward `$data` to this one.
 */
class UiSelect extends AbstractWidgetField
{
    public const VIEW_MODEL = 'ui_select_view_model';

    /**
     * The category-select widget
     *
     * @param AbstractElement $element
     * @return string
     * @throws LocalizedException When the block has no layout
     * @throws \LogicException When the view model is not configured
     */
    protected function widgetHtml(AbstractElement $element): string
    {
        return $this->renderWidget('Hryvinskyi_ConfigurationFields::system/config/ui_select.phtml', [
            'element' => $element,
            'view_model' => $this->requiredData(self::VIEW_MODEL, UiSelectField::class),
        ]);
    }
}
