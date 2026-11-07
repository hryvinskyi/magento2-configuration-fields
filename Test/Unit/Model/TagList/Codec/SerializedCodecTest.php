<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ConfigurationFields\Test\Unit\Model\TagList\Codec;

use Hryvinskyi\ConfigurationFields\Model\TagList\Codec\SerializedCodec;
use Hryvinskyi\ConfigurationFields\Model\TagList\ValueListNormalizer;
use Magento\Framework\Serialize\Serializer\Serialize;
use Magento\Framework\Serialize\SerializerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The values as a PHP-serialized list, read without ever instantiating a class
 */
class SerializedCodecTest extends TestCase
{
    /**
     * The codec under test, over Magento's PHP serializer
     *
     * @var SerializedCodec
     */
    private SerializedCodec $codec;

    protected function setUp(): void
    {
        $this->codec = new SerializedCodec(new Serialize(), new ValueListNormalizer());
    }

    public function testWritesWhatPhpSerializeWritesForTheList(): void
    {
        self::assertSame('a:2:{i:0;s:1:"A";i:1;s:2:"Ä";}', $this->codec->encode(['A', 'Ä'], "\n"));
    }

    public function testAnEmptyListIsAnEmptyString(): void
    {
        self::assertSame('', $this->codec->encode([], "\n"));
    }

    public function testReadsAValueEarlierReleasesStored(): void
    {
        self::assertSame(['A', 'B'], $this->codec->decode('a:2:{i:0;s:1:"A";i:1;s:1:"B";}', "\n"));
    }

    public function testRoundTripKeepsEveryValueIncludingZero(): void
    {
        $values = ['A', '0', 'Ä', 'a;b:{c}'];

        self::assertSame($values, $this->codec->decode($this->codec->encode($values, "\n"), "\n"));
    }

    public function testDropsANestedArrayAndAnObjectInsideTheList(): void
    {
        self::assertSame(['A'], $this->codec->decode('a:2:{i:0;s:1:"A";i:1;O:8:"stdClass":0:{}}', "\n"));
        self::assertSame(['A'], $this->codec->decode('a:2:{i:0;s:1:"A";i:1;a:1:{i:0;s:1:"B";}}', "\n"));
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
            'empty array' => ['a:0:{}'],
            'empty array between spaces' => [' a:0:{} '],
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
            'object payload' => ['O:8:"stdClass":0:{}'],
            'truncated' => ['a:2:{i:0;s:1:"A";i:1;s:1:"B'],
            'corrupted inside the list shape' => ['a:2:{i:0;s:5:"A";}'],
            'string payload' => ['s:1:"A";'],
            'json' => ['["A","B"]'],
            'separator-joined' => ["A\nB"],
            'list without a value' => ['a:2:{i:0;b:1;i:1;N;}'],
        ];
    }

    public function testASerializerThatRaisesAnErrorExceptionCannotBeRead(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->method('unserialize')->willThrowException(new \ErrorException('unserialize(): Error at offset'));
        $codec = new SerializedCodec($serializer, new ValueListNormalizer());

        self::assertNull($codec->decode('a:1:{i:0;s:1:"A";}', "\n"));
    }
}
