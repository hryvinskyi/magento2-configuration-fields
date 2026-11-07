/**
 * Copyright (c) 2025-2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The tag-list field: chips for the values, a text input that adds one, and the hidden input that posts the list
 *
 * The root `[data-role="hcf-tag-list"]` holds the chips `[data-role="hcf-tag-list-chips"]`, the text input
 * `[data-role="hcf-tag-list-input"]`, the search input `[data-role="hcf-tag-list-search"]`, the error message
 * `[data-role="hcf-tag-list-error"]`, the chip `<template data-role="hcf-tag-list-chip-template">` and the hidden
 * input `[data-role="hcf-tag-list-value"]`, which the server prints already holding the values joined by the
 * separator. Mounting writes nothing to it; only an added or removed value does.
 *
 * - Enter adds the typed value. A value already listed shows the duplicate message; one the pattern refuses shows
 *   the invalid message and marks the text input `aria-invalid`. The message hides after three seconds or on the
 *   next keystroke.
 * - A paste of several entries (split by the rules of `tag-values`) adds each of them; the first refused entry is
 *   left in the text input with its message. A paste of one entry is left to the browser.
 * - Backspace in the empty text input removes the last value; a chip's remove button removes its value; a click on
 *   the chip area outside a chip focuses the text input.
 * - Once the chips outgrow 500 pixels, the search input shows and filters the chips by substring, and the root
 *   carries `data-overflowing="true"`; it hides again when they fit.
 * - While the value is inherited ("Use system value" checked) or locked, the widget is read-only: nothing is added or
 *   removed, the remove buttons are disabled and the root carries `data-disabled="true"`. The widget follows the
 *   checkbox through its native `change` event and never changes it: the page's own handler on the checkbox enables
 *   and disables the field's visible controls, but not a hidden input, so the widget enables the hidden input itself
 *   while the value is the field's own, and the list is posted.
 *
 * Mounted through x-magento-init on the root with `{inputId, inheritId, disabled, values, separator, validation,
 * validationMessage, uppercase, wording: {remove, duplicate}}` (`remove` holds `%1` for the value; the search input's
 * wording is printed by the server); a configuration that is not that shape mounts nothing, and the hidden input posts the value the server printed. `mount(root,
 * environment)` is the same with the chip factory, the timer, the inheritance checkbox and the reporter passed in;
 * it mounts once per root and returns a teardown that removes every listener.
 */
