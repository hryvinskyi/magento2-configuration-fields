/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The category select field: Magento's searchable select component over the catalog's categories, and the hidden
 * input that posts the picked ids
 *
 * The root `[data-role="hcf-ui-select"]` holds the place where the component renders, bound by the template with a
 * `scope` binding to the component's name, and the hidden input `[data-role="hcf-ui-select-value"]` holding the
 * stored value.
 *
 * - Mounting creates the component under that name, with the categories nested by `option-tree`. The stored ids reach
 *   the component once it exists, never through its config: Magento renders `${ … }` in a component's config, and a
 *   stored value is not code.
 * - Handing over the stored ids writes nothing; the hidden input keeps the stored value until the person picks. Each
 *   pick then writes the hidden input in the stored form (`select-values`).
 * - While the value is inherited ("Use system value" checked) or locked, the component and the hidden input are
 *   disabled and the root carries `data-disabled="true"`. The widget follows the checkbox through its native `change`
 *   event and never changes it.
 *
 * Mounted through x-magento-init on the root with `{componentName, inputId, inheritId, disabled, multiple, value,
 * categories}`; a configuration that is not that shape mounts nothing, and the hidden input posts the stored value.
 * `mount(root, environment)` is the same with the parsed config, the checkbox and the component's creation passed in;
 * it mounts once per root and returns a teardown that removes the listener, the subscription and the component.
 */
define([
    'Magento_Ui/js/core/app',
    'uiRegistry',
    'Hryvinskyi_ConfigurationFields/js/option-tree',
    'Hryvinskyi_ConfigurationFields/js/select-values'
], function (app, registry, optionTree, selectValues) {
    'use strict';

    var VALUE = '[data-role="hcf-ui-select-value"]',
        COMPONENT = 'Magento_Ui/js/form/element/ui-select',
        ELEMENT_TEMPLATE = 'ui/grid/filters/elements/ui-select',
        mounted = new WeakSet();

    /**
     * @typedef {{
     *     componentName: string,
     *     inheritId: string,
     *     disabled: boolean,
     *     multiple: boolean,
     *     value: string,
     *     categories: Array<{id: string, parentId: string, label: string}>
     * }} Config
     * @typedef {{
     *     config: Config,
     *     inherit: ?HTMLInputElement,
     *     create: function(string, Object): void,
     *     whenCreated: function(string, function(Object): void): void
     * }} Environment
     */

    /**
     * The select component's config: a searchable tree of categories with chips, without a label of its own (the
     * configuration row prints the field's label)
     *
     * @param {Config} config
     * @param {boolean} readOnly
     * @returns {Object}
     */
    function componentConfig(config, readOnly) {
        return {
            component: COMPONENT,
            formElement: 'select',
            componentType: 'field',
            elementTmpl: ELEMENT_TEMPLATE,
            filterOptions: true,
            chipsEnabled: true,
            disableLabel: true,
            levelsVisibility: '1',
            multiple: config.multiple,
            disabled: readOnly,
            options: optionTree.nest(config.categories)
        };
    }

    /**
     * @param {HTMLElement} root
     * @param {Environment} environment
     * @returns {function(): void} Teardown
     */
    function mount(root, environment) {
        var hidden = root.querySelector(VALUE),
            config = environment.config,
            inherit = environment.inherit,
            locked = config.disabled && !(inherit && inherit.checked),
            component = null,
            subscription = null,
            controller;

        if (mounted.has(root) || !hidden) {
            return function () {};
        }
        mounted.add(root);
        controller = new AbortController();

        /**
         * @returns {boolean}
         */
        function isReadOnly() {
            return locked || Boolean(inherit && inherit.checked);
        }

        /**
         * Mirror the read-only state onto the root, the hidden input and the component, once it exists
         */
        function applyState() {
            var readOnly = isReadOnly();

            if (readOnly) {
                root.setAttribute('data-disabled', 'true');
            } else {
                root.removeAttribute('data-disabled');
            }
            hidden.disabled = readOnly;
            if (component) {
                component.disabled(readOnly);
            }
        }

        /**
         * Hand the stored ids to the created component, then post what it holds after each pick
         *
         * @param {Object} created
         */
        function bind(created) {
            if (controller.signal.aborted) {
                return;
            }
            component = created;
            component.value(selectValues.selected(config.value, config.multiple));
            subscription = component.value.subscribe(function (value) {
                hidden.value = selectValues.posted(value);
            });
            applyState();
        }

        environment.create(config.componentName, componentConfig(config, isReadOnly()));
        applyState();
        if (inherit) {
            inherit.addEventListener('change', applyState, {signal: controller.signal});
        }
        environment.whenCreated(config.componentName, bind);

        return function teardown() {
            controller.abort();
            if (subscription) {
                subscription.dispose();
            }
            if (component && typeof component.destroy === 'function') {
                component.destroy();
            }
            mounted.delete(root);
        };
    }

    /**
     * The configuration, or null when it is not the expected shape
     *
     * @param {*} config
     * @returns {?(Config & {inheritId: string})}
     */
    function parseConfig(config) {
        var categories;

        if (!config || typeof config !== 'object'
            || typeof config.componentName !== 'string' || config.componentName === ''
            || typeof config.inheritId !== 'string'
            || typeof config.disabled !== 'boolean'
            || typeof config.multiple !== 'boolean'
            || typeof config.value !== 'string'
        ) {
            return null;
        }
        categories = optionTree.parseRows(config.categories);

        return categories === null ? null : Object.freeze({
            componentName: config.componentName,
            inheritId: config.inheritId,
            disabled: config.disabled,
            multiple: config.multiple,
            value: config.value,
            categories: categories
        });
    }

    /**
     * The x-magento-init entry; a configuration that is not the expected shape mounts nothing, and the hidden input
     * posts the stored value
     *
     * @param {*} config
     * @param {HTMLElement} element
     */
    function entry(config, element) {
        var parsed = parseConfig(config),
            document = element.ownerDocument;

        if (!parsed) {
            return;
        }

        mount(element, {
            config: parsed,
            inherit: parsed.inheritId !== '' ? document.getElementById(parsed.inheritId) : null,
            create: function (name, settings) {
                var components = {};

                components[name] = settings;
                app({components: components});
            },
            whenCreated: function (name, callback) {
                registry.get(name, callback);
            }
        });
    }

    entry.mount = mount;

    return entry;
});
