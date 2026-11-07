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

const tagValues = load('Hryvinskyi_ConfigurationFields/js/tag-values');

const ANY = {uppercase: false, accepts: () => true};
const CODES = {uppercase: true, accepts: tagValues.validator('^[A-Z0-9]+$').accepts};

test('an entry is trimmed, and upper-cased only when the field asks for it', () => {
    assert.equal(tagValues.normalise('  dk-01 ', false), 'dk-01');
    assert.equal(tagValues.normalise('  dk-01 ', true), 'DK-01');
});

test('a paste of postcodes split by line breaks gives one entry per line, with their spaces kept', () => {
    assert.deepEqual(plain(tagValues.splitPasted('SW1A 1AA\nEC1A 1BB', '\n')), ['SW1A 1AA', 'EC1A 1BB']);
});

test('every kind of line break splits a paste, whatever the separator', () => {
    assert.deepEqual(plain(tagValues.splitPasted('a\r\nb\rc\nd', ';')), ['a', 'b', 'c', 'd']);
});

test('a paste never splits on whitespace', () => {
    assert.deepEqual(plain(tagValues.splitPasted('Mozilla/5.0 (X11; Linux) Bot', '\n')), ['Mozilla/5.0 (X11; Linux) Bot']);
});

test('a comma splits a paste only when the separator is a comma', () => {
    const url = 'https://shop.example/a,b';

    assert.deepEqual(plain(tagValues.splitPasted(`${url}\nhttps://shop.example/c`, '\n')), [url, 'https://shop.example/c']);
    assert.deepEqual(plain(tagValues.splitPasted('a,b, c', ',')), ['a', 'b', 'c']);
});

test('a multi-character separator splits before the line breaks do', () => {
    assert.deepEqual(plain(tagValues.splitPasted('a;\nb;\nc', ';\n')), ['a', 'b', 'c']);
});

test('empty entries of a paste are dropped', () => {
    assert.deepEqual(plain(tagValues.splitPasted('\n a \n\n', '\n')), ['a']);
});

test('a valid pattern accepts what it matches and carries no error', () => {
    const validator = tagValues.validator('^[0-9]+$');

    assert.equal(validator.accepts('42'), true);
    assert.equal(validator.accepts('4a'), false);
    assert.equal(validator.error, null);
});

test('a pattern that is not a regular expression accepts every value and carries its error', () => {
    const validator = tagValues.validator('([unclosed');

    assert.equal(validator.accepts('anything at all'), true);
    assert.equal(validator.error.name, 'SyntaxError');
});

test('adding a valid new value returns the list with it at the end, leaving the given list alone', () => {
    const values = ['A1'];
    const result = tagValues.add(values, ' b2 ', CODES);

    assert.deepEqual(plain(result), {outcome: 'added', value: 'B2', values: ['A1', 'B2']});
    assert.deepEqual(values, ['A1']);
});

test('adding an empty entry is its own outcome', () => {
    assert.deepEqual(plain(tagValues.add([], '   ', CODES)), {outcome: 'empty'});
});

test('adding a value the pattern refuses is invalid', () => {
    assert.deepEqual(plain(tagValues.add([], 'a-1', CODES)), {outcome: 'invalid', value: 'A-1'});
});

test('adding a value already listed is a duplicate, compared after normalising', () => {
    assert.deepEqual(plain(tagValues.add(['A1'], 'a1', CODES)), {outcome: 'duplicate', value: 'A1'});
});

test('adding several entries collects what was added, refused as invalid, and refused as a duplicate', () => {
    const result = tagValues.addAll(['A1'], ['b2', 'c-3', 'a1', 'B2', ''], CODES);

    assert.deepEqual(plain(result), {values: ['A1', 'B2'], added: ['B2'], invalid: ['C-3'], duplicate: ['A1', 'B2']});
});

test('removing a value returns the list without it', () => {
    const values = ['a', 'b', 'c'];

    assert.deepEqual(plain(tagValues.remove(values, 'b')), ['a', 'c']);
    assert.deepEqual(values, ['a', 'b', 'c']);
});

test('the list is joined by the separator for the hidden input', () => {
    assert.equal(tagValues.join(['SW1A 1AA', 'EC1A 1BB'], '\n'), 'SW1A 1AA\nEC1A 1BB');
    assert.equal(tagValues.join([], ','), '');
});

test('a search query matches by case-insensitive substring, and an empty query matches everything', () => {
    assert.equal(tagValues.matches('Googlebot', 'BOT'), true);
    assert.equal(tagValues.matches('Googlebot', 'bing'), false);
    assert.equal(tagValues.matches('Googlebot', '  '), true);
});

test('the values the field stores can be shown as stored', () => {
    assert.deepEqual(plain(tagValues.addAll([], ['x', 'x', 'y z'], ANY).values), ['x', 'y z']);
});
