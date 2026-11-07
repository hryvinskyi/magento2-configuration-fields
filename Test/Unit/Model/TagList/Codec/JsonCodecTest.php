<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ConfigurationFields\Test\Unit\Model\TagList\Codec;

use Hryvinskyi\ConfigurationFields\Model\TagList\Codec\JsonCodec;
use Hryvinskyi\ConfigurationFields\Model\TagList\ValueListNormalizer;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The values as a JSON array, in the bytes earlier releases stored
 */
class JsonCodecTest extends TestCase
{
    /**
     * The codec under test
     *
     * @var JsonCodec
     */
    private JsonCodec $codec;

    protected function setUp(): void
    {
        $this->codec = new JsonCodec(new Json(), new ValueListNormalizer());
    }

    public function testWritesUnicodeAsIsAndKeepsSlashesEscaped(): void
    {
        self::assertSame('["Ä","a\/b"]', $this->codec->encode(['Ä', 'a/b'], "\n"));
    }

    public function testAnEmptyListIsAnEmptyString(): void
    {
        self::assertSame('', $this->codec->encode([], "\n"));
    }

    public function testInvalidUtf8RefusesTheSave(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('not valid UTF-8');

        $this->codec->encode(["\xB1\x31"], "\n");
    }

    public function testRoundTripKeepsEveryValueIncludingZero(): void
    {
        $values = ['A', '0', 'Ä', 'a/b', 'with "quotes"'];

        self::assertSame($values, $this->codec->decode($this->codec->encode($values, "\n"), "\n"));
    }

    public function testDecodingKeepsTrimmedNonEmptyStringsAndNumbersAsText(): void
    {
        self::assertSame(
            ['A', '1', 'B', '0'],
            $this->codec->decode('[" A ", 1, "", null, ["C"], {"x": "y"}, "B", "0"]', ',')
        );
    }

    public function testAListOfNumbersIsReadAsText(): void
    {
        self::assertSame(['8080', '8443'], $this->codec->decode('[8080,8443]', "\n"));
    }

    /**
     * @param string $stored
     * @return void
     */
    #[DataProvider('emptyLists')]
    public function testAnEmptyValueAndAnEmptyListReadAsAnEmptyList(string $stored): void
    {
        self::assertSame([], $this->codec->decode($stored, "\n"));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function emptyLists(): array
    {
        return [
            'empty' => [''],
            'blank' => [" \n "],
            'empty array' => ['[]'],
            'empty array with spaces' => [' [ ] '],
            'empty object' => ['{}'],
        ];
    }

    /**
     * @param string $stored
     * @return void
     */
    #[DataProvider('foreignValues')]
    public function testAValueNotInTheFormatCannotBeRead(string $stored): void
    {
        self::assertNull($this->codec->decode($stored, "\n"));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function foreignValues(): array
    {
        return [
            'separator-joined' => ["A\nB"],
            'truncated' => ['["A","B'],
            'scalar' => ['"A"'],
            'number' => ['42'],
            'null' => ['null'],
            'serialized' => ['a:1:{i:0;s:1:"A";}'],
            'list without a value' => ['[true,null]'],
            'list of empty texts' => ['[""," "]'],
        ];
    }
}
