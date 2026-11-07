<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ConfigurationFields\Test\Unit\Model\TagList;

use Hryvinskyi\ConfigurationFields\Model\TagList\Codec\JsonCodec;
use Hryvinskyi\ConfigurationFields\Model\TagList\Codec\SeparatorCodec;
use Hryvinskyi\ConfigurationFields\Model\TagList\CodecPool;
use Hryvinskyi\ConfigurationFields\Model\TagList\ValueListNormalizer;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\TestCase;

/**
 * Storage formats by save type; an unregistered save type fails loudly
 */
class CodecPoolTest extends TestCase
{
    public function testReturnsTheFormatRegisteredUnderTheSaveType(): void
    {
        $json = new JsonCodec(new Json(), new ValueListNormalizer());
        $separator = new SeparatorCodec(new ValueListNormalizer());
        $pool = new CodecPool(['json' => $json, 'separator' => $separator]);

        self::assertSame($json, $pool->forSaveType('json'));
        self::assertSame($separator, $pool->forSaveType('separator'));
    }

    public function testAnUnknownSaveTypeNamesItAndTheRegisteredOnes(): void
    {
        $pool = new CodecPool([
            'json' => new JsonCodec(new Json(), new ValueListNormalizer()),
            'separator' => new SeparatorCodec(new ValueListNormalizer()),
        ]);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage(
            'The tag list save type "JSON" is not registered. Registered save types: json, separator.'
        );

        $pool->forSaveType('JSON');
    }

    public function testAnItemThatIsNotACodecIsNotAFormat(): void
    {
        $pool = new CodecPool(['json' => 'not a codec', 'separator' => new SeparatorCodec(new ValueListNormalizer())]);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('"json" is not registered. Registered save types: separator.');

        $pool->forSaveType('json');
    }
}
