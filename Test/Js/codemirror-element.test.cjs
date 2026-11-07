/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const {load} = require('./amd.cjs');
const {el, plain} = require('./fake-dom.cjs');

const STATIC = '/static/adminhtml/Magento/backend/en_US/';

/**
 * A Knockout-like observable: a function that reads and writes, notifies its subscribers and lets one be disposed
 *
 * @param {*} initial
 * @returns {Function}
 */
function observable(initial) {
    let current = initial;
    const subscribers = [];
    const read = (...args) => {
        if (args.length) {
            current = args[0];
            subscribers.slice().forEach((callback) => callback(current));
        }

        return current;
    };

    read.subscribe = (callback) => {
        subscribers.push(callback);

        return {dispose: () => subscribers.splice(subscribers.indexOf(callback), 1)};
    };
    read.subscriberCount = () => subscribers.length;

    return read;
}

/**
 * The UI form element over fakes of Magento's textarea component, CodeMirror, jQuery and the module loader
 *
 * @returns {Object}
 */
function elementModule() {
    const record = {editors: [], loads: [], data: new Map(), triggered: [], pending: []};
    const Textarea = {
        extend: (proto) => ({
            create: (overrides = {}) => {
                const instance = {
                    ...proto.defaults,
                    ...proto,
                    ...overrides,
                    _super: () => instance,
                    value: observable(''),
                    disabled: observable(false)
                };

                return instance;
            }
        })
    };
    const Element = load('Hryvinskyi_ConfigurationFields/js/form/element/codemirror', {
        require: Object.assign((modules, callback) => {
            record.loads.push(plain(modules));
            record.pending.push(callback);
        }, {toUrl: (id) => STATIC + id}),
        jquery: (node) => ({
            height: () => 0,
            data: (key, value) => record.data.set(node, {[key]: value}),
            trigger: (name, args) => record.triggered.push({node, name, args})
        }),
        'Magento_Ui/js/form/element/textarea': Textarea,
        'Hryvinskyi_ConfigurationFields/js/codemirror/lib/codemirror': {
            fromTextArea: (textarea, options) => {
                const editor = {
                    textarea,
                    options: {...options},
                    text: '',
                    toTextAreaCalls: 0,
                    handlers: {},
                    setSize: () => {},
                    on: (name, handler) => {
                        editor.handlers[name] = handler;
                    },
                    setOption: (name, value) => {
                        editor.options[name] = value;
                    },
                    getValue: () => editor.text,
                    setValue: (value) => {
                        editor.text = value;
                    },
                    toTextArea: () => {
                        editor.toTextAreaCalls += 1;
                    }
                };

                record.editors.push(editor);

                return editor;
            }
        }
    });

    return {Element, record};
}

/**
 * @returns {{textarea: Object}}
 */
function renderedTextarea() {
    const head = el('head');
    const textarea = el('textarea');
    const document = {
        head,
        createElement: (tag) => el(tag),
        querySelectorAll: (selector) => head.querySelectorAll(selector)
    };

    textarea.ownerDocument = document;

    return {textarea, head};
}

/**
 * Open the editor the way the template's afterRender does, and let the module loader answer
 *
 * @param {Object} component
 * @param {Object} record
 * @param {Object} textarea
 */
function open(component, record, textarea) {
    component.initEditor(textarea);
    record.pending.splice(0).forEach((callback) => callback());
}

test('the component renders through the module\'s own template', () => {
    const {Element} = elementModule();
    const component = Element.create();

    assert.equal(component.elementTmpl, 'Hryvinskyi_ConfigurationFields/form/element/codemirror');
});

