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
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\SerializerInterface;

/**
 * The values as a JSON array of strings
 *
 * Non-ASCII characters are written as they are (`["Ä"]`, not `["\u00c4"]`) and slashes stay escaped (`a\/b`), the
 * bytes earlier releases of this module stored, so a stored value does not change when it is saved again unchanged.
 * Magento's JSON serializer passes no flags to the encoder, so writing goes through PHP's encoder directly; reading
 * goes through the serializer, and keeps the numbers of an array such as `[8080, 8443]` as their text. `[]` and `{}`
 * read as an empty list; text that is not JSON, JSON that is not an array or an object, and an array none of whose
 * entries is a value cannot be read.
 */
class JsonCodec implements TagListCodecInterface
{
    /**
     * @param SerializerInterface $json A JSON serializer
     * @param ValueListNormalizer $normalizer
     */
    public function __construct(
        private readonly SerializerInterface $json,
        private readonly ValueListNormalizer $normalizer
    ) {
    }

    /**
     * @inheritDoc
     */
    public function encode(array $values, string $separator): string
    {
        if ($values === []) {
            return '';
        }

        try {
            return json_encode($values, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new LocalizedException(
                __('A tag list value is not valid UTF-8 text, so the list cannot be saved.'),
                $exception
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function decode(string $stored, string $separator): ?array
    {
        if (trim($stored) === '') {
            return [];
        }

        try {
            $decoded = $this->json->unserialize($stored);
        } catch (\InvalidArgumentException) {
            return null;
        }

        return is_array($decoded) ? $this->normalizer->readList($decoded) : null;
    }
}
