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

const ROWS = [
    {id: '2', parentId: '1', label: 'Default Category'},
    {id: '3', parentId: '2', label: 'Gear'}
];

/**
 * A Knockout-like observable: call it to read, call it with a value to write, subscribe to hear writes
 *
 * @param {*} initial
 * @returns {Function}
 */
function observable(initial) {
    let current = initial;
    const listeners = new Set();
    const read = (...args) => {
        if (args.length === 0) {
            return current;
        }
        current = args[0];
        listeners.forEach((listener) => listener(current));

        return undefined;
    };

    read.subscribe = (listener) => {
        listeners.add(listener);

        return {dispose: () => listeners.delete(listener)};
    };
    read.subscriberCount = () => listeners.size;

    return read;
}

/**
 * The widget module over fakes of Magento's component bootstrap and registry: the bootstrap records the components it
 * is asked to create, and the registry answers a lookup once the test creates the component
 *
 * @returns {{field: Function, created: Array<Object>, components: Map<string, Object>, resolve: Function}}
 */
function widgetModule() {
    const created = [];
    const components = new Map();
    const waiting = new Map();
    const field = load('Hryvinskyi_ConfigurationFields/js/ui-select-field', {
        'Magento_Ui/js/core/app': (data) => created.push(plain(data)),
        uiRegistry: {
            get: (name, callback) => {
                if (components.has(name)) {
                    callback(components.get(name));

                    return;
                }
                waiting.set(name, callback);
            }
        }
    });

    /**
     * Creates the component the bootstrap was asked for, as Magento's layout would, and answers the registry lookup
     *
     * @param {string} name
     * @returns {Object} The component
     */
    function resolve(name) {
        const component = {value: observable(''), disabled: observable(false), destroyed: 0};

        component.destroy = () => {
            component.destroyed += 1;
        };
        components.set(name, component);
        if (waiting.has(name)) {
            waiting.get(name)(component);
            waiting.delete(name);
        }

        return component;
    }

    return {field, created, components, resolve};
}

/**
 * One category select as the template prints it, with its "Use system value" checkbox
 *
 * @param {{stored?: string, disabled?: boolean, inherited?: boolean}} [options]
 * @returns {{root: Object, hidden: Object, inherit: Object, document: Object}}
 */
function markup({stored = '', disabled = false, inherited = false} = {}) {
    const hidden = el('input', {
        type: 'hidden',
        id: 'cfg_cats',
        name: 'groups[g][fields][cats][value]',
        value: stored,
        'data-role': 'hcf-ui-select-value',
        ...(disabled ? {disabled: ''} : {})
    });
    const root = el('div', {
        id: 'hcf-abc',
        class: 'hui-tokens hcf-ui-select',
        'data-role': 'hcf-ui-select',
        ...(disabled ? {'data-disabled': 'true'} : {})
    }, [el('div', {'data-role': 'hcf-ui-select-control'}), hidden]);
    const inherit = el('input', {type: 'checkbox', id: 'cfg_cats_inherit', ...(inherited ? {checked: ''} : {})});
    const document = documentOf([root, inherit]);

    return {root, hidden, inherit, document};
}

/**
 * The config the view model prints for the field
 *
 * @param {Object} [overrides]
 * @returns {Object}
 */
function config(overrides = {}) {
    return {
        componentName: 'hcf_abc_select',
        inputId: 'cfg_cats',
        inheritId: 'cfg_cats_inherit',
        disabled: false,
        multiple: true,
        value: '2,3',
        categories: ROWS,
        ...overrides
    };
}

test('mounting creates Magento\'s select component under the bound name with the nested categories', () => {
    const {field, created} = widgetModule();
    const {root} = markup({stored: '2,3'});

    field(config(), root);

    assert.equal(created.length, 1);
    const component = created[0].components['hcf_abc_select'];
    assert.equal(component.component, 'Magento_Ui/js/form/element/ui-select');
    assert.equal(component.elementTmpl, 'ui/grid/filters/elements/ui-select');
    assert.equal(component.multiple, true);
    assert.equal(component.disabled, false);
    assert.equal(component.disableLabel, true);
    assert.deepEqual(component.options, [{
        value: '2',
        label: 'Default Category',
        __disableTmpl: true,
        optgroup: [{value: '3', label: 'Gear', __disableTmpl: true}]
    }]);
});

