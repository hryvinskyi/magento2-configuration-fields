/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const {load} = require('./amd.cjs');
const {el, fire, documentOf} = require('./fake-dom.cjs');

const PARTS = ['minute', 'hour', 'dayOfMonth', 'month', 'dayOfWeek'];
const WORDING = {
    invalidExpression: 'Invalid cron expression.',
    invalidFields: 'Check the highlighted parts.',
    allowedValues: 'allowed values',
    anyValue: 'any value',
    valueListSeparator: 'value list separator',
    rangeOfValues: 'range of values',
    stepValues: 'step values',
    undescribable: 'This schedule cannot be described in words.'
};
const UNDESCRIBABLE = '0 0 31 2 *';

/**
 * The editor module over a fake jQuery whose validator records the rules registered with it, and a describer that
 * refuses one expression the way cronstrue refuses what it cannot read and records the options it was asked with
 *
 * @returns {{
 *     cronEditor: Function,
 *     rules: Array<{name: string, method: Function, message: string}>,
 *     asked: Array<Object>
 * }}
 */
function editorModule() {
    const rules = [];
    const asked = [];
    const cronEditor = load('Hryvinskyi_ConfigurationFields/js/cron-editor', {
        jquery: {validator: {addMethod: (name, method, message) => rules.push({name, method, message})}},
        'jquery/validate': {},
        'Hryvinskyi_ConfigurationFields/js/cronstrue': {
            default: {locales: {en: {}, de: {}, pt_BR: {}}},
            toString: (expression, options) => {
                asked.push(options);
                if (expression === UNDESCRIBABLE) {
                    throw new Error('Not a real date');
                }

                return `Described: ${expression}`;
            }
        }
    });

    return {cronEditor, rules, asked};
}

/**
 * One cron field as the template prints it, with its "Use system value" checkbox
 *
 * @param {string} key Makes the ids unique when a page holds several fields
 * @param {{stored?: string, disabled?: boolean, inherited?: boolean}} [options]
 * @returns {Object} The field's parts
 */
function field(key, {stored = '', disabled = false, inherited = false} = {}) {
    const lock = disabled ? {disabled: ''} : {};
    const inputs = Object.fromEntries(PARTS.map((part) => [
        part,
        el('input', {type: 'text', id: `hcf-${key}-${part}`, class: 'hui-input', 'data-role': 'hcf-cron-field', 'data-part': part, ...lock})
    ]));
    const rows = Object.fromEntries(PARTS.map((part) => [part, el('tbody', {'data-part': part, hidden: ''})]));
    const summary = el('div', {'data-role': 'hcf-cron-summary', role: 'status'});
    const hidden = el('input', {
        type: 'hidden',
        id: `cron_${key}`,
        class: 'validate-cron-expression',
        'data-role': 'hcf-cron-value',
        value: stored,
        ...lock
    });
    const root = el('div', {
        id: `hcf-${key}`,
        class: 'hui hcf-cron cron-editor-root',
        'data-role': 'hcf-cron',
        ...(disabled ? {'data-disabled': 'true'} : {})
    }, [
        summary,
        el('div', {class: 'hcf-cron__parts'}, PARTS.map((part) => el('div', {class: 'hcf-cron__part'}, [inputs[part], el('label')]))),
        el('table', {'data-role': 'hcf-cron-syntax'}, [el('tbody'), ...PARTS.map((part) => rows[part])]),
        hidden
    ]);
    const inherit = el('input', {type: 'checkbox', id: `cron_${key}_inherit`, ...(inherited ? {checked: ''} : {})});

    return {root, inputs, rows, summary, hidden, inherit, key};
}

/**
 * The config the server prints for a field
 *
 * @param {Object} parts
 * @param {Object} [overrides]
 * @returns {Object}
 */
function config(parts, overrides = {}) {
    return {
        inputId: `cron_${parts.key}`,
        inheritId: `cron_${parts.key}_inherit`,
        disabled: false,
        value: parts.hidden.value,
        wording: WORDING,
        ...overrides
    };
}

/**
 * Put fields on one page and mount each through the x-magento-init entry
 *
 * @param {Function} cronEditor
 * @param {Array<Object>} fields
 * @param {Object} [overrides] Config keys to change for every field
 * @returns {Array<Object>} The fields
 */
