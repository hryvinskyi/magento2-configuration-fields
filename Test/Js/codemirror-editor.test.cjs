/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const {load} = require('./amd.cjs');
const {el, fire} = require('./fake-dom.cjs');

const STATIC = '/static/adminhtml/Magento/backend/en_US/';

/**
 * The entry over fakes of CodeMirror, jQuery and the module loader, recording what each was asked
 *
 * @returns {Object}
 */
function editorModule() {
    const record = {editors: [], loads: [], data: new Map(), triggered: []};
    const entry = load('Hryvinskyi_ConfigurationFields/js/codemirror-editor', {
        require: Object.assign((modules, callback) => {
            record.loads.push(modules);
            callback();
        }, {toUrl: (id) => STATIC + id}),
        jquery: (node) => ({
            height: () => 0,
            val: (value) => {
                node.value = value;
            },
            data: (key, value) => record.data.set(node, {[key]: value}),
            trigger: (name, args) => record.triggered.push({node, name, args})
        }),
        'Hryvinskyi_ConfigurationFields/js/codemirror/lib/codemirror': {
            fromTextArea: (textarea, options) => {
                const editor = {textarea, options: {...options}, size: null, setSize: (width, height) => {
                    editor.size = height;
                }, on: () => {}, setOption: (name, value) => {
                    editor.options[name] = value;
                }};

                record.editors.push(editor);

                return editor;
            }
        }
    });

    return {entry, record};
}

/**
 * A page with one or two code fields and their "Use system value" checkboxes
 *
 * @param {Array<{key: string, disabled?: boolean, inherited?: boolean}>} fields
 * @returns {{document: Object, head: Object, fields: Array<Object>}}
 */
function page(fields) {
    const head = el('head');
    const built = fields.map(({key, disabled = false, inherited = false}) => ({
        key,
        textarea: el('textarea', {id: `code_${key}`, ...(disabled ? {disabled: ''} : {})}),
        inherit: el('input', {type: 'checkbox', id: `code_${key}_inherit`, ...(inherited ? {checked: ''} : {})})
    })).map((field) => ({
        ...field,
        root: el('div', {'data-role': 'hcf-code', ...(field.textarea.disabled ? {'data-disabled': 'true'} : {})}, [field.textarea])
    }));
    const nodes = built.flatMap((field) => [field.root, field.textarea, field.inherit]);
    const document = {
        head,
        createElement: (tag) => el(tag),
        querySelectorAll: (selector) => head.querySelectorAll(selector),
        getElementById: (id) => nodes.find((node) => node.getAttribute('id') === id) || null
    };

    nodes.forEach((node) => {
        node.ownerDocument = document;
    });

    return {document, head, fields: built};
}

/**
 * @param {Object} field
 * @param {Object} [overrides]
 * @returns {Object}
 */
const config = (field, overrides = {}) => ({
    inputId: `code_${field.key}`,
    inheritId: `code_${field.key}_inherit`,
    mode: 'css',
    theme: 'default',
    lineNumbers: true,
    lineWrapping: true,
    readOnly: false,
    ...overrides
});

test('the editor is built over the root\'s textarea and published on it', () => {
    const {entry, record} = editorModule();
    const {fields: [field]} = page([{key: 'a'}]);

    entry(config(field), field.root);

    assert.equal(record.editors.length, 1);
    assert.equal(record.editors[0].textarea, field.textarea);
    assert.equal(record.editors[0].options.mode, 'css');
    assert.equal(record.editors[0].options.readOnly, false);
    assert.equal(record.data.get(field.textarea).codemirror, record.editors[0]);
    assert.deepEqual(record.triggered.map(({node, name, args}) => [node, name, args[0]]), [
        [field.textarea, 'codemirror:initialized', record.editors[0]]
    ]);
});

test('mounting the same root twice builds one editor', () => {
    const {entry, record} = editorModule();
    const {fields: [field]} = page([{key: 'a'}]);

    entry(config(field), field.root);
    entry(config(field), field.root);

    assert.equal(record.editors.length, 1);
});

test('the stylesheets are added to the page once, however many code fields it holds', () => {
    const {entry} = editorModule();
    const {head, fields} = page([{key: 'a'}, {key: 'b'}]);

    fields.forEach((field) => entry(config(field, {theme: 'monokai'}), field.root));

    assert.deepEqual(head.querySelectorAll('link').map((link) => link.getAttribute('href')), [
        `${STATIC}Hryvinskyi_ConfigurationFields/js/codemirror/lib/codemirror.css`,
        `${STATIC}Hryvinskyi_ConfigurationFields/js/codemirror/theme/monokai.css`,
        `${STATIC}Hryvinskyi_ConfigurationFields/css/foldgutter.css`
    ]);
});

test('an inherited field opens read-only and follows its checkbox', () => {
    const {entry, record} = editorModule();
    const {fields: [field]} = page([{key: 'a', disabled: true, inherited: true}]);

    entry(config(field, {readOnly: true}), field.root);
    assert.equal(record.editors[0].options.readOnly, 'nocursor');
    assert.equal(field.root.getAttribute('data-disabled'), 'true');

    field.inherit.checked = false;
    fire(field.inherit, 'change');
    assert.equal(record.editors[0].options.readOnly, false);
    assert.equal(field.root.hasAttribute('data-disabled'), false);

    field.inherit.checked = true;
    fire(field.inherit, 'change');
    assert.equal(record.editors[0].options.readOnly, 'nocursor');
    assert.equal(field.root.getAttribute('data-disabled'), 'true');
    assert.equal(field.inherit.checked, true);
});

test('a locked field stays read-only whatever the checkbox does', () => {
    const {entry, record} = editorModule();
    const {fields: [field]} = page([{key: 'a', disabled: true}]);

    entry(config(field, {readOnly: true}), field.root);
    fire(field.inherit, 'change');

    assert.equal(record.editors[0].options.readOnly, 'nocursor');
    assert.equal(field.root.getAttribute('data-disabled'), 'true');
});
