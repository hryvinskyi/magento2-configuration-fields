/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const {load} = require('./amd.cjs');
const {el, fire, documentOf, plain} = require('./fake-dom.cjs');

const tagList = load('Hryvinskyi_ConfigurationFields/js/tag-list');

const ROOT_ID = 'hcf-0123456789abcdef';
const INPUT_ID = 'section_group_codes';
const INHERIT_ID = 'section_group_codes_inherit';
const CHIP = '[data-role="hcf-tag-list-chip"]';
const CHIP_HEIGHT_PX = 40;

/**
 * The config the server prints for the field
 *
 * @param {Object} [overrides]
 * @returns {Object}
 */
function config(overrides = {}) {
    return {
        inputId: INPUT_ID,
        inheritId: INHERIT_ID,
        disabled: false,
        values: [],
        separator: '\n',
        validation: '^[A-Z0-9 ]+$',
        validationMessage: 'Use letters, digits and spaces.',
        uppercase: true,
        wording: {remove: 'Remove %1', duplicate: 'Already listed.', searchPlaceholder: 'Search…', searchLabel: 'Search'},
        ...overrides
    };
}

/**
 * The field as the template prints it, with its "Use system value" checkbox beside it; each chip is 40 pixels high
 *
 * @param {{stored?: string, disabled?: boolean, inherited?: boolean, withTemplate?: boolean}} [options]
 * @returns {Object} The page's parts, its timers and what it reported
 */
function page({stored = '', disabled = false, inherited = false, withTemplate = true} = {}) {
    const timers = [];
    const reports = [];
    const lock = disabled ? {disabled: ''} : {};
    const template = el('template', {'data-role': 'hcf-tag-list-chip-template'});
    const chips = el('div', {class: 'hcf-tag-list__chips', 'data-role': 'hcf-tag-list-chips'});
    const input = el('input', {type: 'text', 'data-role': 'hcf-tag-list-input', ...lock});
    const search = el('input', {type: 'text', 'data-role': 'hcf-tag-list-search', hidden: ''});
    const error = el('div', {'data-role': 'hcf-tag-list-error', role: 'alert', hidden: ''});
    const hidden = el('input', {type: 'hidden', id: INPUT_ID, 'data-role': 'hcf-tag-list-value', value: stored, ...lock});
    const root = el('div', {
        id: ROOT_ID,
        class: 'hui hcf-tag-list tag-list-root',
        'data-role': 'hcf-tag-list',
        ...(disabled ? {'data-disabled': 'true'} : {})
    }, [search, el('div', {class: 'hcf-tag-list__box'}, [chips, input]), error, ...(withTemplate ? [template] : []), hidden]);
    const inherit = el('input', {type: 'checkbox', id: INHERIT_ID, ...(inherited ? {checked: ''} : {})});

    template.content = {
        firstElementChild: el('span', {class: 'hcf-tag-list__chip', 'data-role': 'hcf-tag-list-chip', 'data-value': ''}, [
            el('span', {class: 'hcf-tag-list__text'}),
            el('button', {type: 'button', 'data-role': 'hcf-tag-list-remove', 'aria-label': ''}, [], '×')
        ])
    };
    Object.defineProperty(chips, 'scrollHeight', {get: () => chips.children.length * CHIP_HEIGHT_PX});
    documentOf([root, inherit], {
        setTimeout: (callback, delay) => timers.push({callback, delay, cleared: false}) - 1,
        clearTimeout: (id) => {
            timers[id].cleared = true;
        },
        console: {error: (...args) => reports.push(args)}
    });

    return {root, chips, input, search, error, hidden, inherit, template, timers, reports};
}

/**
 * Mount the field through its x-magento-init entry
 *
 * @param {Object} parts
 * @param {Object} [overrides] Config keys to change
 * @returns {Object} The parts
 */
function mounted(parts, overrides = {}) {
    tagList(config(overrides), parts.root);

    return parts;
}

/**
 * @param {Object} root
 * @returns {Array<string>}
 */
const shownValues = (root) => root.querySelectorAll(CHIP).map((chip) => chip.getAttribute('data-value'));

/**
 * Type a value and press Enter
 *
 * @param {Object} parts
 * @param {string} text
 * @returns {Object} The keydown event
 */
