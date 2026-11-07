/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The rules of a tag list's values, with no DOM and no wording: how a typed or pasted entry becomes a value, which
 * values the field accepts, and how the list is joined for the hidden input
 *
 * - An entry is trimmed, and upper-cased when the field asks for it.
 * - Pasted text splits on the field's separator and on line breaks (`\r\n`, `\n`, `\r`), never on spaces, and on
 *   commas only when the separator is a comma: values such as `SW1A 1AA` or a URL with a comma stay whole.
 * - A value is accepted when it matches the field's pattern. A pattern that is not a valid regular expression accepts
 *   every value; the validator carries the error, so the caller can report it once.
 * - A list never holds the same value twice: adding a value already listed is its own outcome, not a silent no-op.
 *
 * Every function returns a new list; none changes the list it is given.
 */
define([], function () {
    'use strict';

    var LINE_BREAK = /\r\n|\n|\r/;

    /**
     * @typedef {{accepts: function(string): boolean, error: ?Error}} Validator
     * @typedef {{uppercase: boolean, accepts: function(string): boolean}} Rules
     * @typedef {{outcome: 'added', value: string, values: string[]}
     *     |{outcome: 'empty'}
     *     |{outcome: 'invalid', value: string}
     *     |{outcome: 'duplicate', value: string}} AddResult
     * @typedef {{values: string[], added: string[], invalid: string[], duplicate: string[]}} AddAllResult
     */

    /**
     * One raw entry as the value it stands for
     *
     * @param {string} raw
     * @param {boolean} uppercase
     * @returns {string}
     */
    function normalise(raw, uppercase) {
        var value = String(raw).trim();

        return uppercase ? value.toUpperCase() : value;
    }

    /**
     * The entries of pasted text, without the empty ones
     *
     * @param {string} text
     * @param {string} separator
     * @returns {string[]}
     */
    function splitPasted(text, separator) {
        var pieces = separator === '' ? [String(text)] : String(text).split(separator);

        return pieces
            .reduce(function (entries, piece) {
                return entries.concat(piece.split(LINE_BREAK));
            }, [])
            .map(function (entry) {
                return entry.trim();
            })
            .filter(function (entry) {
                return entry !== '';
            });
    }

    /**
     * The validator of a pattern; one that is not a valid regular expression accepts everything and carries its error
     *
     * @param {string} pattern
     * @returns {Validator}
     */
    function validator(pattern) {
        var expression;

        try {
            expression = new RegExp(pattern);
        } catch (error) {
            return Object.freeze({
                accepts: function () {
                    return true;
                },
                error: error
            });
        }

        return Object.freeze({
            accepts: function (value) {
                return expression.test(value);
            },
            error: null
        });
    }

    /**
     * Add one raw entry to a list
     *
     * @param {string[]} values
     * @param {string} raw
     * @param {Rules} rules
     * @returns {AddResult}
     */
    function add(values, raw, rules) {
        var value = normalise(raw, rules.uppercase);

        if (value === '') {
            return {outcome: 'empty'};
        }
        if (!rules.accepts(value)) {
            return {outcome: 'invalid', value: value};
        }
        if (values.indexOf(value) !== -1) {
            return {outcome: 'duplicate', value: value};
        }

        return {outcome: 'added', value: value, values: values.concat([value])};
    }

    /**
     * Add several raw entries in order, collecting what was added and what was refused
     *
     * @param {string[]} values
     * @param {string[]} entries
     * @param {Rules} rules
     * @returns {AddAllResult}
     */
    function addAll(values, entries, rules) {
        var collect = {
            added: function (result, state) {
                state.values = result.values;
                state.added.push(result.value);
            },
            empty: function () {},
            invalid: function (result, state) {
                state.invalid.push(result.value);
            },
            duplicate: function (result, state) {
                state.duplicate.push(result.value);
            }
        };

        return entries.reduce(function (state, entry) {
            var result = add(state.values, entry, rules);

            collect[result.outcome](result, state);

            return state;
        }, {values: values.slice(), added: [], invalid: [], duplicate: []});
    }

    /**
     * The list without one value
     *
     * @param {string[]} values
     * @param {string} value
     * @returns {string[]}
     */
    function remove(values, value) {
        return values.filter(function (known) {
            return known !== value;
        });
    }

    /**
     * The list as the hidden input posts it
     *
     * @param {string[]} values
     * @param {string} separator
     * @returns {string}
     */
    function join(values, separator) {
        return values.join(separator);
    }

    /**
     * Whether a value matches a search query: a case-insensitive substring; an empty query matches every value
     *
     * @param {string} value
     * @param {string} query
     * @returns {boolean}
     */
    function matches(value, query) {
        var needle = String(query).trim().toLowerCase();

        return needle === '' || value.toLowerCase().indexOf(needle) !== -1;
    }

    return Object.freeze({
        normalise: normalise,
        splitPasted: splitPasted,
        validator: validator,
        add: add,
        addAll: addAll,
        remove: remove,
        join: join,
        matches: matches
    });
});
