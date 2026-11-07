/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * The rules of a cron expression as Magento's cron schedule reads it, with no DOM and no wording
 *
 * - The parts, in order: minute, hour, dayOfMonth, month, dayOfWeek. Every part follows the same grammar, the one
 *   Magento's scheduler matches each part with: `*`, a value, a range `a-b`, or a step `x/n` whose start is `*`, a
 *   value or a range, and a comma list of any of these. A value is a number written in digits, or a word whose first
 *   three letters, in any case, name a month (`jan` … `dec`) or a weekday (`sun` … `sat`), the only letters Magento
 *   reads (`january`, `Mon`). A step is a number that is not zero. A number, a step included, has at most two digits
 *   besides its leading zeros: no part of a schedule counts past 59, and a number too large for an integer makes
 *   Magento's scheduler fail converting it every time it runs. A number outside the calendar but within two digits (a
 *   minute of 61) is accepted: Magento accepts it and never matches it.
 * - What Magento's scheduler fails on is refused: an empty list entry, a range or a step with more than two sides, a
 *   word that names no month or weekday (`L`, `?`), a step of zero. Some spellings it also runs without an error are
 *   refused too, because no cron syntax writes them: a number with a sign, a fraction or an exponent, a step with an
 *   empty or unreadable start, and an unreadable list entry after one that matches every time.
 * - A stored expression is split on runs of the whitespace Magento's scheduler splits on (space, tab, line feed,
 *   vertical tab, form feed, carriage return), and that whitespace is trimmed from its ends; any other character, a
 *   no-break space included, belongs to a part, as it does for the scheduler. Five parts fit, and so do six: Magento
 *   accepts a sixth part and never reads it, so it is kept apart, unchecked, and written back after the five. Any
 *   other count is invalid; beyond six, the extra parts stay with the last one, so the expression is shown as far as
 *   it parses and the last part carries the fault.
 * - A set of parts is empty when every part is empty (the field holds no expression), valid when every part is
 *   valid, and invalid otherwise; an empty part beside filled ones is invalid.
 * - Judged as an edit of a field, emptying every part of a field that held an expression is "cleared", not empty: a
 *   job that reads its schedule only from that field would never run again, so such a change cannot be saved. A
 *   field that held nothing may stay empty. Only an empty or a valid evaluation may be saved.
 */