function mountAll(cronEditor, fields, overrides = {}) {
    documentOf(fields.flatMap((parts) => [parts.root, parts.inherit]));
    fields.forEach((parts) => cronEditor(config(parts, overrides), parts.root));

    return fields;
}

/**
 * @param {Object} parts
 * @returns {Array<string>}
 */
const shownParts = (parts) => PARTS.map((part) => parts.inputs[part].value);

/**
 * @param {Object} parts
 * @returns {Array<string>}
 */
const invalidParts = (parts) => PARTS.filter((part) => parts.inputs[part].getAttribute('aria-invalid') === 'true');

/**
 * Type into one part
 *
 * @param {Object} parts
 * @param {string} part
 * @param {string} text
 */
function type(parts, part, text) {
    parts.inputs[part].value = text;
    fire(parts.inputs[part], 'input');
}

test('mounting shows the stored expression in the parts, describes it, and writes nothing', () => {
    const {cronEditor} = editorModule();
    const [parts] = mountAll(cronEditor, [field('a', {stored: '*/5 0 * * 1-5'})]);

    assert.deepEqual(shownParts(parts), ['*/5', '0', '*', '*', '1-5']);
    assert.equal(parts.summary.textContent, 'Described: */5 0 * * 1-5');
    assert.equal(parts.hidden.value, '*/5 0 * * 1-5');
    assert.deepEqual(invalidParts(parts), []);
});

test('an empty stored value shows empty parts and no error, and stays empty', () => {
    const {cronEditor} = editorModule();
    const [parts] = mountAll(cronEditor, [field('a')]);

    assert.deepEqual(shownParts(parts), ['', '', '', '', '']);
    assert.equal(parts.summary.textContent, '');
    assert.equal(parts.summary.hasAttribute('data-tone'), false);
    assert.deepEqual(invalidParts(parts), []);
    assert.equal(parts.hidden.value, '');
});

test('a stored value with too few parts is shown as far as it parses, marked invalid and not rewritten', () => {
    const {cronEditor} = editorModule();
    const [parts] = mountAll(cronEditor, [field('a', {stored: '*/5 0 *'})]);

    assert.deepEqual(shownParts(parts), ['*/5', '0', '*', '', '']);
    assert.deepEqual(invalidParts(parts), ['month', 'dayOfWeek']);
    assert.equal(parts.summary.textContent, WORDING.invalidFields);
    assert.equal(parts.summary.getAttribute('data-tone'), 'error');
    assert.equal(parts.hidden.value, '*/5 0 *');
});

test('a stored value with more than six parts keeps the extra with the last part, marked invalid and not rewritten', () => {
    const {cronEditor} = editorModule();
    const [parts] = mountAll(cronEditor, [field('a', {stored: '0 0 * * * 2026 x'})]);

    assert.deepEqual(shownParts(parts), ['0', '0', '*', '*', '* 2026 x']);
    assert.deepEqual(invalidParts(parts), ['dayOfWeek']);
    assert.equal(parts.hidden.value, '0 0 * * * 2026 x');
});

test('a stored six-part value shows its five parts, describes them, and is accepted unedited', () => {
    const {cronEditor, rules} = editorModule();
    const [parts] = mountAll(cronEditor, [field('a', {stored: '0 0 * * * 2026'})]);

    assert.deepEqual(shownParts(parts), ['0', '0', '*', '*', '*']);
    assert.deepEqual(invalidParts(parts), []);
    assert.equal(parts.summary.textContent, 'Described: 0 0 * * *');
    assert.equal(parts.hidden.value, '0 0 * * * 2026');
    assert.equal(rules[0].method(parts.hidden.value, parts.hidden), true);
});

test('editing a stored six-part value writes the five parts with the sixth kept as it was', () => {
    const {cronEditor} = editorModule();
    const [parts] = mountAll(cronEditor, [field('a', {stored: '0 0 * * * 2026'})]);

    type(parts, 'hour', '4');

    assert.equal(parts.hidden.value, '0 4 * * * 2026');
    assert.equal(parts.summary.textContent, 'Described: 0 4 * * *');
});

