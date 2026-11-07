/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The category rows the server sends, parsed and nested into the option tree Magento's select component draws, with
 * no DOM and no wording
 *
 * - A row is `{id, parentId, label}`, all three strings. Anything else is not a list of rows, and the caller mounts
 *   nothing rather than draw part of a tree.
 * - A row nests under the row whose id is its parent; a row whose parent is not listed (the catalog's root, which the
 *   server leaves out) is at the top. Siblings keep the order of the rows, whichever comes first, child or parent.
 * - Every node is `{value, label, __disableTmpl: true}`, plus `optgroup` when it has children. `__disableTmpl` stops
 *   Magento's component config from rendering `${ … }` in a node, so a category named like a template stays text.
 *
 * Every function returns new objects; none changes what it is given.
 */
define([], function () {
    'use strict';

    /**
     * @typedef {{id: string, parentId: string, label: string}} Row
     * @typedef {{value: string, label: string, __disableTmpl: boolean, optgroup?: Node[]}} Node
     */

    /**
     * @param {*} row
     * @returns {boolean}
     */
    function isRow(row) {
        return Boolean(row) && typeof row === 'object'
            && typeof row.id === 'string'
            && typeof row.parentId === 'string'
            && typeof row.label === 'string';
    }

    /**
     * The rows, or null when the value is not a list of rows
     *
     * @param {*} value
     * @returns {?Row[]}
     */
    function parseRows(value) {
        if (!Array.isArray(value) || !value.every(isRow)) {
            return null;
        }

        return value.map(function (row) {
            return {id: row.id, parentId: row.parentId, label: row.label};
        });
    }

    /**
     * The option tree of the rows
     *
     * @param {Row[]} rows
     * @returns {Node[]}
     */
    function nest(rows) {
        var nodes = new Map(),
            top = [];

        rows.forEach(function (row) {
            nodes.set(row.id, {value: row.id, label: row.label, __disableTmpl: true});
        });
        rows.forEach(function (row) {
            var node = nodes.get(row.id),
                parent = nodes.get(row.parentId);

            if (!parent || parent === node) {
                top.push(node);

                return;
            }
            parent.optgroup = parent.optgroup || [];
            parent.optgroup.push(node);
        });

        return top;
    }

    return Object.freeze({
        parseRows: parseRows,
        nest: nest
    });
});
