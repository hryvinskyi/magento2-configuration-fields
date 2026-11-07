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
 * The names under which a configuration field's form element appears in the page and in the log
 *
 * Magento's element returns its html id and its name already escaped for HTML, and prints them that way in its own
 * markup (the field's label, the "Use system value" checkbox). The id and the name here are the values the browser
 * reads back from that markup, so a template escapes them once more for its attributes and every id it prints matches
 * the ids Magento prints around it.
 */
class ElementIdentity
{
    private const ROOT_ID_PREFIX = 'hcf-';
    private const ROOT_ID_HASH_LENGTH = 16;
    private const INHERIT_CHECKBOX_SUFFIX = '_inherit';

    /**
     * The id of the element that holds the field's value, as the browser reads it
     *
     * @param AbstractElement $element
     * @return string
     */
    public function htmlId(AbstractElement $element): string
    {
        return $this->unescape($element->getHtmlId());
    }

    /**
     * The name under which the field's value is posted, as the browser reads it
     *
     * @param AbstractElement $element
     * @return string
     */
    public function name(AbstractElement $element): string
    {
        return $this->unescape($element->getName());
    }

    /**
     * The id of the "Use system value" checkbox Magento prints beside a field that can inherit its value
     *
     * @param AbstractElement $element
     * @return string
     */
    public function inheritCheckboxId(AbstractElement $element): string
    {
        return $this->htmlId($element) . self::INHERIT_CHECKBOX_SUFFIX;
    }

    /**
     * An id for the widget's own root element, derived from the field's html id
     *
     * The html id may hold characters that break a CSS selector; this id holds only letters, digits and hyphens, and
     * is the same every time for the same html id.
     *
     * @param AbstractElement $element
     * @return non-empty-string
     */
    public function rootId(AbstractElement $element): string
    {
        return self::ROOT_ID_PREFIX
            . substr(hash('sha256', $this->htmlId($element)), 0, self::ROOT_ID_HASH_LENGTH);
    }

    /**
     * The configuration path of the field, for messages about it; the html id when the element carries no path
     *
     * @param AbstractElement $element
     * @return string
     */
    public function configPath(AbstractElement $element): string
    {
        $fieldConfig = $element->getData('field_config');
        if (!is_array($fieldConfig)) {
            return $this->htmlId($element);
        }

        $configPath = $fieldConfig['config_path'] ?? null;
        if (is_string($configPath) && $configPath !== '') {
            return $configPath;
        }

        $groupPath = $fieldConfig['path'] ?? null;
        $fieldId = $fieldConfig['id'] ?? null;
        if (is_string($groupPath) && is_string($fieldId) && $groupPath !== '' && $fieldId !== '') {
            return $groupPath . '/' . $fieldId;
        }

        return $this->htmlId($element);
    }

    /**
     * The text an escaped element attribute stands for
     *
     * @param mixed $escaped
     * @return string
     */
    private function unescape(mixed $escaped): string
    {
        if (!is_scalar($escaped)) {
            return '';
        }

        return htmlspecialchars_decode((string) $escaped, ENT_QUOTES);
    }
}