test('expressions Magento\'s cron schedule accepts are accepted unedited and after an edit', () => {
    const {cronEditor, rules} = editorModule();
    const [weekday, ranged] = mountAll(cronEditor, [
        field('a', {stored: '0 2 * * mon'}),
        field('b', {stored: '* 0-5,10-59/5 2-10,15-25 january-june/2 mon-fri'})
    ]);

    assert.deepEqual(invalidParts(weekday), []);
    assert.equal(rules[0].method(weekday.hidden.value, weekday.hidden), true);
    assert.equal(rules[0].method(ranged.hidden.value, ranged.hidden), true);

    type(weekday, 'minute', '1-30/5');
    type(weekday, 'month', 'jan');

    assert.deepEqual(invalidParts(weekday), []);
    assert.equal(weekday.hidden.value, '1-30/5 2 * jan mon');
    assert.equal(rules[0].method(weekday.hidden.value, weekday.hidden), true);
});

test('typing an invalid part marks it, shows the wording from config and keeps the hidden value', () => {
    const {cronEditor} = editorModule();
    const [parts] = mountAll(cronEditor, [field('a', {stored: '0 3 * * *'})]);

    type(parts, 'hour', 'L');

    assert.deepEqual(invalidParts(parts), ['hour']);
    assert.equal(parts.summary.textContent, WORDING.invalidFields);
    assert.equal(parts.hidden.value, '0 3 * * *');
});

test('a valid set of parts writes the hidden input and shows the description', () => {
    const {cronEditor} = editorModule();
    const [parts] = mountAll(cronEditor, [field('a', {stored: '0 3 * * *'})]);

    type(parts, 'hour', 'L');
    type(parts, 'hour', '4');

    assert.deepEqual(invalidParts(parts), []);
    assert.equal(parts.hidden.value, '0 4 * * *');
    assert.equal(parts.summary.textContent, 'Described: 0 4 * * *');
    assert.equal(parts.summary.hasAttribute('data-tone'), false);
});

test('filling an empty field part by part writes the expression only once every part is valid', () => {
    const {cronEditor} = editorModule();
    const [parts] = mountAll(cronEditor, [field('a')]);

    type(parts, 'minute', '0');
    assert.equal(parts.hidden.value, '');
    assert.deepEqual(invalidParts(parts), ['hour', 'dayOfMonth', 'month', 'dayOfWeek']);

    ['hour', 'dayOfMonth', 'month', 'dayOfWeek'].forEach((part) => type(parts, part, '*'));
    assert.equal(parts.hidden.value, '0 * * * *');
});

/**
 * Empty every part
 *
 * @param {Object} parts
 */
const clear = (parts) => PARTS.forEach((part) => type(parts, part, ''));

test('clearing every part of a stored expression is refused, and the hidden input keeps the stored expression', () => {
    const {cronEditor, rules} = editorModule();
    const [parts] = mountAll(cronEditor, [field('a', {stored: '*/5 * * * *'})]);

    clear(parts);

    assert.equal(rules[0].method(parts.hidden.value, parts.hidden), false);
    assert.equal(parts.summary.textContent, WORDING.invalidExpression);
    assert.equal(parts.summary.getAttribute('data-tone'), 'error');
    assert.deepEqual(invalidParts(parts), PARTS);
    assert.equal(parts.hidden.value, '*/5 * * * *');
});

test('a dedicated wording for a cleared expression, when the config carries one, is what the summary shows', () => {
    const {cronEditor} = editorModule();
    const wording = {...WORDING, expressionRequired: 'Enter a schedule.'};
    const [parts] = mountAll(cronEditor, [field('a', {stored: '*/5 * * * *'})], {wording});

    clear(parts);

    assert.equal(parts.summary.textContent, 'Enter a schedule.');
});

test('inside a cron field the rule leaves its own message empty, as the summary shows it; outside one it names the fault', () => {
    const {cronEditor, rules} = editorModule();
    const [cleared, invalid] = mountAll(cronEditor, [field('a', {stored: '*/5 * * * *'}), field('b', {stored: '0 3 * * *'})]);
    const loose = el('input', {value: '*/5 * * *'});

    clear(cleared);
    type(invalid, 'minute', 'L');

    assert.equal(rules[0].method(cleared.hidden.value, cleared.hidden), false);
    assert.equal(rules[0].message(true, cleared.hidden), '');
    assert.equal(rules[0].method(invalid.hidden.value, invalid.hidden), false);
    assert.equal(rules[0].message(true, invalid.hidden), '');
    assert.equal(rules[0].method(loose.value, loose), false);
    assert.equal(rules[0].message(true, loose), WORDING.invalidExpression);
});

