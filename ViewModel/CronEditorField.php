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
use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * What the cron-editor template prints for one field, and the config its script mounts with
 *
 * Stateless: one instance serves every cron field of a page, so nothing about a field is kept between calls. The
 * stored expression is handed over as stored, never replaced by a default: an empty field stays empty until someone
 * edits it. A stored value that is not valid UTF-8 is handed over with its malformed bytes replaced rather than
 * stopping the configuration page.
 *
 * The script is told the locale of the interface as Magento spells it (`de_DE`); in the admin area the locale
 * resolver reports the admin user's interface language, and the script chooses the description language from it.
 *
 * The hidden input that posts the expression carries the class of the script's validation rule and the classes
 * Magento gives the element, among them those of the field's `<validate>` (such as `required-entry`), so every rule
 * the field declares applies to the value it posts.
 */
class CronEditorField implements ArgumentInterface
{
    public const COMPONENT = 'Hryvinskyi_ConfigurationFields/js/cron-editor';

    /**
     * The class of the form-validation rule the script registers for the expression
     */
    private const VALIDATION_CLASS = 'validate-cron-expression';

    /**
     * The five parts of an expression, in order, with the values that match a time; months and weekdays may also be
     * written by name, of which Magento reads the first three letters
     */
    private const PARTS = [
        'minute' => '0-59',
        'hour' => '0-23',
        'dayOfMonth' => '1-31',
        'month' => '1-12, jan-dec',
        'dayOfWeek' => '0-6, sun-sat',
    ];

    /**
     * @param ElementIdentity $identity
     * @param StoredValue $storedValue
     * @param WidgetMount $widgetMount
     * @param ResolverInterface $localeResolver
     */
    public function __construct(
        private readonly ElementIdentity $identity,
        private readonly StoredValue $storedValue,
        private readonly WidgetMount $widgetMount,
        private readonly ResolverInterface $localeResolver
    ) {
    }

    /**
     * The template's data for one cron field
     *
     * @param AbstractElement $element The field's form element
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
     *     value: string,
     *     valueClass: string,
     *     parts: list<array{part: string, inputId: string, label: string, allowed: string}>,
     *     syntax: list<array{symbol: string, meaning: string}>,
     *     allowedValuesLabel: string
     * }
     */
    public function config(AbstractElement $element): array
    {
        $rootId = $this->identity->rootId($element);
        $inputId = $this->identity->htmlId($element);
        $inheritId = $this->identity->inheritCheckboxId($element);
        $disabled = (bool) $element->getData('disabled');
        $value = $this->storedValue->text($element);
        $wording = [
            'invalidExpression' => (string) __('Invalid cron expression.'),
            'invalidFields' => (string) __('Invalid cron expression. Please check highlighted fields.'),
            'undescribable' => (string) __('This schedule cannot be described in words.'),
            'allowedValues' => (string) __('allowed values'),
            'anyValue' => (string) __('any value'),
            'valueListSeparator' => (string) __('value list separator'),
            'rangeOfValues' => (string) __('range of values'),
            'stepValues' => (string) __('step values'),
            'expressionRequired' => (string) __(
                'This field holds a schedule. Enter a cron expression; an empty value would stop the job from running.'
            ),
        ];

        return [
            'mount' => $this->widgetMount->mount($element, self::COMPONENT, [
                'inputId' => $inputId,
                'inheritId' => $inheritId,
                'disabled' => $disabled,
                'value' => $value,
                'locale' => $this->localeResolver->getLocale(),
                'wording' => $wording,
            ]),
            'inputId' => $inputId,
            'inputName' => $this->identity->name($element),
            'inheritId' => $inheritId,
            'disabled' => $disabled,
            'value' => $value,
            'valueClass' => $this->valueClass($element),
            'parts' => $this->parts($rootId),
            'syntax' => [
                ['symbol' => '*', 'meaning' => $wording['anyValue']],
                ['symbol' => ',', 'meaning' => $wording['valueListSeparator']],
                ['symbol' => '-', 'meaning' => $wording['rangeOfValues']],
                ['symbol' => '/', 'meaning' => $wording['stepValues']],
            ],
            'allowedValuesLabel' => $wording['allowedValues'],
        ];
    }

    /**
     * The classes of the hidden input: the validation rule's class, then the element's own, each once
     *
     * @param AbstractElement $element
     * @return string
     */
    private function valueClass(AbstractElement $element): string
    {
        $elementClass = $element->getData('class');
        $classes = is_string($elementClass) ? preg_split('/\s+/', $elementClass, -1, PREG_SPLIT_NO_EMPTY) : [];

        return implode(' ', array_unique([self::VALIDATION_CLASS, ...($classes !== false ? $classes : [])]));
    }

    /**
     * The five part inputs: their ids, labels and the values each accepts
     *
     * @param non-empty-string $rootId
     * @return list<array{part: string, inputId: string, label: string, allowed: string}>
     */
    private function parts(string $rootId): array
    {
        $labels = [
            'minute' => (string) __('minute'),
            'hour' => (string) __('hour'),
            'dayOfMonth' => (string) __('day (month)'),
            'month' => (string) __('month'),
            'dayOfWeek' => (string) __('day (week)'),
        ];

        $parts = [];
        foreach (self::PARTS as $part => $allowed) {
            $parts[] = [
                'part' => $part,
                'inputId' => $rootId . '-' . $part,
                'label' => $labels[$part],
                'allowed' => $allowed,
            ];
        }

        return $parts;
    }
}
