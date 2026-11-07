<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ConfigurationFields\Model\TagList;

/**
 * Turns whatever a storage format read back into the values of a tag list
 *
 * A tag value is text, trimmed, and not empty after trimming. "0" is a value. A number is read as its text, so a
 * list stored as `[8080, 8443]` reads as the values "8080" and "8443". A boolean, a null, a nested list or an object
 * is not a value and is dropped.
 */
class ValueListNormalizer
{
    /**
     * Keep the text and number entries as text, trimmed, in their order, dropping those that are empty after trimming
     *
     * @param array<mixed> $entries
     * @return list<string>
     */
    public function normalize(array $entries): array
    {
        $values = [];
        foreach ($entries as $entry) {
            if (!is_string($entry) && !is_int($entry) && !is_float($entry)) {
                continue;
            }
            $value = trim((string) $entry);
            if ($value === '') {
                continue;
            }
            $values[] = $value;
        }

        return $values;
    }

    /**
     * The values of a list a storage format parsed, or null when the list has entries and none of them is a value
     *
     * An empty list is a list of no values. A list whose every entry is dropped (`[true, null]`, `["", " "]`) is not
     * read as an empty one: its entries are something other than tag values, and reading it as empty would let the
     * next save of the field erase them.
     *
     * @param array<mixed> $entries
     * @return list<string>|null
     */
    public function readList(array $entries): ?array
    {
        $values = $this->normalize($entries);

        return $values === [] && $entries !== [] ? null : $values;
    }
}
