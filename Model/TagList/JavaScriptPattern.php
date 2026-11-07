<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ConfigurationFields\Model\TagList;

/**
 * A tag-list field's `validation` pattern, written as the source of a JavaScript regular expression the browser
 * compiles without flags, as the PCRE pattern that matches the same values on the server
 *
 * - The pattern is delimited by `/`, and every `/` the source leaves unescaped is escaped, so a source holding `/`,
 *   `#` or a backtick compiles as written; an escape the source already wrote (`\/`, `\\`) is copied as it is.
 * - `\uXXXX`, the JavaScript spelling of a character by its code, becomes PCRE's `\x{XXXX}`.
 * - A `.` outside a character class matches what JavaScript's matches: any character but a line feed, a carriage
 *   return and the line and paragraph separators (U+2028, U+2029). PCRE's `.` would also match the last three.
 * - `\s` matches what JavaScript's matches: its white space and line terminators, U+0009 to U+000D, U+0020, U+00A0,
 *   U+1680, U+2000 to U+200A, U+2028, U+2029, U+202F, U+205F, U+3000 and U+FEFF, where PCRE's would match the ASCII
 *   ones only. `\S` matches every other character. Both are written out as that set, inside a character class as well
 *   as outside: a class holding `\S` becomes the alternative of the other characters and the class's other members
 *   (`[\S,]`), and a negated one the whitespace its other members leave (`[^\S\n]`).
 * - Inside a class, a `-` beside `\d`, `\D`, `\w`, `\W`, `\s` or `\S` is a hyphen, as JavaScript reads it without
 *   the `u` flag (`[\d-z]` holds the digits, `-` and `z`), where PCRE would refuse the class; a `^` inside a class that
 *   does not negate it is escaped.
 * - The pattern starts with PCRE's `(*UTF)`, which reads the pattern and the values as UTF-8, so `.` and a character
 *   class count characters, not bytes. It is not PHP's `u` modifier, which also makes `\d`, `\w`, `\s` and `\b` match
 *   letters, digits and spaces of every script, where JavaScript's `\d`, `\w` and `\b` see ASCII ones only. A value
 *   that is not valid UTF-8 matches nothing.
 * - The `D` modifier lets `$` match only at the end of the value, as JavaScript's `$` does without the `m` flag;
 *   PCRE's `$` alone would also match before a final line feed.
 *
 * Other spellings PCRE reads differently from JavaScript are not rewritten; one PCRE cannot compile makes the
 * pattern unusable on the server.
 */
class JavaScriptPattern
{
    private const DELIMITER = '/';
    private const UTF = '(*UTF)';
    private const MODIFIERS = 'D';
    private const CODE_POINT = '/\G[0-9A-Fa-f]{4}/';
    private const ANY_BUT_LINE_TERMINATOR = '[^\n\r\x{2028}\x{2029}]';
    private const SPACE = '\s';
    private const NOT_SPACE = '\S';

    /**
     * The escapes that stand for a set of characters inside a class, which never end a range in JavaScript
     */
    private const CLASS_ESCAPES = ['\d', '\D', '\w', '\W', self::SPACE, self::NOT_SPACE];

    /**
     * The characters JavaScript's `\s` matches, as the members of a PCRE character class
     */
    private const WHITESPACE = '\x{9}-\x{D}\x{20}\x{A0}\x{1680}\x{2000}-\x{200A}\x{2028}\x{2029}\x{202F}\x{205F}'
        . '\x{3000}\x{FEFF}';

    /**
     * The PCRE pattern, delimiters and modifiers included, for a JavaScript regular expression's source
     *
     * @param string $source
     * @return string
     */
    public function toPcre(string $source): string
    {
        $tokens = $this->tokens($source);
        $count = count($tokens);
        $pcre = '';
        for ($index = 0; $index < $count; $index++) {
            if ($tokens[$index] !== '[') {
                $pcre .= $this->outsideClass($tokens[$index]);
                continue;
            }
            $end = array_search(']', array_slice($tokens, $index + 1), true);
            $body = array_slice($tokens, $index + 1, is_int($end) ? $end : null);
            $pcre .= is_int($end) ? $this->characterClass($body) : $this->unterminatedClass($body);
            $index += is_int($end) ? $end + 1 : $count;
        }

        return self::DELIMITER . self::UTF . $pcre . self::DELIMITER . self::MODIFIERS;
    }