function enter(parts, text) {
    parts.input.value = text;

    return fire(parts.input, 'keydown', {key: 'Enter'});
}

/**
 * Paste text into the text input
 *
 * @param {Object} parts
 * @param {string} text
 * @returns {Object} The paste event
 */
function paste(parts, text) {
    return fire(parts.input, 'paste', {clipboardData: {getData: (type) => (type === 'text' ? text : '')}});
}

/**
 * @param {Object} root
 * @param {string} value
 * @returns {Object} The chip's remove button
 */
const removeButtonOf = (root, value) => root.querySelector(`[data-value="${value}"]`).querySelector('[data-role="hcf-tag-list-remove"]');

test('mounting shows the stored values as chips and writes nothing to the hidden input', () => {
    const parts = mounted(page({stored: 'as printed'}), {values: ['SW1A 1AA', 'ec1a 1bb']});
    const [first] = parts.root.querySelectorAll(CHIP);

    assert.deepEqual(shownValues(parts.root), ['SW1A 1AA', 'ec1a 1bb']);
    assert.equal(first.querySelector('.hcf-tag-list__text').textContent, 'SW1A 1AA');
    assert.equal(removeButtonOf(parts.root, 'SW1A 1AA').getAttribute('aria-label'), 'Remove SW1A 1AA');
    assert.equal(parts.hidden.value, 'as printed');
    assert.equal(parts.hidden.disabled, false);
});

test('Enter adds the typed value, upper-cased, and writes the list to the hidden input', () => {
    const parts = mounted(page(), {values: ['A1']});
    const event = enter(parts, ' sw1a 1aa ');

    assert.equal(event.defaultPrevented, true);
    assert.deepEqual(shownValues(parts.root), ['A1', 'SW1A 1AA']);
    assert.equal(parts.hidden.value, 'A1\nSW1A 1AA');
    assert.equal(parts.input.value, '');
});

test('a paste of several lines adds each entry with its spaces kept', () => {
    const parts = mounted(page(), {values: ['A1']});
    const event = paste(parts, 'SW1A 1AA\nEC1A 1BB');

    assert.equal(event.defaultPrevented, true);
    assert.deepEqual(shownValues(parts.root), ['A1', 'SW1A 1AA', 'EC1A 1BB']);
    assert.equal(parts.hidden.value, 'A1\nSW1A 1AA\nEC1A 1BB');
});

test('a paste of one entry is left to the browser', () => {
    const parts = mounted(page());
    const event = paste(parts, 'SW1A 1AA');

    assert.equal(event.defaultPrevented, false);
    assert.deepEqual(shownValues(parts.root), []);
});

test('a paste with a refused entry adds the others and leaves the first refused one in the input', () => {
    const parts = mounted(page());

    paste(parts, 'A1\nnot-valid\nB2');

    assert.deepEqual(shownValues(parts.root), ['A1', 'B2']);
    assert.equal(parts.input.value, 'NOT-VALID');
    assert.equal(parts.input.getAttribute('aria-invalid'), 'true');
    assert.equal(parts.error.textContent, 'Use letters, digits and spaces.');
});

test('a duplicate shows the duplicate message and adds nothing', () => {
    const parts = mounted(page({stored: 'A1'}), {values: ['A1']});

    enter(parts, 'a1');

    assert.deepEqual(shownValues(parts.root), ['A1']);
    assert.equal(parts.hidden.value, 'A1');
    assert.equal(parts.error.textContent, 'Already listed.');
    assert.equal(parts.error.hasAttribute('hidden'), false);
    assert.equal(parts.input.hasAttribute('aria-invalid'), false);
});

test('an invalid value shows the invalid message, marks the input and keeps the hidden value', () => {
    const parts = mounted(page({stored: 'A1'}), {values: ['A1']});

    enter(parts, 'no-dashes');

    assert.deepEqual(shownValues(parts.root), ['A1']);
    assert.equal(parts.hidden.value, 'A1');
    assert.equal(parts.input.value, 'no-dashes');
    assert.equal(parts.input.getAttribute('aria-invalid'), 'true');
    assert.equal(parts.error.textContent, 'Use letters, digits and spaces.');
});

