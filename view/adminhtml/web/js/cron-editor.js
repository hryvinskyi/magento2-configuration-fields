/**
 * Copyright (c) 2025-2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The cron field: one input per part of the expression, a summary of it, and the hidden input that posts it
 *
 * The root `[data-role="hcf-cron"]` holds the summary `[data-role="hcf-cron-summary"]`, the five part inputs
 * `[data-role="hcf-cron-field"][data-part="minute|hour|dayOfMonth|month|dayOfWeek"]`, the syntax table
 * `[data-role="hcf-cron-syntax"]` whose `tbody[data-part]` rows belong to one part, and the hidden input
 * `[data-role="hcf-cron-value"]` holding the stored expression.
 *
 * - Mounting writes nothing: the parts show the stored expression as far as it parses (the rules are in
 *   `cron-fields`) and the hidden input keeps it until a part is edited. An empty field shows empty parts and no
 *   error, and saving it stores no expression.
 * - An edit that leaves every part valid writes the expression to the hidden input, with the sixth part the stored
 *   expression held, if any, kept after the five (Magento accepts a sixth part and never reads it), and shows the
 *   description of the five. One that clears every part empties the hidden input only when the field held no
 *   expression at load. Clearing a field that held one would stop a job that reads its schedule only from it, so
 *   every part carries
 *   `aria-invalid="true"`, the summary shows the cleared wording with `data-tone="error"`, and the hidden input keeps
 *   what it had. Otherwise each invalid part carries `aria-invalid="true"`, the summary shows the invalid wording
 *   with `data-tone="error"`, and the hidden input is left as it was.
 * - Focusing a part shows its rows of the syntax table; leaving it hides them.
 * - While the value is inherited ("Use system value" checked) or locked, the parts are disabled and the root carries
 *   `data-disabled="true"`. The widget follows the checkbox through its native `change` event and never changes it;
 *   the page's own handler on the checkbox does not reach a hidden input, so the widget enables the hidden input
 *   itself while the value is the field's own, and the expression is posted.
 * - The form rule `validate-cron-expression` is registered once, on the first mount. It judges the element it is
 *   given by that element's own widget: for the hidden input of a mounted widget, its parts as an edit of the
 *   expression the widget was mounted with, refusing a cleared one and invalid parts; for any other element, its
 *   value. Several cron fields on one page are validated independently. The stored expression is remembered at
 *   mount rather than read from the input's `defaultValue`: a hidden input's `defaultValue` follows every write to
 *   its `value`. For a widget's hidden input the rule's message is empty, because the summary already shows the
 *   cleared or the invalid-parts wording; for any other element it is the invalid-expression wording.
 *
 * - The summary describes a valid expression in the language of the interface. A valid expression the describer
 *   cannot put into words (a minute of 61, which the grammar accepts and the calendar never reaches) shows the
 *   `undescribable` wording in the normal tone: the field is acceptable and saves, so it is not an error.
 *
 * Mounted through x-magento-init on the root with `{inputId, inheritId, disabled, value, locale?, wording:
 * {invalidExpression, invalidFields, expressionRequired?, undescribable?, …}}`, where `locale` is Magento's code of
 * the interface language (`de_DE`; absent or unreadable gives English), `expressionRequired` is the cleared wording
 * and `undescribable` the wording for an expression that cannot be described; each falls back to `invalidExpression`
 * when absent. A configuration that is not that shape mounts nothing, and the hidden input posts the stored
 * expression. `mount(root, environment)` is the same with the describer and the inheritance checkbox passed in; it
 * mounts once per root and returns a teardown that removes every listener.
 */