test('a stored expression edited to another valid one is accepted and written', () => {
    const {cronEditor, rules} = editorModule();
    const [parts] = mountAll(cronEditor, [field('a', {stored: '*/5 * * * *'})]);

    type(parts, 'minute', '0');

    assert.equal(rules[0].method(parts.hidden.value, parts.hidden), true);
    assert.equal(parts.hidden.value, '0 * * * *');
    assert.deepEqual(invalidParts(parts), []);
});

test('a field empty at load may stay empty, even after it was filled and cleared again', () => {
    const {cronEditor, rules} = editorModule();
    const [parts] = mountAll(cronEditor, [field('a')]);

    assert.equal(rules[0].method(parts.hidden.value, parts.hidden), true);

    PARTS.forEach((part) => type(parts, part, '*'));
    assert.equal(parts.hidden.value, '* * * * *');

    clear(parts);

    assert.equal(rules[0].method(parts.hidden.value, parts.hidden), true);
    assert.equal(parts.hidden.value, '');
    assert.equal(parts.summary.textContent, '');
    assert.deepEqual(invalidParts(parts), []);
});

test('a cleared field is judged against its own stored expression, not a neighbour\'s', () => {
    const {cronEditor, rules} = editorModule();
    const [held, empty] = mountAll(cronEditor, [field('a', {stored: '0 3 * * *'}), field('b')]);

    clear(held);
    clear(empty);

    assert.equal(rules[0].method(held.hidden.value, held.hidden), false);
    assert.equal(rules[0].method(empty.hidden.value, empty.hidden), true);
});

test('an expression the describer refuses shows the neutral wording in the normal tone', () => {
    const {cronEditor} = editorModule();
    const [parts] = mountAll(cronEditor, [field('a', {stored: UNDESCRIBABLE})]);

    assert.equal(parts.summary.textContent, WORDING.undescribable);
    assert.equal(parts.summary.hasAttribute('data-tone'), false);
});

test('without a dedicated wording the describer\'s refusal shows the invalid-expression wording, in the normal tone', () => {
    const {cronEditor} = editorModule();
    const {undescribable, ...wording} = WORDING;
    const [parts] = mountAll(cronEditor, [field('a', {stored: UNDESCRIBABLE})], {wording});

    assert.equal(parts.summary.textContent, WORDING.invalidExpression);
    assert.equal(parts.summary.hasAttribute('data-tone'), false);
});

test('the describer is asked in the locale of the config, mapped onto the locales the bundle reports', () => {
    [
        ['de_DE', 'de'],
        ['pt_BR', 'pt_BR'],
        ['de_AT', 'de'],
        ['xx_YY', 'en'],
        [undefined, 'en'],
        [5, 'en']
    ].forEach(([locale, expected]) => {
        const {cronEditor, asked} = editorModule();

        mountAll(cronEditor, [field('a', {stored: '*/5 * * * *'})], locale === undefined ? {} : {locale});

        assert.deepEqual(asked.map((options) => options.locale), [expected], String(locale));
    });
});

test('focusing a part reveals its syntax rows, and leaving it hides them', () => {
    const {cronEditor} = editorModule();
    const [parts] = mountAll(cronEditor, [field('a')]);
    const shown = () => PARTS.filter((part) => !parts.rows[part].hasAttribute('hidden'));

    fire(parts.inputs.dayOfMonth, 'focusin');
    assert.deepEqual(shown(), ['dayOfMonth']);

    fire(parts.inputs.dayOfMonth, 'focusout');
    fire(parts.inputs.month, 'focusin');
    assert.deepEqual(shown(), ['month']);

    fire(parts.inputs.month, 'focusout');
    assert.deepEqual(shown(), []);
});

test('the form rule is registered once with the wording from config, and judges each field by its own parts', () => {
    const {cronEditor, rules} = editorModule();
    const [first, second] = mountAll(cronEditor, [field('a', {stored: '0 3 * * *'}), field('b', {stored: '*/5 * * * *'})]);

    assert.equal(rules.length, 1);
    assert.equal(rules[0].name, 'validate-cron-expression');
    type(first, 'minute', '1-2-3');

    assert.equal(rules[0].method(first.hidden.value, first.hidden), false);
    assert.equal(rules[0].method(second.hidden.value, second.hidden), true);

    type(first, 'minute', '1');
    type(second, 'dayOfWeek', '?');

    assert.equal(rules[0].method(first.hidden.value, first.hidden), true);
    assert.equal(rules[0].method(second.hidden.value, second.hidden), false);
});

