<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ConfigurationFields\Model\Cron;

/**
 * The rules of a cron expression as Magento's cron schedule reads it: the server's half of the cron editor's rule
 * (`view/adminhtml/web/js/cron-fields.js`), part by part, so the editor and the save accept the same expressions
 *
 * - Every part follows the same grammar, the one Magento's scheduler matches each part with: `*`, a value, a range
 *   `a-b`, or a step `x/n` whose start is `*`, a value or a range, and a comma list of any of these. A value is a
 *   number written in digits, or a word whose first three letters, in any case, name a month (`jan` … `dec`) or a
 *   weekday (`sun` … `sat`), the only letters Magento reads (`january`, `Mon`). A step is a number that is not zero:
 *   the scheduler fails on a step of zero every time it runs. A number, a step included, has at most two digits
 *   besides its leading zeros: no part of a schedule counts past 59, and a number too large for an integer makes the
 *   scheduler fail converting it every time it runs. A number outside the calendar but within two digits (a minute
 *   of 61) is accepted: Magento accepts it and never matches it.
 * - Refused, besides what the scheduler fails on: a number with a sign, a fraction or an exponent, a step with an
 *   empty or unreadable start, and an unreadable list entry after one that matches every time, because no cron syntax
 *   writes them.
 * - An expression is split on runs of the whitespace Magento's scheduler splits on (space, tab, line feed, vertical
 *   tab, form feed, carriage return); any other character, a no-break space included, belongs to a part. It is
 *   valid when it is empty (the field holds no schedule), or when it has five valid parts, or six: Magento accepts a
 *   sixth part and never reads it, so the sixth is not checked.
 *
 * Every expression accepted here is one Magento's scheduler runs without an error.
 */
class ExpressionRule
{
    private const PART_COUNT = 5;
    private const WHITESPACE = '/[ \t\n\x0B\f\r]+/';
    private const NUMBER = '/^0*[0-9]{1,2}\z/';
    private const WORD = '/^[a-z]{3,}\z/i';
    private const STEP = '/^0*[1-9][0-9]?\z/';
    private const NAMES = [
        'jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec',
        'sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat',
    ];

    /**
     * Whether a stored expression is acceptable: empty, or five valid parts with or without a sixth
     *
     * @param string $expression
     * @return bool
     */
    public function isValidExpression(string $expression): bool
    {
        $tokens = preg_split(self::WHITESPACE, $expression, -1, PREG_SPLIT_NO_EMPTY);
        if ($tokens === false) {
            return false;
        }
        $count = count($tokens);
        if ($count === 0) {
            return true;
        }
        if ($count !== self::PART_COUNT && $count !== self::PART_COUNT + 1) {
            return false;
        }

        foreach (array_slice($tokens, 0, self::PART_COUNT) as $part) {
            if (!$this->isValidPart($part)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether one part's text is valid
     *
     * @param string $text
     * @return bool
     */
    public function isValidPart(string $text): bool
    {
        foreach (explode(',', $text) as $entry) {
            if (!$this->isValidEntry($entry)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether one entry of a comma list is valid: `*`, a value, a range, or a step on one of these
     *
     * @param string $entry
     * @return bool
     */
    private function isValidEntry(string $entry): bool
    {
        $step = explode('/', $entry);
        if (count($step) > 2) {
            return false;
        }
        if (isset($step[1]) && preg_match(self::STEP, $step[1]) !== 1) {
            return false;
        }

        return $step[0] === '*' || $this->isValueOrRange($step[0]);
    }

    /**
     * Whether a text is a value or a range of two values
     *
     * @param string $text
     * @return bool
     */
    private function isValueOrRange(string $text): bool
    {
        $sides = explode('-', $text);
        if (count($sides) > 2) {
            return false;
        }
        foreach ($sides as $side) {
            if (!$this->isValue($side)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether a text is a value
     *
     * A value is a number of at most two digits besides its leading zeros, or a word Magento reads as a month or a
     * weekday.
     *
     * @param string $text
     * @return bool
     */
    private function isValue(string $text): bool
    {
        if (preg_match(self::NUMBER, $text) === 1) {
            return true;
        }

        return preg_match(self::WORD, $text) === 1
            && in_array(strtolower(substr($text, 0, 3)), self::NAMES, true);
    }
}
