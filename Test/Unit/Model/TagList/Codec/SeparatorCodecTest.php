<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ConfigurationFields\Test\Unit\Model\TagList\Codec;

use Hryvinskyi\ConfigurationFields\Model\TagList\Codec\SeparatorCodec;
use Hryvinskyi\ConfigurationFields\Model\TagList\ValueListNormalizer;
use PHPUnit\Framework\TestCase;

/**
 * The values joined by the field's separator
 */
class SeparatorCodecTest extends TestCase
{
    /**
     * The codec under test
     *
     * @var SeparatorCodec
     */
    private SeparatorCodec $codec;

    protected function setUp(): void
    {
        $this->codec = new SeparatorCodec(new ValueListNormalizer());
    }

    public function testJoinsTheValuesWithTheSeparator(): void
    {
        self::assertSame("A\nB C\n0", $this->codec->encode(['A', 'B C', '0'], "\n"));
        self::assertSame('A,B', $this->codec->encode(['A', 'B'], ','));
    }

    public function testAnEmptyListIsAnEmptyString(): void
    {
        self::assertSame('', $this->codec->encode([], "\n"));
    }

    public function testRoundTripKeepsEveryValueIncludingZero(): void
    {
        $values = ['SW1A 1AA', '0', 'Ä/ö'];

        self::assertSame($values, $this->codec->decode($this->codec->encode($values, "\n"), "\n"));
    }

    public function testDecodingTrimsAndDropsEmptyEntries(): void
    {
        self::assertSame(['A', 'B', '0'], $this->codec->decode(" A ,,  , B,0,", ','));
        self::assertSame(['A', 'B'], $this->codec->decode("A\r\n\r\nB\r\n", "\n"));
    }

    public function testAnEmptyStoredValueIsAnEmptyList(): void
    {
        self::assertSame([], $this->codec->decode('', "\n"));
    }

    public function testEveryTextIsReadEvenWhenItHoldsOnlySeparators(): void
    {
        self::assertSame([], $this->codec->decode("\n \n", "\n"));
        self::assertSame(['[]'], $this->codec->decode('[]', "\n"));
    }
}
