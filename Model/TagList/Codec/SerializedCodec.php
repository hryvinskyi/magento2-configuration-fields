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
 * The values as a PHP-serialized list of strings, the bytes PHP's `serialize()` writes for a list
 *
 * Reading never instantiates a class: the serializer this codec is given must unserialize with classes disallowed,
 * which turns an object into an incomplete-class placeholder instead of failing. Only an array is accepted, and only
 * its text and number entries are kept, numbers as their text; nested arrays, objects, booleans and nulls are
 * dropped.
 *
 * A serialized list always starts with `a:` and ends with `}`, so anything else is not read at all. A value that has
 * that shape and is still corrupted is refused by the serializer, which reports it as an
 * `\InvalidArgumentException`; on Magento releases whose serializer catches only notices, PHP 8.3 and later report
 * the corruption as a warning, which Magento's error handler turns into an `\ErrorException`. Neither can be read,
 * and neither can a list none of whose entries is a value. `a:0:{}`, the empty list, reads as an empty list.
 */
class SerializedCodec implements TagListCodecInterface
{
    /**
     * @param SerializerInterface $serializer A PHP serializer that unserializes with classes disallowed
     * @param ValueListNormalizer $normalizer
     */
    public function __construct(
        private readonly SerializerInterface $serializer,
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

        $serialized = $this->serializer->serialize($values);
        if (!is_string($serialized)) {
            throw new LocalizedException(__('The tag list values cannot be saved in the serialized format.'));
        }

        return $serialized;
    }

    /**
     * @inheritDoc
     */
    public function decode(string $stored, string $separator): ?array
    {
        $serialized = trim($stored);
        if ($serialized === '') {
            return [];
        }
        if (!str_starts_with($serialized, 'a:') || !str_ends_with($serialized, '}')) {
            return null;
        }

        try {
            $decoded = $this->serializer->unserialize($serialized);
        } catch (\Exception) {
            // The serializer's own \InvalidArgumentException, or the \ErrorException Magento's error handler raises
            // for a warning the serializer does not catch: either way the value is not a readable list
            return null;
        }

        return is_array($decoded) ? $this->normalizer->readList($decoded) : null;
    }
}