define([
    'jquery',
    'Hryvinskyi_ConfigurationFields/js/cronstrue',
    'Hryvinskyi_ConfigurationFields/js/cron-fields',
    'Hryvinskyi_ConfigurationFields/js/cron-locale',
    'jquery/validate'
], function ($, cronstrue, cronFields, cronLocale) {
    'use strict';

    var FIELD = '[data-role="hcf-cron-field"]',
        SUMMARY = '[data-role="hcf-cron-summary"]',
        SYNTAX = '[data-role="hcf-cron-syntax"]',
        VALUE = '[data-role="hcf-cron-value"]',
        FIELD_ROLE = 'hcf-cron-field',
        RULE = 'validate-cron-expression',
        WORDING_KEYS = Object.freeze(['invalidExpression', 'invalidFields']),
        mounted = new WeakSet(),
        widgets = new WeakMap(),
        ruleRegisteredOn = new WeakSet();

    /**
     * @typedef {{
     *     invalidExpression: string,
     *     invalidFields: string,
     *     expressionRequired: string,
     *     undescribable: string
     * }} Wording
     * @typedef {{fields: Object<string, HTMLInputElement>, stored: string}} Widget
     * @typedef {{
     *     value: string,
     *     disabled: boolean,
     *     wording: Wording,
     *     inherit: ?HTMLInputElement,
     *     describe: function(string): string
     * }} Environment
     */

    /**
     * Every part input of a root, keyed by part name; null when one is missing
     *
     * @param {HTMLElement} root
     * @returns {?Object<string, HTMLInputElement>}
     */
    function fieldsOf(root) {
        var fields = {};

        Array.prototype.slice.call(root.querySelectorAll(FIELD)).forEach(function (field) {
            fields[field.getAttribute('data-part')] = field;
        });

        return cronFields.PARTS.every(function (part) {
            return Boolean(fields[part]);
        }) ? fields : null;
    }

    /**
     * The text of every part input
     *
     * @param {Object<string, HTMLInputElement>} fields
     * @returns {Object<string, string>}
     */
    function partsOf(fields) {
        var parts = {};

        cronFields.PARTS.forEach(function (part) {
            parts[part] = String(fields[part].value);
        });

        return parts;
    }

    /**
     * @param {HTMLElement} root
     * @param {Environment} environment
     * @returns {function(): void} Teardown
     */
    function mount(root, environment) {
        var fields = fieldsOf(root),
            summary = root.querySelector(SUMMARY),
            syntax = root.querySelector(SYNTAX),
            hidden = root.querySelector(VALUE),
            inherit = environment.inherit,
            wording = environment.wording,
            locked = environment.disabled && !(inherit && inherit.checked),
            controller,
            options,

            /**
             * What the widget shows, and writes, for each kind of evaluation
             */
            show = Object.freeze({
                empty: function (evaluation, write) {
                    markInvalid([]);
                    setSummary('', false);
                    if (write) {
                        hidden.value = '';
                    }
                },
                valid: function (evaluation, write) {
                    markInvalid([]);
                    describe(evaluation.expression);
                    if (write) {
                        hidden.value = evaluation.value;
                    }
                },
                invalid: function (evaluation) {
                    markInvalid(evaluation.invalidParts);
                    setSummary(wording.invalidFields, true);
                },
                cleared: function () {
                    markInvalid(cronFields.PARTS);
                    setSummary(wording.expressionRequired, true);
                }
            });

        if (mounted.has(root) || !fields || !hidden) {
            return function () {};
        }
        mounted.add(root);
        widgets.set(hidden, Object.freeze({fields: fields, stored: environment.value}));
        controller = new AbortController();
        options = {signal: controller.signal};

        /**
         * @param {string} text
         * @param {boolean} error
         */
        function setSummary(text, error) {
            if (!summary) {
                return;
            }
            summary.textContent = text;
            if (error) {
                summary.setAttribute('data-tone', 'error');
            } else {
                summary.removeAttribute('data-tone');
            }
        }

        /**
         * Show the description of a valid expression, or the undescribable wording when the describer refuses it
         *
         * @param {string} expression
         */
        function describe(expression) {
            var description;

            try {
                description = environment.describe(expression);
            } catch (refusal) {
                setSummary(wording.undescribable, false);

                return;
            }
            setSummary(description, false);
        }

        /**
         * @param {string[]} invalidParts
         */
        function markInvalid(invalidParts) {
            cronFields.PARTS.forEach(function (part) {
                if (invalidParts.indexOf(part) !== -1) {
                    fields[part].setAttribute('aria-invalid', 'true');
                } else {
                    fields[part].removeAttribute('aria-invalid');
                }
            });
        }

        /**
         * @param {boolean} write Whether the hidden input takes the result
         */
        function render(write) {
            var evaluation = cronFields.evaluateChange(partsOf(fields), environment.value);

            show[evaluation.state](evaluation, write);
        }

        /**
         * Show the syntax rows of one part, or of none
         *
         * @param {?string} part
         */
        function showSyntax(part) {
            if (!syntax) {
                return;
            }
            Array.prototype.slice.call(syntax.querySelectorAll('[data-part]')).forEach(function (rows) {
                if (rows.getAttribute('data-part') === part) {
                    rows.removeAttribute('hidden');
                } else {
                    rows.setAttribute('hidden', '');
                }
            });
        }

        /**
         * Mirror the read-only state onto the root and the controls
         */
        function applyState() {
            var readOnly = locked || Boolean(inherit && inherit.checked);

            if (readOnly) {
                root.setAttribute('data-disabled', 'true');
            } else {
                root.removeAttribute('data-disabled');
            }
            cronFields.PARTS.forEach(function (part) {
                fields[part].disabled = readOnly;
            });
            hidden.disabled = readOnly;
        }

        /**
         * The part input an event happened on, or null
         *
         * @param {Event} event
         * @returns {?HTMLInputElement}
         */
        function fieldOf(event) {
            var target = event.target;

            return target && target.getAttribute && target.getAttribute('data-role') === FIELD_ROLE
                && fields[target.getAttribute('data-part')] === target ? target : null;
        }

        /**
         * @param {Event} event
         */
        function onInput(event) {
            if (fieldOf(event)) {
                render(true);
            }
        }

        /**
         * @param {FocusEvent} event
         */
        function onFocusIn(event) {
            var field = fieldOf(event);

            if (field) {
                showSyntax(field.getAttribute('data-part'));
            }
        }

        /**
         * @param {FocusEvent} event
         */
        function onFocusOut(event) {
            if (fieldOf(event)) {
                showSyntax(null);
            }
        }

        (function fill() {
            var parts = cronFields.split(environment.value).parts;

            cronFields.PARTS.forEach(function (part) {
                fields[part].value = parts[part];
            });
        }());
        applyState();
        render(false);

        root.addEventListener('input', onInput, options);
        root.addEventListener('focusin', onFocusIn, options);
        root.addEventListener('focusout', onFocusOut, options);
        if (inherit) {
            inherit.addEventListener('change', applyState, options);
        }

        return function teardown() {
            controller.abort();
            mounted.delete(root);
            widgets.delete(hidden);
        };
    }

    /**
     * What the parts of the element's own widget amount to, or null when the element is not a mounted widget's
     * hidden input
     *
     * @param {*} element
     * @returns {?Object} The change evaluation of `cron-fields`
     */
    function evaluateWidgetOf(element) {
        var widget = widgets.get(element);

        return widget ? cronFields.evaluateChange(partsOf(widget.fields), widget.stored) : null;
    }

    /**
     * The form rule: whether the element's own widget holds an acceptable expression
     *
     * @param {*} value The element's value
     * @param {HTMLElement} element
     * @returns {boolean}
     */
    function validateElement(value, element) {
        var evaluation = evaluateWidgetOf(element);

        return evaluation
            ? cronFields.isAcceptable(evaluation)
            : cronFields.isValidExpression(typeof value === 'string' ? value : '');
    }

    /**
     * The form rule's message for the element it refused
     *
     * Empty for the hidden input of a mounted widget: its summary already shows why, in the error tone, so the label
     * the validator adds stays empty and the widget's stylesheet hides an empty one. A label another rule adds there
     * (a field's `required-entry`) carries that rule's text and shows. An element outside a widget has no summary,
     * so its label names the fault.
     *
     * @param {Wording} wording
     * @returns {function(*, HTMLElement): string}
     */
    function messageOf(wording) {
        return function (parameters, element) {
            return widgets.has(element) ? '' : wording.invalidExpression;
        };
    }

    /**
     * Register the form rule with a validator, once per validator
     *
     * @param {*} validator jQuery's `$.validator`
     * @param {Wording} wording
     */
    function registerRule(validator, wording) {
        if (!validator || typeof validator.addMethod !== 'function' || ruleRegisteredOn.has(validator)) {
            return;
        }
        ruleRegisteredOn.add(validator);
        validator.addMethod(RULE, validateElement, messageOf(wording));
    }

    /**
     * @param {*} value
     * @returns {boolean}
     */
    function isFilled(value) {
        return typeof value === 'string' && value !== '';
    }

    /**
     * The configuration, or null when it is not the expected shape
     *
     * @param {*} config
     * @returns {?{inheritId: string, disabled: boolean, value: string, locale: string, wording: Wording}}
     */
    function parseConfig(config) {
        var wording = {};

        if (!config || typeof config !== 'object'
            || typeof config.inheritId !== 'string'
            || typeof config.disabled !== 'boolean'
            || typeof config.value !== 'string'
            || !config.wording || typeof config.wording !== 'object'
        ) {
            return null;
        }

        if (!WORDING_KEYS.every(function (key) {
            wording[key] = config.wording[key];

            return isFilled(config.wording[key]);
        })) {
            return null;
        }
        wording.expressionRequired = isFilled(config.wording.expressionRequired)
            ? config.wording.expressionRequired
            : wording.invalidExpression;
        wording.undescribable = isFilled(config.wording.undescribable)
            ? config.wording.undescribable
            : wording.invalidExpression;

        return Object.freeze({
            inheritId: config.inheritId,
            disabled: config.disabled,
            value: config.value,
            locale: typeof config.locale === 'string' ? config.locale : '',
            wording: Object.freeze(wording)
        });
    }

    /**
     * The description of an expression in the locale of the interface
     *
     * @param {string} locale Magento's locale code, or an empty string
     * @returns {function(string): string}
     */
    function describerFor(locale) {
        var held = cronstrue.default && cronstrue.default.locales,
            available = held && typeof held === 'object' ? Object.keys(held) : [],
            options = {locale: cronLocale.resolve(locale, available)};

        return function (expression) {
            return cronstrue.toString(expression, options);
        };
    }

    /**
     * The x-magento-init entry; a configuration that is not the expected shape mounts nothing, and the hidden input
     * posts the stored expression
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

        registerRule($.validator, parsed.wording);
        mount(element, {
            value: parsed.value,
            disabled: parsed.disabled,
            wording: parsed.wording,
            inherit: parsed.inheritId !== '' ? document.getElementById(parsed.inheritId) : null,
            describe: describerFor(parsed.locale)
        });
    }

    entry.mount = mount;
    entry.validateElement = validateElement;

    return entry;
});
