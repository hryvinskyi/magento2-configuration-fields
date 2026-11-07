<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ConfigurationFields\Test\Unit\Model\TagList;

use Hryvinskyi\ConfigurationFields\Model\TagList\ValueListNormalizer;
use PHPUnit\Framework\TestCase;

/**
 * What counts as a tag value: a string or a number, as text, trimmed, not empty after trimming
 */
class ValueListNormalizerTest extends TestCase
{
    public function testKeepsTrimmedStringsInOrderAndDropsEverythingElse(): void
    {
        $normalizer = new ValueListNormalizer();

        self::assertSame(
            ['A', 'B', '0'],
            $normalizer->normalize(['key' => ' A ', '', '   ', null, ['C'], 'B', new \stdClass(), "0\n", false, true])
        );
    }

    public function testKeepsNumbersAsText(): void
    {
        $normalizer = new ValueListNormalizer();

        self::assertSame(['8080', '8443'], $normalizer->normalize([8080, 8443]));
        self::assertSame(['1.5', '0', '-3', '42'], $normalizer->normalize([1.5, 0, -3, ' 42 ']));
    }
}