define([], function () {
    'use strict';

    var PARTS = Object.freeze(['minute', 'hour', 'dayOfMonth', 'month', 'dayOfWeek']),
        NAMES = Object.freeze([
            'jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec',
            'sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'
        ]),
        NUMBER = /^0*[0-9]{1,2}$/,
        WORD = /^[a-z]{3,}$/i,
        STEP = /^0*[1-9][0-9]?$/,
        WHITESPACE = /[ \t\n\v\f\r]+/,
        EDGE_WHITESPACE = /^[ \t\n\v\f\r]+|[ \t\n\v\f\r]+$/g,
        MAX_PARTS = PARTS.length + 1,
        ACCEPTABLE = Object.freeze({empty: true, valid: true, invalid: false, cleared: false});

    /**
     * @typedef {Object<string, string>} Parts Every part's text, keyed by part name
     * @typedef {{state: 'empty'}|{state: 'valid', expression: string}|{state: 'invalid', invalidParts: string[]}}
     *     Evaluation
     * @typedef {{state: 'empty'}|{state: 'valid', expression: string, value: string}|
     *     {state: 'invalid', invalidParts: string[]}|{state: 'cleared'}} ChangeEvaluation The `value` of a valid
     *     change is what the field saves: the five parts, and the sixth part the field held, if any
     */

    /**
     * A text without the whitespace Magento's scheduler splits on at its ends
     *
     * @param {string} text
     * @returns {string}
     */
    function strip(text) {
        return String(text).replace(EDGE_WHITESPACE, '');
    }

    /**
     * Whether a text is a value: a number of at most two digits besides its leading zeros, or a word Magento reads as a
     * month or a weekday
     *
     * @param {string} text
     * @returns {boolean}
     */
    function isValue(text) {
        return NUMBER.test(text) || (WORD.test(text) && NAMES.indexOf(text.slice(0, 3).toLowerCase()) !== -1);
    }

    /**
     * Whether a text is a value or a range of two values
     *
     * @param {string} text
     * @returns {boolean}
     */
    function isValueOrRange(text) {
        var sides = text.split('-');

        return sides.length === 1 ? isValue(text) : sides.length === 2 && sides.every(isValue);
    }

    /**
     * Whether one entry of a comma list is valid: `*`, a value, a range, or a step on one of these
     *
     * @param {string} entry
     * @returns {boolean}
     */
    function isValidEntry(entry) {
        var step = entry.split('/');

        if (step.length > 2) {
            return false;
        }
        if (step.length === 2 && !STEP.test(step[1])) {
            return false;
        }

        return step[0] === '*' || isValueOrRange(step[0]);
    }

    /**
     * Whether one part's text is valid; a name that is not a part is never valid
     *
     * @param {string} part
     * @param {string} value
     * @returns {boolean}
     */
    function isValidPart(part, value) {
        return PARTS.indexOf(part) !== -1 && String(value).split(',').every(isValidEntry);
    }

    /**
     * A stored expression as its five parts and the sixth Magento accepts, and whether it holds five or six parts (an
     * empty expression fits)
     *
     * @param {string} expression
     * @returns {{parts: Parts, fits: boolean, sixth: string}}
     */
    function split(expression) {
        var trimmed = strip(expression),
            tokens = trimmed === '' ? [] : trimmed.split(WHITESPACE),
            hasSixth = tokens.length === MAX_PARTS,
            fields = hasSixth ? tokens.slice(0, PARTS.length) : tokens,
            last = PARTS.length - 1,
            parts = {};

        PARTS.forEach(function (part, index) {
            parts[part] = index < last
                ? (fields[index] || '')
                : fields.slice(last).join(' ');
        });

        return {
            parts: parts,
            fits: tokens.length === 0 || tokens.length === PARTS.length || hasSixth,
            sixth: hasSixth ? tokens[PARTS.length] : ''
        };
    }

    /**
     * Five parts as one expression
     *
     * @param {Parts} parts
     * @returns {string}
     */
    function compose(parts) {
        return PARTS.map(function (part) {
            return strip(parts[part] || '');
        }).join(' ');
    }

    /**
     * What a set of parts amounts to
     *
     * @param {Parts} parts
     * @returns {Evaluation}
     */
    function evaluate(parts) {
        var texts = PARTS.map(function (part) {
                return strip(parts[part] || '');
            }),
            invalidParts;

        if (texts.every(function (text) {
            return text === '';
        })) {
            return {state: 'empty'};
        }
        invalidParts = PARTS.filter(function (part, index) {
            return !isValidPart(part, texts[index]);
        });

        return invalidParts.length === 0
            ? {state: 'valid', expression: texts.join(' ')}
            : {state: 'invalid', invalidParts: invalidParts};
    }

    /**
     * What a set of parts amounts to as an edit of a field that held the stored expression
     *
     * @param {Parts} parts
     * @param {string} storedExpression The field's expression before the edit
     * @returns {ChangeEvaluation}
     */
    function evaluateChange(parts, storedExpression) {
        var evaluation = evaluate(parts),
            sixth = split(storedExpression).sixth;

        if (evaluation.state === 'empty' && strip(storedExpression) !== '') {
            return {state: 'cleared'};
        }

        return evaluation.state === 'valid'
            ? {
                state: 'valid',
                expression: evaluation.expression,
                value: sixth === '' ? evaluation.expression : evaluation.expression + ' ' + sixth
            }
            : evaluation;
    }

    /**
     * Whether an evaluation may be saved: only an empty or a valid one
     *
     * @param {ChangeEvaluation} evaluation
     * @returns {boolean}
     */
    function isAcceptable(evaluation) {
        return ACCEPTABLE[evaluation.state] === true;
    }

    /**
     * Whether a stored expression is acceptable: empty, or five valid parts with or without a sixth
     *
     * @param {string} expression
     * @returns {boolean}
     */
    function isValidExpression(expression) {
        var result = split(expression);

        return result.fits && isAcceptable(evaluate(result.parts));
    }

    return Object.freeze({
        PARTS: PARTS,
        isValidPart: isValidPart,
        split: split,
        compose: compose,
        evaluate: evaluate,
        evaluateChange: evaluateChange,
        isAcceptable: isAcceptable,
        isValidExpression: isValidExpression
    });
});