define([
    'Hryvinskyi_ConfigurationFields/js/tag-values'
], function (tagValues) {
    'use strict';

    var CHIPS = '[data-role="hcf-tag-list-chips"]',
        CHIP = '[data-role="hcf-tag-list-chip"]',
        CHIP_TEXT = '.hcf-tag-list__text',
        REMOVE = '[data-role="hcf-tag-list-remove"]',
        INPUT = '[data-role="hcf-tag-list-input"]',
        SEARCH = '[data-role="hcf-tag-list-search"]',
        ERROR = '[data-role="hcf-tag-list-error"]',
        VALUE = '[data-role="hcf-tag-list-value"]',
        TEMPLATE = '[data-role="hcf-tag-list-chip-template"]',
        INPUT_ROLE = 'hcf-tag-list-input',
        SEARCH_ROLE = 'hcf-tag-list-search',
        ERROR_DELAY_MS = 3000,
        SEARCH_THRESHOLD_PX = 500,
        WORDING_KEYS = Object.freeze(['remove', 'duplicate']),
        AS_STORED = Object.freeze({
            uppercase: false,
            accepts: function () {
                return true;
            }
        }),
        mounted = new WeakSet();

    /**
     * @typedef {{remove: string, duplicate: string, invalid: string}} Wording
     * @typedef {{
     *     values: string[],
     *     separator: string,
     *     validation: string,
     *     uppercase: boolean,
     *     disabled: boolean,
     *     wording: Wording,
     *     inherit: ?HTMLInputElement,
     *     createChip: function(string, string): HTMLElement,
     *     schedule: function(function(): void, number): function(): void,
     *     report: function(Error): void
     * }} Environment
     */

    /**
     * @param {HTMLElement} root
     * @param {Environment} environment
     * @returns {function(): void} Teardown
     */
    function mount(root, environment) {
        var chips = root.querySelector(CHIPS),
            input = root.querySelector(INPUT),
            search = root.querySelector(SEARCH),
            error = root.querySelector(ERROR),
            hidden = root.querySelector(VALUE),
            inherit = environment.inherit,
            wording = environment.wording,
            validator = tagValues.validator(environment.validation),
            rules = Object.freeze({uppercase: environment.uppercase, accepts: validator.accepts}),
            locked = environment.disabled && !(inherit && inherit.checked),
            values = [],
            chipsByValue = new Map(),
            cancelHide = null,
            controller,
            options,

            /**
             * What the widget shows after one entry is refused or added
             */
            onAdd = Object.freeze({
                added: function (result) {
                    values = result.values;
                    appendChip(result.value);
                    input.value = '';
                    changed();
                },
                empty: function () {},
                invalid: function () {
                    showError(wording.invalid, true);
                },
                duplicate: function () {
                    showError(wording.duplicate, false);
                }
            });

        if (mounted.has(root) || !chips || !input || !hidden) {
            return function () {};
        }
        mounted.add(root);
        controller = new AbortController();
        options = {signal: controller.signal};

        /**
         * @returns {boolean}
         */
        function isReadOnly() {
            return locked || Boolean(inherit && inherit.checked);
        }

        /**
         * @param {string} message
         * @param {boolean} invalid Whether the text input's value is the cause
         */
        function showError(message, invalid) {
            if (cancelHide) {
                cancelHide();
            }
            if (invalid) {
                input.setAttribute('aria-invalid', 'true');
            }
            if (error) {
                error.textContent = message;
                error.removeAttribute('hidden');
            }
            cancelHide = environment.schedule(clearError, ERROR_DELAY_MS);
        }

        /**
         * Hide the message and the invalid mark
         */
        function clearError() {
            if (cancelHide) {
                cancelHide();
                cancelHide = null;
            }
            input.removeAttribute('aria-invalid');
            if (error) {
                error.textContent = '';
                error.setAttribute('hidden', '');
            }
        }

        /**
         * Show or hide a chip by the search query
         *
         * @param {HTMLElement} chip
         * @param {string} value
         */
        function filterChip(chip, value) {
            if (search && !tagValues.matches(value, search.value)) {
                chip.setAttribute('hidden', '');
            } else {
                chip.removeAttribute('hidden');
            }
        }

        /**
         * Apply the search query to every chip
         */
        function filterAll() {
            chipsByValue.forEach(filterChip);
        }

        /**
         * Show the search input while the chips outgrow their area, and hide it, clearing the query, once they fit
         */
        function updateSearch() {
            var overflowing = chips.scrollHeight > SEARCH_THRESHOLD_PX;

            if (overflowing) {
                root.setAttribute('data-overflowing', 'true');
            } else {
                root.removeAttribute('data-overflowing');
            }
            if (!search) {
                return;
            }
            if (overflowing) {
                search.removeAttribute('hidden');

                return;
            }
            if (!search.hasAttribute('hidden')) {
                search.setAttribute('hidden', '');
                search.value = '';
                filterAll();
            }
        }

        /**
         * @param {string} value
         */
        function appendChip(value) {
            var chip = environment.createChip(value, wording.remove.split('%1').join(value)),
                button = chip.querySelector(REMOVE);

            if (button) {
                button.disabled = isReadOnly();
            }
            chipsByValue.set(value, chip);
            filterChip(chip, value);
            chips.appendChild(chip);
        }

        /**
         * Write the list to the hidden input after a change
         */
        function changed() {
            hidden.value = tagValues.join(values, environment.separator);
            updateSearch();
        }

        /**
         * @param {string} value
         */
        function removeValue(value) {
            var chip = chipsByValue.get(value);

            if (isReadOnly() || !chip) {
                return;
            }
            values = tagValues.remove(values, value);
            chipsByValue.delete(value);
            chip.remove();
            clearError();
            changed();
        }

        /**
         * Mirror the read-only state onto the root and the controls
         */
        function applyState() {
            var readOnly = isReadOnly();

            if (readOnly) {
                root.setAttribute('data-disabled', 'true');
                clearError();
            } else {
                root.removeAttribute('data-disabled');
            }
            input.disabled = readOnly;
            hidden.disabled = readOnly;
            chipsByValue.forEach(function (chip) {
                var button = chip.querySelector(REMOVE);

                if (button) {
                    button.disabled = readOnly;
                }
            });
        }

        /**
         * @param {KeyboardEvent} event
         */
        function onKeydown(event) {
            var role = event.target && event.target.getAttribute ? event.target.getAttribute('data-role') : null,
                result;

            if (role === SEARCH_ROLE && event.key === 'Enter') {
                event.preventDefault();

                return;
            }
            if (role !== INPUT_ROLE) {
                return;
            }
            clearError();
            if (event.key === 'Enter') {
                event.preventDefault();
                if (!isReadOnly()) {
                    result = tagValues.add(values, input.value, rules);
                    onAdd[result.outcome](result);
                }

                return;
            }
            if (event.key === 'Backspace' && input.value === '' && values.length > 0) {
                removeValue(values[values.length - 1]);
            }
        }

        /**
         * @param {ClipboardEvent} event
         */
        function onPaste(event) {
            var text = event.clipboardData ? event.clipboardData.getData('text') : '',
                entries,
                result;

            if (!event.target || event.target !== input) {
                return;
            }
            entries = tagValues.splitPasted(text, environment.separator);
            if (entries.length < 2) {
                return;
            }
            event.preventDefault();
            if (isReadOnly()) {
                return;
            }
            clearError();
            result = tagValues.addAll(values, entries, rules);
            values = result.values;
            result.added.forEach(appendChip);
            input.value = result.invalid.length > 0 ? result.invalid[0] : '';
            if (result.added.length > 0) {
                changed();
            }
            if (result.invalid.length > 0) {
                showError(wording.invalid, true);
            } else if (result.duplicate.length > 0) {
                showError(wording.duplicate, false);
            }
        }

        /**
         * @param {Event} event
         */
        function onInput(event) {
            if (search && event.target === search) {
                filterAll();
            }
        }

        /**
         * @param {MouseEvent} event
         */
        function onClick(event) {
            var target = event.target && typeof event.target.closest === 'function' ? event.target : null,
                button = target ? target.closest(REMOVE) : null,
                chip;

            if (button && root.contains(button)) {
                chip = button.closest(CHIP);
                if (chip) {
                    removeValue(chip.getAttribute('data-value'));
                }
                if (!input.disabled) {
                    input.focus();
                }

                return;
            }
            if (target && !target.closest(CHIP) && target.closest(CHIPS) === chips && !input.disabled) {
                input.focus();
            }
        }

        // The stored values are shown as stored: the pattern and the upper-casing apply to what is typed or pasted.
        values = tagValues.addAll([], environment.values, AS_STORED).values;
        values.forEach(appendChip);
        if (validator.error) {
            environment.report(validator.error);
        }
        applyState();
        updateSearch();

        root.addEventListener('keydown', onKeydown, options);
        root.addEventListener('paste', onPaste, options);
        root.addEventListener('input', onInput, options);
        root.addEventListener('click', onClick, options);
        if (inherit) {
            inherit.addEventListener('change', applyState, options);
        }

        return function teardown() {
            controller.abort();
            if (cancelHide) {
                cancelHide();
                cancelHide = null;
            }
            mounted.delete(root);
        };
    }

    /**
     * @param {*} value
     * @returns {boolean}
     */
    function isFilled(value) {
        return typeof value === 'string' && value !== '';
    }

    /**
     * The wording, or null when a phrase is missing
     *
     * @param {*} value
     * @returns {?Object<string, string>}
     */
    function parseWording(value) {
        var wording = {};

        if (!value || typeof value !== 'object') {
            return null;
        }

        return WORDING_KEYS.every(function (key) {
            wording[key] = value[key];

            return isFilled(value[key]);
        }) ? wording : null;
    }

    /**
     * The configuration, or null when it is not the expected shape
     *
     * @param {*} config
     * @returns {?{inheritId: string, disabled: boolean, values: string[], separator: string, validation: string,
     *     uppercase: boolean, wording: Wording}}
     */
    function parseConfig(config) {
        var wording;

        if (!config || typeof config !== 'object'
            || typeof config.inheritId !== 'string'
            || typeof config.disabled !== 'boolean'
            || !Array.isArray(config.values)
            || !config.values.every(function (value) {
                return typeof value === 'string';
            })
            || !isFilled(config.separator)
            || typeof config.validation !== 'string'
            || !isFilled(config.validationMessage)
            || typeof config.uppercase !== 'boolean'
        ) {
            return null;
        }
        wording = parseWording(config.wording);

        return wording ? Object.freeze({
            inheritId: config.inheritId,
            disabled: config.disabled,
            values: config.values.slice(),
            separator: config.separator,
            validation: config.validation,
            uppercase: config.uppercase,
            wording: Object.freeze({
                remove: wording.remove,
                duplicate: wording.duplicate,
                invalid: config.validationMessage
            })
        }) : null;
    }

    /**
     * A chip factory that clones the chip template: its text is the value, its `data-value` the value, its remove
     * button's `aria-label` the given label
     *
     * @param {HTMLTemplateElement} template
     * @returns {function(string, string): HTMLElement}
     */
    function chipFactory(template) {
        return function createChip(value, removeLabel) {
            var chip = template.content.firstElementChild.cloneNode(true),
                text = chip.querySelector(CHIP_TEXT),
                button = chip.querySelector(REMOVE);

            chip.setAttribute('data-value', value);
            if (text) {
                text.textContent = value;
            }
            if (button) {
                button.setAttribute('aria-label', removeLabel);
            }

            return chip;
        };
    }

    /**
     * The x-magento-init entry; a configuration or markup that is not the expected shape mounts nothing, and the
     * hidden input posts the value the server printed
     *
     * @param {*} config
     * @param {HTMLElement} element
     */
    function entry(config, element) {
        var parsed = parseConfig(config),
            document = element.ownerDocument,
            view = document.defaultView,
            template = element.querySelector(TEMPLATE);

        if (!parsed || !template || !template.content || !template.content.firstElementChild) {
            return;
        }

        mount(element, {
            values: parsed.values,
            separator: parsed.separator,
            validation: parsed.validation,
            uppercase: parsed.uppercase,
            disabled: parsed.disabled,
            wording: parsed.wording,
            inherit: parsed.inheritId !== '' ? document.getElementById(parsed.inheritId) : null,
            createChip: chipFactory(template),
            schedule: function (callback, delay) {
                var id = view.setTimeout(callback, delay);

                return function () {
                    view.clearTimeout(id);
                };
            },
            report: function (cause) {
                view.console.error(
                    'The tag list\'s validation pattern is not a valid regular expression; every value is accepted.',
                    cause
                );
            }
        });
    }

    entry.mount = mount;
    entry.chipFactory = chipFactory;

    return entry;
});