test('the message hides after three seconds', () => {
    const parts = mounted(page());

    enter(parts, 'no-dashes');
    const timer = parts.timers[parts.timers.length - 1];

    assert.equal(timer.delay, 3000);
    timer.callback();

    assert.equal(parts.error.hasAttribute('hidden'), true);
    assert.equal(parts.input.hasAttribute('aria-invalid'), false);
});

test('the message hides on the next keystroke', () => {
    const parts = mounted(page());

    enter(parts, 'no-dashes');
    fire(parts.input, 'keydown', {key: 's'});

    assert.equal(parts.error.hasAttribute('hidden'), true);
    assert.equal(parts.error.textContent, '');
    assert.equal(parts.timers[parts.timers.length - 1].cleared, true);
});

test('a chip\'s remove button removes its value and returns the focus to the input', () => {
    const parts = mounted(page(), {values: ['A1', 'B2', 'C3']});

    fire(removeButtonOf(parts.root, 'B2'), 'click');

    assert.deepEqual(shownValues(parts.root), ['A1', 'C3']);
    assert.equal(parts.hidden.value, 'A1\nC3');
    assert.equal(parts.input.focused, 1);
});

test('Backspace in the empty input removes the last value', () => {
    const parts = mounted(page(), {values: ['A1', 'B2']});

    fire(parts.input, 'keydown', {key: 'Backspace'});

    assert.deepEqual(shownValues(parts.root), ['A1']);
    assert.equal(parts.hidden.value, 'A1');
});

test('Backspace in an input holding text removes nothing', () => {
    const parts = mounted(page({stored: 'A1'}), {values: ['A1']});

    parts.input.value = 'B';
    fire(parts.input, 'keydown', {key: 'Backspace'});

    assert.deepEqual(shownValues(parts.root), ['A1']);
    assert.equal(parts.hidden.value, 'A1');
});

test('a click on the chip area outside a chip focuses the input; a click on a chip does not', () => {
    const parts = mounted(page(), {values: ['A1']});

    fire(parts.root.querySelector(CHIP), 'click');
    assert.equal(parts.input.focused, 0);

    fire(parts.chips, 'click');
    assert.equal(parts.input.focused, 1);
});

test('the search input appears once the chips outgrow 500 pixels, filters them, and hides when they fit', () => {
    const values = Array.from({length: 12}, (unused, index) => `V${index}`);
    const parts = mounted(page(), {values});

    assert.equal(parts.search.hasAttribute('hidden'), true);

    enter(parts, 'X12');
    assert.equal(parts.search.hasAttribute('hidden'), false);
    assert.equal(parts.root.getAttribute('data-overflowing'), 'true');

    parts.search.value = 'v1';
    fire(parts.search, 'input');
    assert.deepEqual(
        parts.root.querySelectorAll(CHIP).filter((chip) => !chip.hasAttribute('hidden')).map((chip) => chip.getAttribute('data-value')),
        ['V1', 'V10', 'V11']
    );
    assert.equal(fire(parts.search, 'keydown', {key: 'Enter'}).defaultPrevented, true);

    fire(removeButtonOf(parts.root, 'V1'), 'click');
    assert.equal(parts.search.hasAttribute('hidden'), true);
    assert.equal(parts.search.value, '');
    assert.equal(parts.root.hasAttribute('data-overflowing'), false);
    assert.equal(parts.root.querySelectorAll(CHIP).every((chip) => !chip.hasAttribute('hidden')), true);
});

test('an inherited field accepts no add or remove, and never touches the checkbox', () => {
    const parts = mounted(page({stored: 'A1', disabled: true, inherited: true}), {values: ['A1'], disabled: true});

    assert.equal(parts.root.getAttribute('data-disabled'), 'true');
    assert.equal(parts.input.disabled, true);
    assert.equal(parts.hidden.disabled, true);
    assert.equal(removeButtonOf(parts.root, 'A1').disabled, true);

    enter(parts, 'B2');
    paste(parts, 'C3\nD4');
    fire(removeButtonOf(parts.root, 'A1'), 'click');
    parts.input.value = '';
    fire(parts.input, 'keydown', {key: 'Backspace'});

    assert.deepEqual(shownValues(parts.root), ['A1']);
    assert.equal(parts.hidden.value, 'A1');
    assert.equal(parts.inherit.checked, true);
});

