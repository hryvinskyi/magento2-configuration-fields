<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ConfigurationFields\ViewModel;

use Hryvinskyi\ConfigurationFields\Model\Field\ElementIdentity;
use Hryvinskyi\ConfigurationFields\Model\Field\StoredValue;
use Hryvinskyi\ConfigurationFields\Model\Field\WidgetMount;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * What the code-editor template prints for one field, and the config CodeMirror mounts with
 *
 * Stateless: one instance serves every code field of a page, so nothing about a field is kept between calls. A field
 * that is disabled (its value inherited or locked) opens the editor read-only. A stored value that is not valid UTF-8
 * is shown with its malformed bytes replaced rather than stopping the configuration page.
 */
class CodeMirrorField implements ArgumentInterface
{
    public const COMPONENT = 'Hryvinskyi_ConfigurationFields/js/codemirror-editor';

    /**
     * @param ElementIdentity $identity
     * @param StoredValue $storedValue
     * @param WidgetMount $widgetMount
     */
    public function __construct(
        private readonly ElementIdentity $identity,
        private readonly StoredValue $storedValue,
        private readonly WidgetMount $widgetMount
    ) {
    }

    /**
     * The template's data for one code field
     *
     * @param AbstractElement $element The field's form element
     * @param array<string,mixed> $editorConfig The field block's editor settings: `mode`, `theme`, `lineNumbers`,
     *     `lineWrapping`
     * @return array{
     *     mount: array{
     *         rootId: non-empty-string,
     *         init: array<non-empty-string, array<string, array<string, mixed>>>,
     *         initJson: string
     *     },
     *     inputId: string,
     *     inputName: string,
     *     inheritId: string,
     *     disabled: bool,
     *     value: string
     * }
     */
    public function config(AbstractElement $element, array $editorConfig): array
    {
        $inputId = $this->identity->htmlId($element);
        $inheritId = $this->identity->inheritCheckboxId($element);
        $disabled = (bool) $element->getData('disabled');

        return [
            'mount' => $this->widgetMount->mount($element, self::COMPONENT, [
                'inputId' => $inputId,
                'inheritId' => $inheritId,
                'mode' => $this->text($editorConfig['mode'] ?? null, 'htmlmixed'),
                'theme' => $this->text($editorConfig['theme'] ?? null, 'default'),
                'lineNumbers' => (bool) ($editorConfig['lineNumbers'] ?? true),
                'lineWrapping' => (bool) ($editorConfig['lineWrapping'] ?? true),
                'readOnly' => $disabled,
            ]),
            'inputId' => $inputId,
            'inputName' => $this->identity->name($element),
            'inheritId' => $inheritId,
            'disabled' => $disabled,
            'value' => $this->storedValue->text($element),
        ];
    }

    /**
     * A configured text, or the default when it is absent or not text
     *
     * @param mixed $value
     * @param string $default
     * @return string
     */
    private function text(mixed $value, string $default): string
    {
        return is_string($value) && $value !== '' ? $value : $default;
    }
}
