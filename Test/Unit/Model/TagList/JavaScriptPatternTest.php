<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ConfigurationFields\Test\Unit\Model\TagList;

use Hryvinskyi\ConfigurationFields\Model\TagList\JavaScriptPattern;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A JavaScript regular expression's source as the PCRE pattern that accepts and refuses the same values
 */
class JavaScriptPatternTest extends TestCase
{
    /**
     * @var JavaScriptPattern
     */
    private JavaScriptPattern $pattern;

    protected function setUp(): void
    {
        $this->pattern = new JavaScriptPattern();
    }

    /**
     * @param string $source
     * @param string $pcre
     * @return void
     */
    #[DataProvider('translations')]
    public function testTranslatesTheSource(string $source, string $pcre): void
    {
        self::assertSame($pcre, $this->pattern->toPcre($source));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function translations(): array
    {
        return [
            'no delimiter in the source' => ['^[A-Z]+$', '/(*UTF)^[A-Z]+$/D'],
            'an unescaped slash is escaped' => ['^[A-Za-z0-9_*/]+$', '/(*UTF)^[A-Za-z0-9_*\/]+$/D'],
            'an escaped slash stays as written' => ['^a\/b$', '/(*UTF)^a\/b$/D'],
            'an escaped backslash before a slash' => ['^a\\\\/b$', '/(*UTF)^a\\\\\/b$/D'],
            'a code point' => ['^\u00C4$', '/(*UTF)^\x{00C4}$/D'],
            'a u that is not a code point' => ['^\uZZ$', '/(*UTF)^\uZZ$/D'],
            'a dot outside a class' => ['^a.b$', '/(*UTF)^a[^\n\r\x{2028}\x{2029}]b$/D'],
            'a dot inside a class stays a dot' => ['^[.a]$', '/(*UTF)^[.a]$/D'],
            'an escaped dot stays escaped' => ['^a\\.b$', '/(*UTF)^a\\.b$/D'],
            'a trailing backslash is kept' => ['a\\', '/(*UTF)a\\/D'],
        ];
    }

    /**
     * @param string $source
     * @param string $value
     * @param bool $matches
     * @return void
     */
    #[DataProvider('decisions')]
    public function testTheTranslationDecidesAsJavaScriptDoes(string $source, string $value, bool $matches): void
    {
        self::assertSame($matches ? 1 : 0, preg_match($this->pattern->toPcre($source), $value));
    }

    /**
     * Sources holding `/`, `#` and a backtick, a dollar before a final line feed, and a class counting characters
     *
     * @return array<string, array{string, string, bool}>
     */
    public static function decisions(): array
    {
        $default = '^[a-zA-Z0-9.!#$%&\'*+\/=?^_`"{|}~-]+$';

        return [
            'the default pattern accepts its characters' => [$default, 'a.b#c`d/e', true],
            'the default pattern refuses a space' => [$default, 'a b', false],
            'a slash in a class' => ['^[A-Za-z0-9_*/]+$', 'catalog/product/*', true],
            'dollar does not match before a final line feed' => ['^[a-z]+$', "abc\n", false],
            'a class counts characters, not bytes' => ['^.$', 'Ä', true],
            'a code point matches its character' => ['^\u00C4$', 'Ä', true],
            'a dot does not match a carriage return' => ['^.+$', "Bot\rName", false],
            'a dot does not match a line separator' => ['^.+$', "Bot\u{2028}Name", false],
            'a dot matches a vertical tab, as in JavaScript' => ['^.+$', "Bot\x0BName", true],
            'a dot inside a class matches only a dot' => ['^[.]$', 'x', false],
            'digits are ASCII digits' => ['^\d+$', '١٢', false],
            'a word character is an ASCII one' => ['^\w+$', 'é', false],
            'a word boundary sees ASCII word characters only' => ['^\bé$', 'é', false],
            'a path pattern refuses a space' => ['^[^\s,]+$', 'a b', false],
            'a path pattern refuses a no-break space' => ['^[^\s,]+$', "a\u{00A0}b", false],
            'a path pattern refuses a line separator' => ['^[^\s,]+$', "a\u{2028}b", false],
            'a path pattern refuses a byte order mark' => ['^[^\s,]+$', "a\u{FEFF}b", false],
            'a path pattern refuses a comma' => ['^[^\s,]+$', 'a,b', false],
            'a path pattern accepts a path' => ['^[^\s,]+$', 'customer/section/load', true],
            'a postcode pattern accepts a space' => ['^[A-Z0-9\s]+$', 'SW1A 1AA', true],
            'a postcode pattern accepts a no-break space' => ['^[A-Z0-9\s]+$', "SW1A\u{00A0}1AA", true],
            'a postcode pattern accepts an ideographic space' => ['^[A-Z0-9\s]+$', "SW1A\u{3000}1AA", true],
            'a postcode pattern refuses a dash' => ['^[A-Z0-9\s]+$', 'SW1A-1AA', false],
            'a non-whitespace class refuses a no-break space' => ['^\S+$', "a\u{00A0}b", false],
            'a non-whitespace class refuses a byte order mark' => ['^\S+$', "a\u{FEFF}b", false],
            'a non-whitespace class accepts letters of any script' => ['^\S+$', 'Äé١', true],
            'next line is not whitespace to JavaScript' => ['^\S$', "\u{0085}", true],
            'the Mongolian vowel separator is not whitespace to JavaScript' => ['^\S$', "\u{180E}", true],
            'a zero-width space is not whitespace to JavaScript' => ['^\s$', "\u{200B}", false],
            'whitespace or not, inside a class, is any character' => ['^[\s\S]+$', "a \n\u{2028}é", true],
            'neither whitespace nor not, inside a negated class, is no character' => ['^[^\s\S]$', ' ', false],
            'non-whitespace beside another member of a class' => ['^[\S,]+$', 'a,b', true],
            'non-whitespace beside another member refuses whitespace' => ['^[\S,]+$', "a\u{00A0}b", false],
            'a negated class of non-whitespace and a line feed: whitespace but a line feed' => [
                '^[^\S\n]+$', " \t\u{3000}", true,
            ],
            'a negated class of non-whitespace and a line feed refuses a line feed' => ['^[^\S\n]+$', "\n", false],
            'a negated class of non-whitespace and a line feed refuses a letter' => ['^[^\S\n]+$', 'a', false],
            'a hyphen after whitespace in a class is a hyphen' => ['^[\s-z]+$', ' -z', true],
            'a hyphen after whitespace in a class is not a range' => ['^[\s-z]+$', 'a', false],
            'a hyphen before whitespace in a class is a hyphen' => ['^[a-\s]+$', 'a- ', true],
            'a hyphen before whitespace in a class is not a range' => ['^[a-\s]+$', 'b', false],
            'a hyphen after a digit class accepts a digit' => ['^[\d-z]$', '5', true],
            'a hyphen after a digit class is a hyphen' => ['^[\d-z]$', '-', true],
            'a hyphen after a digit class leaves the character after it' => ['^[\d-z]$', 'z', true],
            'a hyphen after a digit class is not a range' => ['^[\d-z]$', 'a', false],
            'a hyphen between a word class and a dot' => ['^[\w-.]+$', 'a-b.c', true],
            'a hyphen before a word class accepts word characters' => ['^[a-\w]+$', 'a-_9', true],
            'a hyphen before a word class is a hyphen' => ['^[a-\w]+$', '-', true],
            'a hyphen after a non-word class accepts punctuation' => ['^[\W-a]$', '!', true],
            'a hyphen after a non-word class leaves the character after it' => ['^[\W-a]$', 'a', true],
            'a hyphen after a non-word class is not a range' => ['^[\W-a]$', 'b', false],
            'a hyphen after a digit class in a negated class is refused' => ['^[^\d-z]$', '-', false],
            'a hyphen after a digit class in a negated class leaves other letters' => ['^[^\d-z]$', 'a', true],
            'a hyphen after a non-digit class leaves the digit after it' => ['^[\D-1]$', '1', true],
            'a hyphen after a non-digit class is not a range' => ['^[\D-1]$', '2', false],
            'a hyphen after a non-digit class is a hyphen' => ['^[\D-1]$', '-', true],
        ];
    }

    /**
     * @param string $character
     * @return void
     */
    #[DataProvider('javaScriptWhitespace')]
    public function testWhitespaceIsTheWhitespaceOfJavaScript(string $character): void
    {
        self::assertSame(1, preg_match($this->pattern->toPcre('^\s$'), $character));
        self::assertSame(1, preg_match($this->pattern->toPcre('^[\s]$'), $character));
        self::assertSame(0, preg_match($this->pattern->toPcre('^\S$'), $character));
        self::assertSame(0, preg_match($this->pattern->toPcre('^[^\s]$'), $character));
        self::assertSame(0, preg_match($this->pattern->toPcre('^[\S]$'), $character));
    }

    /**
     * Every character a JavaScript `\s` matches without flags: its white space and its line terminators
     *
     * @return array<string, array{string}>
     */
    public static function javaScriptWhitespace(): array
    {
        $codePoints = [
            0x09, 0x0A, 0x0B, 0x0C, 0x0D, 0x20, 0xA0, 0x1680,
            0x2000, 0x2001, 0x2002, 0x2003, 0x2004, 0x2005, 0x2006, 0x2007, 0x2008, 0x2009, 0x200A,
            0x2028, 0x2029, 0x202F, 0x205F, 0x3000, 0xFEFF,
        ];

        return array_combine(
            array_map(static fn (int $codePoint): string => sprintf('U+%04X', $codePoint), $codePoints),
            array_map(static fn (int $codePoint): array => [(string) mb_chr($codePoint, 'UTF-8')], $codePoints)
        );
    }
}