test('unchecking "Use system value" makes the field editable and its hidden input posted, and checking it again locks it', () => {
    const parts = mounted(page({stored: 'A1', disabled: true, inherited: true}), {values: ['A1'], disabled: true});

    parts.inherit.checked = false;
    fire(parts.inherit, 'change');

    assert.equal(parts.root.hasAttribute('data-disabled'), false);
    assert.equal(parts.input.disabled, false);
    assert.equal(parts.hidden.disabled, false);
    assert.equal(removeButtonOf(parts.root, 'A1').disabled, false);
    enter(parts, 'B2');
    assert.equal(parts.hidden.value, 'A1\nB2');
    assert.equal(removeButtonOf(parts.root, 'B2').disabled, false);

    parts.inherit.checked = true;
    fire(parts.inherit, 'change');

    assert.equal(parts.root.getAttribute('data-disabled'), 'true');
    assert.equal(parts.hidden.disabled, true);
    assert.equal(removeButtonOf(parts.root, 'B2').disabled, true);
});

test('a locked field stays read-only whatever the checkbox does', () => {
    const parts = mounted(page({stored: 'A1', disabled: true}), {values: ['A1'], disabled: true});

    fire(parts.inherit, 'change');
    enter(parts, 'B2');

    assert.equal(parts.root.getAttribute('data-disabled'), 'true');
    assert.deepEqual(shownValues(parts.root), ['A1']);
});

test('a pattern that is not a regular expression is reported once and accepts every value', () => {
    const parts = mounted(page(), {validation: '([unclosed'});

    enter(parts, 'any-thing');
    enter(parts, 'other.thing');

    assert.equal(parts.reports.length, 1);
    assert.equal(parts.reports[0][1].name, 'SyntaxError');
    assert.deepEqual(shownValues(parts.root), ['ANY-THING', 'OTHER.THING']);
});

test('the separator joins the hidden value and splits a paste', () => {
    const parts = mounted(page(), {separator: ',', uppercase: false, validation: '^.+$'});

    paste(parts, 'https://shop.example/a,https://shop.example/b');

    assert.equal(parts.hidden.value, 'https://shop.example/a,https://shop.example/b');
});

test('mounting twice adds one set of listeners, and the teardown removes them all', () => {
    const parts = page();
    const environment = {
        values: [],
        separator: '\n',
        validation: '^.+$',
        uppercase: false,
        disabled: false,
        wording: {remove: 'Remove %1', duplicate: 'Already listed.', invalid: 'Invalid.'},
        inherit: parts.inherit,
        createChip: tagList.chipFactory(parts.template),
        schedule: () => () => {},
        report: () => {}
    };
    const teardown = tagList.mount(parts.root, environment);

    tagList.mount(parts.root, environment);
    assert.deepEqual(['keydown', 'paste', 'input', 'click'].map((type) => parts.root.listenerCount(type)), [1, 1, 1, 1]);
    assert.equal(parts.inherit.listenerCount('change'), 1);

    teardown();
    assert.deepEqual(['keydown', 'paste', 'input', 'click'].map((type) => parts.root.listenerCount(type)), [0, 0, 0, 0]);
    assert.equal(parts.inherit.listenerCount('change'), 0);

    tagList.mount(parts.root, environment);
    assert.equal(parts.root.listenerCount('keydown'), 1);
});

test('a configuration that is not the expected shape mounts nothing', () => {
    [
        null,
        config({wording: {remove: 'Remove %1'}}),
        config({values: [1]}),
        config({separator: ''}),
        config({validationMessage: ''}),
        config({disabled: 'no'})
    ].forEach((broken) => {
        const parts = page({stored: 'as printed'});

        tagList(broken, parts.root);

        assert.equal(parts.root.listenerCount('keydown'), 0, JSON.stringify(broken));
        assert.equal(parts.hidden.value, 'as printed');
    });
});

test('markup without the chip template mounts nothing', () => {
    const parts = mounted(page({withTemplate: false}), {values: ['A1']});

    assert.equal(parts.root.listenerCount('keydown'), 0);
    assert.deepEqual(plain(shownValues(parts.root)), []);
});