test('the editor opens over the textarea with the field\'s mode, theme and settings', () => {
    const {Element, record} = elementModule();
    const {textarea} = renderedTextarea();
    const component = Element.create({editorConfig: {mode: 'css', theme: 'default', lineNumbers: false}});

    open(component, record, textarea);

    assert.equal(record.editors.length, 1);
    assert.equal(record.editors[0].textarea, textarea);
    assert.equal(record.editors[0].options.mode, 'css');
    assert.equal(record.editors[0].options.lineNumbers, false);
    assert.equal(record.editors[0].options.lineWrapping, true);
    assert.equal(record.editors[0].options.readOnly, false);
    assert.ok(record.loads[0].includes('Hryvinskyi_ConfigurationFields/js/codemirror/mode/css/css'));
    assert.equal(record.data.get(textarea).codemirror, record.editors[0]);
    assert.deepEqual(record.triggered.map(({node, name, args}) => [node, name, args[0]]), [
        [textarea, 'codemirror:initialized', record.editors[0]]
    ]);
});

test('a field without editor settings opens as an html editor', () => {
    const {Element, record} = elementModule();
    const component = Element.create({editorConfig: undefined});

    open(component, record, renderedTextarea().textarea);

    assert.equal(record.editors[0].options.mode, 'htmlmixed');
});

test('the editor\'s stylesheets are added to the page\'s head once', () => {
    const {Element, record} = elementModule();
    const {textarea, head} = renderedTextarea();

    open(Element.create({editorConfig: {mode: 'css', theme: 'monokai'}}), record, textarea);
    open(Element.create({editorConfig: {mode: 'css', theme: 'monokai'}}), record, textarea);

    assert.deepEqual(head.querySelectorAll('link').map((link) => link.getAttribute('href')), [
        `${STATIC}Hryvinskyi_ConfigurationFields/js/codemirror/lib/codemirror.css`,
        `${STATIC}Hryvinskyi_ConfigurationFields/js/codemirror/theme/monokai.css`,
        `${STATIC}Hryvinskyi_ConfigurationFields/css/foldgutter.css`
    ]);
});

test('a field that is disabled opens read-only and follows its disabled state', () => {
    const {Element, record} = elementModule();
    const component = Element.create();

    component.disabled(true);
    open(component, record, renderedTextarea().textarea);
    assert.equal(record.editors[0].options.readOnly, 'nocursor');

    component.disabled(false);
    assert.equal(record.editors[0].options.readOnly, false);

    component.disabled(true);
    assert.equal(record.editors[0].options.readOnly, 'nocursor');
});

test('typing in the editor sets the element\'s value, and a new value reaches the editor', () => {
    const {Element, record} = elementModule();
    const component = Element.create();

    component.initObservable();
    open(component, record, renderedTextarea().textarea);
    const [editor] = record.editors;

    editor.text = 'a { color: red; }';
    editor.handlers.change(editor);
    assert.equal(component.value(), 'a { color: red; }');

    component.value('b {}');
    assert.equal(editor.text, 'b {}');

    component.value(null);
    assert.equal(editor.text, '');
});

test('a value the editor already shows is not written back', () => {
    const {Element, record} = elementModule();
    const component = Element.create();
    let writes = 0;

    component.initObservable();
    open(component, record, renderedTextarea().textarea);
    record.editors[0].text = 'same';
    record.editors[0].setValue = () => {
        writes += 1;
    };

    component.value('same');

    assert.equal(writes, 0);
});

test('destroying the element gives the textarea back and stops following the disabled state', () => {
    const {Element, record} = elementModule();
    const component = Element.create();

    open(component, record, renderedTextarea().textarea);
    assert.equal(component.disabled.subscriberCount(), 1);

    component.destroy();

    assert.equal(record.editors[0].toTextAreaCalls, 1);
    assert.equal(component.editor, null);
    assert.equal(component.disabled.subscriberCount(), 0);
});

test('an element destroyed while the editor\'s modules load opens no editor', () => {
    const {Element, record} = elementModule();
    const component = Element.create();

    component.initEditor(renderedTextarea().textarea);
    component.destroy();
    record.pending.splice(0).forEach((callback) => callback());

    assert.equal(record.editors.length, 0);
    assert.equal(record.triggered.length, 0);
});
