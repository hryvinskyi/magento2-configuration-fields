<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ConfigurationFields\Model\TagList;

use Magento\Framework\Exception\LocalizedException;

/**
 * The server's check of a tag list's values against the field's `validation` pattern, deciding as the browser does
 *
 * Each value is upper-cased first when the field asks for it (full Unicode case mapping, as JavaScript's
 * `toUpperCase()`, so `ß` becomes `SS`), then matched against the pattern translated by JavaScriptPattern. The
 * upper-casing serves the match only: the value is stored as posted. A pattern the server cannot compile refuses
 * every save of the field, where the browser accepts every value; a value the pattern refuses refuses the save. Both
 * messages name the field, and the second also the value and the field's own message, because a value stored before
 * this check existed now stops the save of its whole section until it is fixed.
 */
class ValueCheck
{
    /**
     * @param JavaScriptPattern $javaScriptPattern
     */
    public function __construct(
        private readonly JavaScriptPattern $javaScriptPattern
    ) {
    }

    /**
     * Refuse the values when one does not match the field's pattern, or when the pattern cannot be compiled
     *
     * @param Settings $settings The field's settings
     * @param list<string> $values The values about to be stored
     * @param string $fieldName The field's name, for the message
     * @return void
     * @throws LocalizedException When the pattern cannot be compiled, or a value does not match it
     */
    public function assertAccepted(Settings $settings, array $values, string $fieldName): void
    {
        if ($values === []) {
            return;
        }
        $pcre = $this->javaScriptPattern->toPcre($settings->validation());
        if ($this->match($pcre, '') === null) {
            throw new LocalizedException(__(
                'The field "%1" cannot be saved: its validation pattern is not a regular expression the server can'
                . ' check.',
                $fieldName
            ));
        }

        foreach ($values as $value) {
            $candidate = $settings->uppercase() ? mb_strtoupper($value, 'UTF-8') : $value;
            if ($this->match($pcre, $candidate) !== true) {
                throw new LocalizedException(__(
                    'The field "%1" cannot be saved with the value "%2": %3',
                    $fieldName,
                    $value,
                    $settings->validationMessage()
                ));
            }
        }
    }

    /**
     * Whether a value matches a pattern, or null when the pattern cannot be compiled or matching fails
     *
     * A pattern PCRE cannot compile raises a warning, which the error handler of the application would turn into an
     * exception; it is answered with null instead.
     *
     * @param string $pcre
     * @param string $value
     * @return bool|null
     */
    private function match(string $pcre, string $value): ?bool
    {
        set_error_handler(static fn (): bool => true);
        try {
            $result = preg_match($pcre, $value);
        } finally {
            restore_error_handler();
        }

        return $result === false ? null : $result === 1;
    }
}
