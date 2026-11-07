<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ConfigurationFields\ViewModel;

use Hryvinskyi\ConfigurationFields\Api\CategoryOptionSourceInterface;
use Hryvinskyi\ConfigurationFields\Model\Field\ElementIdentity;
use Hryvinskyi\ConfigurationFields\Model\Field\ScopeStore;
use Hryvinskyi\ConfigurationFields\Model\Field\StoredValue;
use Hryvinskyi\ConfigurationFields\Model\Field\WidgetMount;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * What the category-select template prints for one field, and the config its script mounts with
 *
 * Stateless: one instance serves every category select of a page, so nothing about a field is kept between calls.
 *
 * A `select` field holds one category id, a `multiselect` field ids joined by commas; the hidden input holds the
 * stored value as stored, and the script posts what the person picks in the same form. Magento builds both fields as
 * elements of type `select`, and marks the multiselect's with the extended type `multiple`. The categories are
 * labelled in the store view the configuration page's scope shows. Any other element type gets no widget: the
 * template prints the notice this view model words, and the field posts nothing.
 */
class UiSelectField implements ArgumentInterface
{
    public const COMPONENT = 'Hryvinskyi_ConfigurationFields/js/ui-select-field';

    /**
     * The element type of Magento's select and multiselect elements, the only one the widget draws
     */
    private const SELECT_TYPE = 'select';

    /**
     * The extended type that marks a select element as taking several values
     */
    private const MULTIPLE_EXT_TYPE = 'multiple';

    /**
     * Joins the ids of a multiselect field, as stored and as posted
     */
    private const ID_SEPARATOR = ',';

    /**
     * Ends the name of the select component, which is the widget's root id with underscores for hyphens: letters,
     * digits and underscores only, so the name reads the same in a binding, a script string and the registry
     */
    private const COMPONENT_NAME_SUFFIX = '_select';

    /**
     * @param ElementIdentity $identity
     * @param StoredValue $storedValue
     * @param WidgetMount $widgetMount
     * @param ScopeStore $scopeStore
     * @param CategoryOptionSourceInterface $categories
     */
    public function __construct(
        private readonly ElementIdentity $identity,
        private readonly StoredValue $storedValue,
        private readonly WidgetMount $widgetMount,
        private readonly ScopeStore $scopeStore,
        private readonly CategoryOptionSourceInterface $categories
    ) {
    }

    /**
     * The notice shown in place of the widget for an element type it cannot draw; null for a select or a multiselect
     *
     * @param AbstractElement $element
     * @return string|null
     */
    public function unsupportedNotice(AbstractElement $element): ?string
    {
        $type = $this->type($element);
        if ($type === self::SELECT_TYPE) {
            return null;
        }

        return (string) __(
            'The field type "%1" cannot be drawn as a category select. Use "select" or "multiselect".',
            $type
        );
    }

    /**
     * The template's data for one select or multiselect field
     *
     * @param AbstractElement $element The field's form element
     * @return array{
     *     mount: array{
     *         rootId: non-empty-string,
     *         init: array<non-empty-string, array<string, array<string, mixed>>>,
     *         initJson: string
     *     },
     *     componentName: non-empty-string,
     *     inputId: string,
     *     inputName: string,
     *     disabled: bool,
     *     value: string
     * }
     * @throws \InvalidArgumentException When the element is neither a select nor a multiselect
     */
    public function config(AbstractElement $element): array
    {
        $type = $this->type($element);
        if ($type !== self::SELECT_TYPE) {
            throw new \InvalidArgumentException(
                sprintf('A category select draws a select or a multiselect element, not "%s".', $type)
            );
        }

        $componentName = str_replace('-', '_', $this->identity->rootId($element)) . self::COMPONENT_NAME_SUFFIX;
        $inputId = $this->identity->htmlId($element);
        $disabled = (bool) $element->getData('disabled');
        $value = $this->storedValue->joined($element, self::ID_SEPARATOR);

        return [
            'mount' => $this->widgetMount->mount($element, self::COMPONENT, [
                'componentName' => $componentName,
                'inputId' => $inputId,
                'inheritId' => $this->identity->inheritCheckboxId($element),
                'disabled' => $disabled,
                'multiple' => $element->getData('ext_type') === self::MULTIPLE_EXT_TYPE,
                'value' => $value,
                'categories' => $this->categories->categories($this->scopeStore->storeId($element)),
            ]),
            'componentName' => $componentName,
            'inputId' => $inputId,
            'inputName' => $this->identity->name($element),
            'disabled' => $disabled,
            'value' => $value,
        ];
    }

    /**
     * The element's type, as text
     *
     * @param AbstractElement $element
     * @return string
     */
    private function type(AbstractElement $element): string
    {
        return (string) $element->getType();
    }
}
