<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ConfigurationFields\Block\Adminhtml\System\Config\Form\Field\CodeMirror;

use Hryvinskyi\ConfigurationFields\Block\Adminhtml\System\Config\Form\Field\AbstractWidgetField;
use Hryvinskyi\ConfigurationFields\ViewModel\CodeMirrorField;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Exception\LocalizedException;

/**
 * Base CodeMirror editor field for system configuration.
 *
 * Provides a syntax-highlighted code editor using CodeMirror library.
 * Extend this class or use pre-built language-specific editors.
 *
 * The view model and the label wrapper arrive as items of the block's `data` argument, declared for this type in the
 * module's adminhtml `di.xml`; Magento merges a type's arguments into its subclasses and virtual types, so a subclass
 * that declares its own constructor must forward `$data` to this one.
 */
class Editor extends AbstractWidgetField
{
    public const VIEW_MODEL = 'code_mirror_view_model';

    /**
     * @param Context $context
     * @param array<string,mixed> $editorConfig Configuration for CodeMirror editor
     * @param array<string,mixed> $data
     */
    public function __construct(
        Context $context,
        protected readonly array $editorConfig = [],
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * The code-editor widget
     *
     * @param AbstractElement $element
     * @return string
     * @throws LocalizedException When the block has no layout
     * @throws \LogicException When the view model is not configured
     */
    protected function widgetHtml(AbstractElement $element): string
    {
        return $this->renderWidget('Hryvinskyi_ConfigurationFields::system/config/codemirror.phtml', [
            'element' => $element,
            'view_model' => $this->requiredData(self::VIEW_MODEL, CodeMirrorField::class),
            'editor_config' => $this->getEditorConfig(),
        ]);
    }

    /**
     * Get editor configuration with defaults.
     *
     * @return array<string,mixed>
     */
    protected function getEditorConfig(): array
    {
        return [
            'mode' => $this->editorConfig['mode'] ?? 'htmlmixed',
            'theme' => $this->editorConfig['theme'] ?? 'default',
            'lineNumbers' => (bool)($this->editorConfig['line_numbers'] ?? true),
            'lineWrapping' => (bool)($this->editorConfig['line_wrapping'] ?? true),
        ];
    }
}