test('the form rule accepts an empty field and refuses an unedited stored value that does not parse', () => {
    const {cronEditor, rules} = editorModule();
    const [empty, broken] = mountAll(cronEditor, [field('a'), field('b', {stored: '0 0 * * L'})]);

    assert.equal(rules[0].method(empty.hidden.value, empty.hidden), true);
    assert.equal(rules[0].method(broken.hidden.value, broken.hidden), false);
});

test('the form rule judges an element outside a cron field by its value', () => {
    const {cronEditor} = editorModule();
    const loose = el('input', {value: '*/5 * * *'});

    assert.equal(cronEditor.validateElement('*/5 * * *', loose), false);
    assert.equal(cronEditor.validateElement('*/5 * * * *', loose), true);
    assert.equal(cronEditor.validateElement('', loose), true);
});

test('an inherited field has its parts and hidden input disabled, and never touches the checkbox', () => {
    const {cronEditor} = editorModule();
    const [parts] = mountAll(cronEditor, [field('a', {stored: '0 3 * * *', disabled: true, inherited: true})], {disabled: true});

    assert.equal(parts.root.getAttribute('data-disabled'), 'true');
    assert.equal(PARTS.every((part) => parts.inputs[part].disabled), true);
    assert.equal(parts.hidden.disabled, true);
    assert.equal(parts.inherit.checked, true);
});

test('unchecking "Use system value" enables the parts and the hidden input, and checking it again disables them', () => {
    const {cronEditor} = editorModule();
    const [parts] = mountAll(cronEditor, [field('a', {stored: '0 3 * * *', disabled: true, inherited: true})], {disabled: true});

    parts.inherit.checked = false;
    fire(parts.inherit, 'change');

    assert.equal(parts.root.hasAttribute('data-disabled'), false);
    assert.equal(PARTS.every((part) => !parts.inputs[part].disabled), true);
    assert.equal(parts.hidden.disabled, false);

    parts.inherit.checked = true;
    fire(parts.inherit, 'change');

    assert.equal(parts.root.getAttribute('data-disabled'), 'true');
    assert.equal(parts.hidden.disabled, true);
});

test('a locked field stays disabled whatever the checkbox does', () => {
    const {cronEditor} = editorModule();
    const [parts] = mountAll(cronEditor, [field('a', {stored: '0 3 * * *', disabled: true})], {disabled: true});

    fire(parts.inherit, 'change');

    assert.equal(parts.root.getAttribute('data-disabled'), 'true');
    assert.equal(parts.hidden.disabled, true);
});

test('mounting twice adds one set of listeners, and the teardown removes them all', () => {
    const {cronEditor} = editorModule();
    const parts = field('a', {stored: '0 3 * * *'});
    const environment = {value: '0 3 * * *', disabled: false, wording: WORDING, inherit: parts.inherit, describe: String};
    const teardown = cronEditor.mount(parts.root, environment);

    cronEditor.mount(parts.root, environment);
    assert.deepEqual(['input', 'focusin', 'focusout'].map((kind) => parts.root.listenerCount(kind)), [1, 1, 1]);
    assert.equal(parts.inherit.listenerCount('change'), 1);

    teardown();
    assert.deepEqual(['input', 'focusin', 'focusout'].map((kind) => parts.root.listenerCount(kind)), [0, 0, 0]);
    assert.equal(parts.inherit.listenerCount('change'), 0);
});

test('a configuration that is not the expected shape mounts nothing and registers no rule', () => {
    [
        (parts) => null,
        (parts) => config(parts, {value: 5}),
        (parts) => config(parts, {wording: {invalidExpression: 'Invalid.'}}),
        (parts) => config(parts, {disabled: 'no'}),
        (parts) => config(parts, {inheritId: null})
    ].forEach((broken) => {
        const {cronEditor, rules} = editorModule();
        const parts = field('a', {stored: '0 3 * * *'});

        documentOf([parts.root, parts.inherit]);
        cronEditor(broken(parts), parts.root);

        assert.equal(parts.root.listenerCount('input'), 0);
        assert.deepEqual(shownParts(parts), ['', '', '', '', '']);
        assert.equal(rules.length, 0);
    });
});
