/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * How a category select's stored value becomes what the select component holds, and what it holds becomes the posted
 * value, with no DOM and no wording
 *
 * - A multiselect field stores ids joined by commas. Its component holds the list of ids: the stored entries,
 *   trimmed, without the empty ones.
 * - A select field stores one id, which its component holds as text, trimmed.
 * - The hidden input posts a list as its ids joined by commas, one id as it is, and nothing picked as an empty value,
 *   so a field posts back what it stores.
 */
define([], function () {
    'use strict';

    var SEPARATOR = ',';

    /**
     * What the component holds for the stored value
     *
     * @param {string} stored
     * @param {boolean} multiple
     * @returns {string[]|string}
     */
    function selected(stored, multiple) {
        var text = String(stored);

        if (!multiple) {
            return text.trim();
        }

        return text.split(SEPARATOR)
            .map(function (entry) {
                return entry.trim();
            })
            .filter(function (entry) {
                return entry !== '';
            });
    }

    /**
     * The posted value for what the component holds
     *
     * @param {*} value
     * @returns {string}
     */
    function posted(value) {
        if (Array.isArray(value)) {
            return value.map(String).join(SEPARATOR);
        }

        return value === null || value === undefined ? '' : String(value);
    }

    return Object.freeze({
        selected: selected,
        posted: posted
    });
});
