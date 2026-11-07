<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ConfigurationFields\ViewModel;

use Hryvinskyi\ConfigurationFields\Api\TagListCodecInterface;
use Hryvinskyi\ConfigurationFields\Api\TagListCodecPoolInterface;
use Hryvinskyi\ConfigurationFields\Model\Field\ElementIdentity;
use Hryvinskyi\ConfigurationFields\Model\Field\StoredValue;
use Hryvinskyi\ConfigurationFields\Model\Field\WidgetMount;
use Hryvinskyi\ConfigurationFields\Model\TagList\Settings;
use Hryvinskyi\ConfigurationFields\Model\TagList\SettingsReader;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Psr\Log\LoggerInterface;

/**
 * What the tag-list template prints for one field, and the config its script mounts with
 *
 * Stateless: one instance serves every tag-list field of a page, so nothing about a field is kept between calls.
 *
 * The stored value is read through the storage format the field's save type names. A save type no format is
 * registered under does not stop the page: it is logged with the field's path and the value is read as values joined
 * by the separator, which is how unknown save types were always read. Saving such a field is refused by the backend
 * model.
 *
 * The hidden input holds the decoded values joined by the separator, the form in which the browser posts the list;
 * an empty list in the field's format (`[]`, `a:0:{}`) posts an empty value. A stored value its format cannot read
 * (values joined by a newline in a `json` field, as `config.xml` or `config:set` may write it, a corrupted value, or
 * a list none of whose entries is a value) is never replaced: the hidden input carries it unchanged, so saving the
 * section keeps it, and the backend model re-encodes it as values split by the separator. The widget then shows no
 * chips and a notice, and the field is logged with its path and save type. Its script writes the hidden input only
 * when the list is edited. A stored value that is not valid UTF-8 is read with its
 * malformed bytes replaced, so the attribute escaper and the JSON serializer accept it.
 */
class TagListField implements ArgumentInterface
{
    public const COMPONENT = 'Hryvinskyi_ConfigurationFields/js/tag-list';

    /**
     * @param ElementIdentity $identity
     * @param StoredValue $storedValue
     * @param WidgetMount $widgetMount
     * @param SettingsReader $settingsReader
     * @param TagListCodecPoolInterface $codecPool
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly ElementIdentity $identity,
        private readonly StoredValue $storedValue,
        private readonly WidgetMount $widgetMount,
        private readonly SettingsReader $settingsReader,
        private readonly TagListCodecPoolInterface $codecPool,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * The template's data for one tag-list field
     *
     * @param AbstractElement $element The field's form element
     * @param array<mixed> $tagListConfig The field block's `tagListConfig`; SettingsReader lists the keys
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
     *     values: list<string>,
     *     value: string,
     *     unread: bool,
     *     unreadNotice: string,
     *     placeholder: string,
     *     inputLabel: string,
     *     searchPlaceholder: string,
     *     searchLabel: string
     * }
     * @throws LocalizedException When the field's separator is empty
     */
    public function config(AbstractElement $element, array $tagListConfig): array
    {
        $settings = $this->settingsReader->read($tagListConfig);
        $stored = $this->storedValue->text($element);
        $decoded = $this->codecFor($element, $settings)->decode($stored, $settings->separator());
        $unread = $decoded === null;
        if ($unread) {
            $this->logUnread($element, $settings);
        }
        $values = $decoded ?? [];
        $value = $unread ? $stored : implode($settings->separator(), $values);
        $unreadNotice = (string) __(
            'The stored value could not be read as a list. It is kept as it is until you add or remove a value here.'
        );
        $disabled = (bool) $element->getData('disabled');
        $inputId = $this->identity->htmlId($element);
        $inheritId = $this->identity->inheritCheckboxId($element);
        $wording = [
            'remove' => (string) __('Remove %1'),
            'duplicate' => (string) __('This value already exists.'),
            'searchPlaceholder' => (string) __('Search values…'),
            'searchLabel' => (string) __('Search values'),
        ];

        return [
            'mount' => $this->widgetMount->mount($element, self::COMPONENT, [
                'inputId' => $inputId,
                'inheritId' => $inheritId,
                'disabled' => $disabled,
                'values' => $values,
                'separator' => $settings->separator(),
                'validation' => $settings->validation(),
                'validationMessage' => $settings->validationMessage(),
                'uppercase' => $settings->uppercase(),
                'wording' => $wording,
            ]),
            'inputId' => $inputId,
            'inputName' => $this->identity->name($element),
            'inheritId' => $inheritId,
            'disabled' => $disabled,
            'values' => $values,
            'value' => $value,
            'unread' => $unread,
            'unreadNotice' => $unreadNotice,
            'placeholder' => $settings->placeholder(),
            'inputLabel' => $this->label($element),
            'searchPlaceholder' => $wording['searchPlaceholder'],
            'searchLabel' => $wording['searchLabel'],
        ];
    }

    /**
     * The storage format of the field's save type, or the separator format when none is registered under it
     *
     * @param AbstractElement $element
     * @param Settings $settings
     * @return TagListCodecInterface
     */
    private function codecFor(AbstractElement $element, Settings $settings): TagListCodecInterface
    {
        try {
            return $this->codecPool->forSaveType($settings->saveType());
        } catch (LocalizedException $exception) {
            $this->logger->warning(
                sprintf(
                    'The tag list field "%s" names the save type "%s", under which no storage format is registered;'
                    . ' its value is read as values joined by the separator.',
                    $this->identity->configPath($element),
                    $settings->saveType()
                ),
                ['exception' => $exception]
            );
        }

        return $this->codecPool->forSaveType(TagListCodecPoolInterface::SAVE_TYPE_SEPARATOR);
    }

    /**
     * Log a stored value that yields no value in the format of the field's save type
     *
     * @param AbstractElement $element
     * @param Settings $settings
     * @return void
     */
    private function logUnread(AbstractElement $element, Settings $settings): void
    {
        $this->logger->warning(sprintf(
            'The stored value of the tag list field "%s" could not be read in the format of the save type "%s";'
            . ' it is kept unchanged until the list is edited.',
            $this->identity->configPath($element),
            $settings->saveType()
        ));
    }

    /**
     * The field's label, which names the text input for assistive technology
     *
     * @param AbstractElement $element
     * @return string
     */
    private function label(AbstractElement $element): string
    {
        $label = $element->getData('label');

        return is_scalar($label) || $label instanceof \Stringable ? (string) $label : '';
    }
}
