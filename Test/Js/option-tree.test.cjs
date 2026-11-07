/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const {load} = require('./amd.cjs');
const {plain} = require('./fake-dom.cjs');

const optionTree = load('Hryvinskyi_ConfigurationFields/js/option-tree');

const row = (id, parentId, label) => ({id, parentId, label});

test('rows nest under their parents, and a row whose parent is not listed is at the top', () => {
    const tree = optionTree.nest([
        row('2', '1', 'Default Category'),
        row('3', '2', 'Gear'),
        row('4', '3', 'Bags'),
        row('5', '1', 'Second Root')
    ]);

    assert.deepEqual(plain(tree), [
        {
            value: '2',
            label: 'Default Category',
            __disableTmpl: true,
            optgroup: [
                {
                    value: '3',
                    label: 'Gear',
                    __disableTmpl: true,
                    optgroup: [{value: '4', label: 'Bags', __disableTmpl: true}]
                }
            ]
        },
        {value: '5', label: 'Second Root', __disableTmpl: true}
    ]);
});

test('a child listed before its parent still nests under it, and siblings keep the order of the rows', () => {
    const tree = optionTree.nest([row('4', '2', 'Second'), row('3', '2', 'First'), row('2', '1', 'Root')]);

    assert.deepEqual(plain(tree[0].optgroup).map((node) => node.value), ['4', '3']);
});

test('every node turns off Magento\'s template rendering, so a label such as ${ $.provider } stays text', () => {
    const tree = optionTree.nest([row('2', '1', '${ $.provider }'), row('3', '2', 'x')]);

    assert.equal(tree[0].__disableTmpl, true);
    assert.equal(tree[0].optgroup[0].__disableTmpl, true);
    assert.equal(tree[0].label, '${ $.provider }');
});

test('no rows make no options', () => {
    assert.deepEqual(plain(optionTree.nest([])), []);
});

test('rows the server sends are accepted only as a list of string ids, parents and labels', () => {
    assert.deepEqual(plain(optionTree.parseRows([row('2', '1', 'A')])), [row('2', '1', 'A')]);
    assert.deepEqual(plain(optionTree.parseRows([])), []);
    assert.equal(optionTree.parseRows(null), null);
    assert.equal(optionTree.parseRows({0: row('2', '1', 'A')}), null);
    assert.equal(optionTree.parseRows([row(2, '1', 'A')]), null);
    assert.equal(optionTree.parseRows([{id: '2', parentId: '1'}]), null);
    assert.equal(optionTree.parseRows(['2']), null);
});