test('the stored ids reach the component after it is created, never through its templated config', () => {
    const {field, created, resolve} = widgetModule();
    const {root, hidden} = markup({stored: '2,3'});

    field(config({value: '${ $.provider },3'}), root);
    const component = resolve('hcf_abc_select');

    assert.equal(Object.prototype.hasOwnProperty.call(created[0].components['hcf_abc_select'], 'value'), false);
    assert.deepEqual(plain(component.value()), ['${ $.provider }', '3']);
    assert.equal(hidden.value, '2,3', 'Handing over the stored ids writes nothing');
});

test('a select field hands over one id', () => {
    const {field, resolve} = widgetModule();
    const {root} = markup({stored: '3'});

    field(config({multiple: false, value: '3'}), root);

    assert.equal(resolve('hcf_abc_select').value(), '3');
});

test('what the person picks is posted by the hidden input, ids joined by commas', () => {
    const {field, resolve} = widgetModule();
    const {root, hidden} = markup({stored: '2'});

    field(config({value: '2'}), root);
    const component = resolve('hcf_abc_select');
    component.value(['2', '3']);

    assert.equal(hidden.value, '2,3');
    component.value([]);
    assert.equal(hidden.value, '');
});

test('an inherited value disables the component and the hidden input until "Use system value" is cleared', () => {
    const {field, created, resolve} = widgetModule();
    const {root, hidden, inherit} = markup({stored: '2', disabled: true, inherited: true});

    field(config({disabled: true, value: '2'}), root);
    const component = resolve('hcf_abc_select');

    assert.equal(created[0].components['hcf_abc_select'].disabled, true);
    assert.equal(component.disabled(), true);
    assert.equal(hidden.disabled, true);
    assert.equal(root.getAttribute('data-disabled'), 'true');

    inherit.checked = false;
    fire(inherit, 'change');

    assert.equal(component.disabled(), false);
    assert.equal(hidden.disabled, false);
    assert.equal(root.hasAttribute('data-disabled'), false);

    inherit.checked = true;
    fire(inherit, 'change');

    assert.equal(component.disabled(), true);
    assert.equal(hidden.disabled, true);
});

test('a locked value stays read-only whatever the checkbox does', () => {
    const {field, resolve} = widgetModule();
    const {root, hidden, inherit} = markup({stored: '2', disabled: true});

    field(config({disabled: true, value: '2'}), root);
    const component = resolve('hcf_abc_select');
    fire(inherit, 'change');

    assert.equal(component.disabled(), true);
    assert.equal(hidden.disabled, true);
});

test('a checkbox change before the component exists applies once it is created', () => {
    const {field, resolve} = widgetModule();
    const {root, inherit} = markup({stored: '2', disabled: true, inherited: true});

    field(config({disabled: true, value: '2'}), root);
    inherit.checked = false;
    fire(inherit, 'change');

    assert.equal(resolve('hcf_abc_select').disabled(), false);
});

test('a root is mounted once, and the teardown removes the listener, the subscription and the component', () => {
    const {field, created, components, resolve} = widgetModule();
    const {root, inherit, document} = markup({stored: '2'});
    const environment = {
        config: {
            componentName: 'hcf_abc_select',
            disabled: false,
            multiple: true,
            value: '2',
            categories: ROWS
        },
        inherit: document.getElementById('cfg_cats_inherit'),
        create: (name, componentConfig) => created.push({components: {[name]: plain(componentConfig)}}),
        whenCreated: (name, callback) => callback(components.get(name) || resolve(name))
    };

    const teardown = field.mount(root, environment);
    const component = components.get('hcf_abc_select');
    field.mount(root, environment);

    assert.equal(created.length, 1);
    assert.equal(inherit.listenerCount('change'), 1);

    teardown();

    assert.equal(inherit.listenerCount('change'), 0);
    assert.equal(component.value.subscriberCount(), 0);
    assert.equal(component.destroyed, 1);
    assert.equal(created.length, 1);
    field.mount(root, environment);
    assert.equal(created.length, 2, 'A torn-down root mounts again');
});

test('a configuration that is not the expected shape mounts nothing, and the hidden input posts the stored value', () => {
    const {field, created} = widgetModule();

    [
        null,
        config({componentName: ''}),
        config({multiple: 'yes'}),
        config({disabled: 1}),
        config({value: ['2']}),
        config({inheritId: null}),
        config({categories: [{id: 2, parentId: '1', label: 'x'}]})
    ].forEach((broken) => {
        const {root, hidden} = markup({stored: '2'});

        field(broken, root);
        assert.equal(hidden.value, '2');
    });
    assert.equal(created.length, 0);
});

test('a root without its hidden input mounts nothing', () => {
    const {field, created} = widgetModule();
    const root = el('div', {'data-role': 'hcf-ui-select'});
    documentOf([root]);

    field(config(), root);

    assert.equal(created.length, 0);
});