    /**
     * The source split into its characters and escapes
     *
     * An escape is a backslash with the character after it, or `\uXXXX` already spelled `\x{XXXX}`.
     *
     * @param string $source
     * @return list<string>
     */
    private function tokens(string $source): array
    {
        $tokens = [];
        $length = strlen($source);
        for ($index = 0; $index < $length; $index++) {
            if ($source[$index] !== '\\') {
                $tokens[] = $source[$index];
                continue;
            }
            if (($source[$index + 1] ?? '') === 'u'
                && preg_match(self::CODE_POINT, $source, $codePoint, 0, $index + 2) === 1
            ) {
                $tokens[] = '\\x{' . $codePoint[0] . '}';
                $index += 5;
                continue;
            }
            $tokens[] = substr($source, $index, 2);
            $index++;
        }

        return $tokens;
    }

    /**
     * A character or an escape outside a character class as PCRE spells it
     *
     * @param string $token
     * @return string
     */
    private function outsideClass(string $token): string
    {
        return match ($token) {
            self::DELIMITER => '\\' . self::DELIMITER,
            '.' => self::ANY_BUT_LINE_TERMINATOR,
            self::SPACE => '[' . self::WHITESPACE . ']',
            self::NOT_SPACE => '[^' . self::WHITESPACE . ']',
            default => $token,
        };
    }

    /**
     * A character class, given the characters and escapes between its brackets, as PCRE spells it
     *
     * PCRE cannot hold the complement of a set of its own inside a class, so a class holding `\S` is written as the
     * alternative of the characters that are not whitespace and the class's other members, and a negated one as the
     * whitespace none of its other members match.
     *
     * @param list<string> $body
     * @return string
     */
    private function characterClass(array $body): string
    {
        $negated = ($body[0] ?? '') === '^';
        $members = $negated ? array_slice($body, 1) : $body;
        $notSpace = in_array(self::NOT_SPACE, $members, true);
        $others = $this->members($members);

        if (!$notSpace) {
            return '[' . ($negated ? '^' : '') . $others . ']';
        }
        if ($others === '') {
            return $negated ? '[' . self::WHITESPACE . ']' : '[^' . self::WHITESPACE . ']';
        }

        return $negated
            ? '(?:(?![' . $others . '])[' . self::WHITESPACE . '])'
            : '(?:[^' . self::WHITESPACE . ']|[' . $others . '])';
    }

    /**
     * A class whose closing bracket the source never writes, kept unterminated
     *
     * PCRE refuses it, as the browser's engine does.
     *
     * @param list<string> $body
     * @return string
     */
    private function unterminatedClass(array $body): string
    {
        return '[' . implode('', array_map(
            static fn (string $token): string => $token === self::DELIMITER ? '\\' . self::DELIMITER : $token,
            $body
        ));
    }

    /**
     * The members of a character class other than `\S`, as PCRE spells them
     *
     * @param list<string> $members The characters and escapes between the brackets, without a negating `^`
     * @return string
     */
    private function members(array $members): string
    {
        $pcre = '';
        foreach ($members as $position => $token) {
            if ($token === self::NOT_SPACE) {
                continue;
            }
            $besideClassEscape = in_array($members[$position - 1] ?? '', self::CLASS_ESCAPES, true)
                || in_array($members[$position + 1] ?? '', self::CLASS_ESCAPES, true);
            $pcre .= match (true) {
                $token === self::SPACE => self::WHITESPACE,
                $token === '-' && $besideClassEscape => '\\-',
                $token === '^' => '\\^',
                $token === self::DELIMITER => '\\' . self::DELIMITER,
                default => $token,
            };
        }

        return $pcre;
    }
}
