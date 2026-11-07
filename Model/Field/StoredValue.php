<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ConfigurationFields\Model\Field;

use Magento\Framework\Data\Form\Element\AbstractElement;

/**
 * The value stored for a configuration field, as text a template can escape and a serializer can write
 *
 * A value that is not valid UTF-8 (written by an import, a direct database edit or a command-line save) has every
 * malformed byte sequence replaced by mbstring's substitute character (`?` by default). The attribute escaper and the
 * JSON serializer throw on such bytes, and one exception while a field renders leaves the whole configuration section
 * empty. The form posts what it shows, so the next save of the section stores the replacement.
 */
class StoredValue
{
    /**
     * The stored value as valid UTF-8 text; an empty string when the element holds no text or number
     *
     * @param AbstractElement $element
     * @return string
     */
    public function text(AbstractElement $element): string
    {
        $value = $element->getData('value');

        return is_scalar($value) ? mb_scrub((string) $value, 'UTF-8') : '';
    }

    /**
     * A stored list as its entries joined by the separator, as the field posts it
     *
     * A field whose backend model hands the element a list (an array) is read as its text and number entries, joined;
     * any other entry is left out. Stored text is read as `text()` reads it.
     *
     * @param AbstractElement $element
     * @param string $separator
     * @return string
     */
    public function joined(AbstractElement $element, string $separator): string
    {
        $value = $element->getData('value');
        if (!is_array($value)) {
            return $this->text($element);
        }

        $entries = [];
        foreach ($value as $entry) {
            if (is_scalar($entry)) {
                $entries[] = (string) $entry;
            }
        }

        return mb_scrub(implode($separator, $entries), 'UTF-8');
    }
}
