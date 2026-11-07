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

const selectValues = load('Hryvinskyi_ConfigurationFields/js/select-values');

test('a multiselect field\'s stored ids are its comma-separated entries, trimmed, without empty ones', () => {
    assert.deepEqual(plain(selectValues.selected('2,3', true)), ['2', '3']);
    assert.deepEqual(plain(selectValues.selected(' 2 , ,3,', true)), ['2', '3']);
    assert.deepEqual(plain(selectValues.selected('', true)), []);
});

test('a select field\'s stored id is the stored text, trimmed, never a list', () => {
    assert.equal(selectValues.selected('3', false), '3');
    assert.equal(selectValues.selected(' 3 ', false), '3');
    assert.equal(selectValues.selected('', false), '');
});

test('a picked list is posted as its ids joined by commas, as it is stored', () => {
    assert.equal(selectValues.posted(['2', '3']), '2,3');
    assert.equal(selectValues.posted([2, 3]), '2,3');
    assert.equal(selectValues.posted([]), '');
});

test('a picked id is posted as it is, and nothing picked as an empty value', () => {
    assert.equal(selectValues.posted('3'), '3');
    assert.equal(selectValues.posted(''), '');
    assert.equal(selectValues.posted(null), '');
    assert.equal(selectValues.posted(undefined), '');
});

test('what a field stores is what it posts back unchanged', () => {
    ['2,3', '7', ''].forEach((stored) => {
        assert.equal(selectValues.posted(selectValues.selected(stored, true)), stored);
        assert.equal(selectValues.posted(selectValues.selected(stored, false)), stored);
    });
});
