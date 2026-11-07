<?php
/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ConfigurationFields\Block\Adminhtml\System\Config\Form\Field;

use Hryvinskyi\ConfigurationFields\ViewModel\CronEditorField;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Exception\LocalizedException;

/**
 * Cron expression configuration field
 *
 * Edits the five parts of a cron expression in separate inputs and stores the expression in the field's value.
 *
 * The view model and the label wrapper arrive as items of the block's `data` argument, declared for this type in the
 * module's adminhtml `di.xml`; Magento merges a type's arguments into its subclasses and virtual types, so a subclass
 * that declares its own constructor must forward `$data` to this one.
 */
class CronEditor extends AbstractWidgetField
{
    public const VIEW_MODEL = 'cron_editor_view_model';

    /**
     * The cron-editor widget
     *
     * @param AbstractElement $element
     * @return string
     * @throws LocalizedException When the block has no layout
     * @throws \LogicException When the view model is not configured
     */
    protected function widgetHtml(AbstractElement $element): string
    {
        return $this->renderWidget('Hryvinskyi_ConfigurationFields::system/config/cron_editor.phtml', [
            'element' => $element,
            'view_model' => $this->requiredData(self::VIEW_MODEL, CronEditorField::class),
        ]);
    }
}
