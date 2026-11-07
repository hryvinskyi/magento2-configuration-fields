<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ConfigurationFields\Model\TagList\Codec;

use Hryvinskyi\ConfigurationFields\Api\TagListCodecInterface;
use Hryvinskyi\ConfigurationFields\Model\TagList\ValueListNormalizer;

/**
 * The values joined by the field's separator (a newline unless the field says otherwise)
 *
 * This is also the form in which the browser posts a tag list, whatever format the field stores. Every text is a list
 * in this format, so reading never reports a value it cannot read.
 */
class SeparatorCodec implements TagListCodecInterface
{
    /**
     * @param ValueListNormalizer $normalizer
     */
    public function __construct(
        private readonly ValueListNormalizer $normalizer
    ) {
    }

    /**
     * @inheritDoc
     */
    public function encode(array $values, string $separator): string
    {
        return implode($separator, $values);
    }

    /**
     * @inheritDoc
     */
    public function decode(string $stored, string $separator): array
    {
        if ($stored === '') {
            return [];
        }

        return $this->normalizer->normalize(explode($separator, $stored));
    }
}
