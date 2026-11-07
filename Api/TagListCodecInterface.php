<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ConfigurationFields\Api;

use Magento\Framework\Exception\LocalizedException;

/**
 * One storage format of a tag-list field: how a list of strings is written into one configuration value and read
 * back from it
 *
 * A format is registered in the codec pool under its save type, the name a field's `tagListConfig` gives in
 * `save_type`. Another module adds a format by implementing this interface and adding an item to the pool's
 * `codecs` argument.
 *
 * @api
 */
interface TagListCodecInterface
{
    /**
     * Write the values in this format
     *
     * The values are already trimmed and none of them is empty. An empty list is written as an empty string.
     *
     * @param list<string> $values
     * @param non-empty-string $separator The field's separator; formats that do not join values may ignore it
     * @return string
     * @throws LocalizedException When the values cannot be written in this format; nothing must be stored then
     */
    public function encode(array $values, string $separator): string;

    /**
     * Read the values back from a stored value, or report that it cannot be read in this format
     *
     * Every value comes back trimmed, and a value that is empty after trimming is dropped ("0" is a value). A stored
     * value that is empty or holds only whitespace, and an empty list written in this format, read as an empty list.
     *
     * Null means the stored text is not a list in this format: it does not parse, it parses to something other than
     * a list, or it is a list with entries none of which is a value. A caller then keeps the stored text as it is
     * rather than treating it as an empty list, so that saving the field again never erases it. A format in which
     * every text is a list, such as values joined by a separator, never returns null. This method never throws and
     * never raises a warning.
     *
     * @param string $stored
     * @param non-empty-string $separator The field's separator; formats that do not join values may ignore it
     * @return list<string>|null
     */
    public function decode(string $stored, string $separator): ?array;
}
