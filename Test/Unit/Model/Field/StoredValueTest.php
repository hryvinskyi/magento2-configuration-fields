<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ConfigurationFields\Test\Unit\Model\Field;

use Hryvinskyi\ConfigurationFields\Model\Field\StoredValue;
use Hryvinskyi\ConfigurationFields\Test\Unit\Fake\FormElementTestCase;
use Magento\Framework\ZendEscaper;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The stored value of a field as text that escaping and JSON encoding accept
 */
class StoredValueTest extends FormElementTestCase
{
    /**
     * @param mixed $stored
     * @param string $text
     * @return void
     */
    #[DataProvider('storedValues')]
    public function testReadsTheStoredValueAsText(mixed $stored, string $text): void
    {
        self::assertSame($text, (new StoredValue())->text($this->element(['html_id' => 'f', 'value' => $stored])));
    }

    /**
     * @return array<string, array{mixed, string}>
     */
    public static function storedValues(): array
    {
        return [
            'text' => [" A\nÄ ", " A\nÄ "],
            'number' => [42, '42'],
            'absent' => [null, ''],
            'list' => [['A'], ''],
        ];
    }

    /**
     * @param mixed $stored
     * @param string $text
     * @return void
     */
    #[DataProvider('storedLists')]
    public function testReadsAStoredListAsItsEntriesJoinedBySeparator(mixed $stored, string $text): void
    {
        $element = $this->element(['html_id' => 'f', 'value' => $stored]);

        self::assertSame($text, (new StoredValue())->joined($element, ','));
    }

    /**
     * @return array<string, array{mixed, string}>
     */
    public static function storedLists(): array
    {
        return [
            'text, as stored' => ['2, 3', '2, 3'],
            'a list of ids' => [['2', 3], '2,3'],
            'a list with an entry that is not text' => [['2', ['x'], null, '3'], '2,3'],
            'absent' => [null, ''],
        ];
    }

    public function testAStoredListWithBytesThatAreNotUtf8IsReplacedSoEscapingCannotFail(): void
    {
        $text = (new StoredValue())->joined($this->element(['html_id' => 'f', 'value' => ['2', "3\xb1"]]), ',');

        self::assertTrue(mb_check_encoding($text, 'UTF-8'));
        self::assertStringStartsWith('2,3', $text);
    }

    public function testBytesThatAreNotUtf8AreReplacedSoEscapingCannotFail(): void
    {
        $text = (new StoredValue())->text($this->element(['html_id' => 'f', 'value' => "a\xb1b"]));

        self::assertTrue(mb_check_encoding($text, 'UTF-8'));
        self::assertStringStartsWith('a', $text);
        self::assertStringEndsWith('b', $text);
        self::assertNotSame('', (new ZendEscaper())->escapeHtmlAttr($text));
    }
}
