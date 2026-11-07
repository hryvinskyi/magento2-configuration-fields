<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ConfigurationFields\Model\TagList;

use Hryvinskyi\ConfigurationFields\Api\TagListCodecPoolInterface;
use Magento\Framework\Exception\LocalizedException;

/**
 * Reads a tag-list field's `tagListConfig` argument, the array a consumer configures on the field block and on the
 * backend model in `di.xml`
 *
 * Keys and defaults:
 * - `placeholder`: "Type value and press Enter..."
 * - `validation`: `^[a-zA-Z0-9.!#$%&'*+\/=?^_`"{|}~-]+$`, letters, digits and the characters
 *   . ! # $ % & ' * + / = ? ^ _ ` " { | } ~ - with no spaces
 * - `validation_message`: when absent or empty, a message listing the characters the default pattern accepts if the
 *   field uses the default pattern, and a neutral one ("This value does not match the format this field accepts.")
 *   if it has a pattern of its own, whose characters this module cannot name
 * - `uppercase`: true; any value is cast to bool, so the string "false" still means true
 * - `separator`: a newline; the spellings `\n`, `\t` and `\r` stand for those characters
 * - `save_type`: `separator`
 *
 * `placeholder` and `validation_message` may arrive as translatable phrases (an item marked `translatable="true"`),
 * which are rendered to text.
 */
class SettingsReader
{
    private const DEFAULT_VALIDATION = '^[a-zA-Z0-9.!#$%&\'*+\/=?^_`"{|}~-]+$';
    private const DEFAULT_SEPARATOR = "\n";

    /**
     * @param SettingsFactory $settingsFactory
     */
    public function __construct(
        private readonly SettingsFactory $settingsFactory
    ) {
    }

    /**
     * The settings a `tagListConfig` array describes, with the defaults for every key it leaves out
     *
     * @param array<mixed> $tagListConfig
     * @return Settings
     * @throws LocalizedException When the configured separator is empty
     */
    public function read(array $tagListConfig): Settings
    {
        $validation = $this->text($tagListConfig, 'validation') ?? self::DEFAULT_VALIDATION;
        $validationMessage = $this->text($tagListConfig, 'validation_message') ?? '';

        return $this->settingsFactory->create([
            'placeholder' => $this->text($tagListConfig, 'placeholder')
                ?? (string) __('Type value and press Enter...'),
            'validation' => $validation,
            'validationMessage' => $validationMessage !== ''
                ? $validationMessage
                : $this->defaultValidationMessage($validation),
            'uppercase' => (bool) ($tagListConfig['uppercase'] ?? true),
            'separator' => $this->unescape($this->text($tagListConfig, 'separator') ?? self::DEFAULT_SEPARATOR),
            'saveType' => $this->text($tagListConfig, 'save_type')
                ?? TagListCodecPoolInterface::SAVE_TYPE_SEPARATOR,
        ]);
    }

    /**
     * The message for a value the pattern refuses, when the field configures none
     *
     * Only the default pattern's characters are known here; naming them for a pattern of the field's own would tell
     * the person to type characters that pattern may refuse.
     *
     * @param string $validation
     * @return string
     */
    private function defaultValidationMessage(string $validation): string
    {
        return $validation === self::DEFAULT_VALIDATION
            ? (string) __('Use letters, digits and . ! # $ % & \' * + / = ? ^ _ ` " { | } ~ - only, without spaces.')
            : (string) __('This value does not match the format this field accepts.');
    }

    /**
     * A configured text, or null when the key is absent or holds something that is not text
     *
     * @param array<mixed> $tagListConfig
     * @param string $key
     * @return string|null
     */
    private function text(array $tagListConfig, string $key): ?string
    {
        $value = $tagListConfig[$key] ?? null;
        if (is_scalar($value) || $value instanceof \Stringable) {
            return (string) $value;
        }

        return null;
    }

    /**
     * Turn the `\n`, `\t` and `\r` spellings into the characters they stand for
     *
     * @param string $separator
     * @return string
     */
    private function unescape(string $separator): string
    {
        return str_replace(['\\n', '\\t', '\\r'], ["\n", "\t", "\r"], $separator);
    }
}
