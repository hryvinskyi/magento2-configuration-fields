/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const {load} = require('./amd.cjs');
const {plain} = require('./fake-dom.cjs');

const cronFields = load('Hryvinskyi_ConfigurationFields/js/cron-fields');

/**
 * @param {string} minute
 * @param {string} hour
 * @param {string} dayOfMonth
 * @param {string} month
 * @param {string} dayOfWeek
 * @returns {Object<string, string>}
 */
const parts = (minute, hour, dayOfMonth, month, dayOfWeek) => ({minute, hour, dayOfMonth, month, dayOfWeek});

test('the parts come in the order of a cron expression', () => {
    assert.deepEqual(plain(cronFields.PARTS), ['minute', 'hour', 'dayOfMonth', 'month', 'dayOfWeek']);
});

test('each part accepts what Magento\'s own cron schedule tests run without an error', () => {
    // The parts Magento's Schedule::matchCronExpression() is tested with and matches without throwing
    const magentoParts = [
        '*', '0,1,20', '0,1,22', '0,1,*', '0-20', '*/2', '*/20', '0-20/5', '3-20/5',
        '1/5', '5/5', '10/5', '4/5', '8/5', '13/5'
    ];
    // Month and weekday names, which Magento reads by their first three letters in any case
    const names = ['JAN', 'DEC', 'JAN-DEC', 'SUN', 'SAT', 'mon', 'jan', 'Mon-Fri', 'january-june/2', 'monday'];

    cronFields.PARTS.forEach((part) => [...magentoParts, ...names].forEach((value) => {
        assert.equal(cronFields.isValidPart(part, value), true, `${part} ${value}`);
    }));
});

test('a step on a range, and a list of ranges with steps, are accepted', () => {
    assert.equal(cronFields.isValidPart('minute', '1-30/5'), true);
    assert.equal(cronFields.isValidPart('minute', '0-5,10-59/5'), true);
    assert.equal(cronFields.isValidPart('dayOfMonth', '2-10,15-25'), true);
});

test('a number outside the calendar range is accepted, as Magento accepts it and never matches it', () => {
    assert.equal(cronFields.isValidPart('minute', '61'), true);
    assert.equal(cronFields.isValidPart('hour', '24'), true);
    assert.equal(cronFields.isValidPart('dayOfWeek', '7'), true);
});

test('each part refuses what Magento\'s cron schedule fails on, and an empty text', () => {
    // The parts Magento's Schedule::matchCronExpression() is tested to throw on, the letters its setCronExpr() test
    // keeps although matching them fails, a word that names no month or weekday, and an empty list entry
    const refused = [
        '1/2/3', '1/', '-', '1-2-3', '0/0', '*/00', '?', 'L', 'W', 'C', ',', '1,', 'a', 'mo', 'abc', '5-', '',
        '+5', '1.5', '1e2', '/5'
    ];

    cronFields.PARTS.forEach((part) => refused.forEach((value) => {
        assert.equal(cronFields.isValidPart(part, value), false, `${part} ${value}`);
    }));
});

test('a step of zero is refused in every spelling, as Magento\'s scheduler fails on it every time it runs', () => {
    const zeroSteps = ['*/0', '*/00', '0/0', '5/0', '1-5/0', 'jan-dec/0', '1,*/0'];

    cronFields.PARTS.forEach((part) => zeroSteps.forEach((value) => {
        assert.equal(cronFields.isValidPart(part, value), false, `${part} ${value}`);
    }));
    assert.equal(cronFields.isValidExpression('*/0 * * * *'), false);
    assert.equal(cronFields.isValidExpression('* * * * 1-5/0'), false);
    assert.equal(cronFields.isValidExpression('*/10 * * * 1-5/1'), true);
});

test('a number or a step has at most two digits besides its leading zeros', () => {
    const accepted = ['99', '007', '00099', '*/99', '0-99/99', '*/007'];
    const refused = [
        '100', '0100', '9223372036854775807', '9223372036854775808', '99999999999999999999',
        '1-100', '100-1', '*/100', '1-5/100', '*/99999999999999999999', '1,99999999999999999999'
    ];

    cronFields.PARTS.forEach((part) => {
        accepted.forEach((value) => assert.equal(cronFields.isValidPart(part, value), true, `${part} ${value}`));
        refused.forEach((value) => assert.equal(cronFields.isValidPart(part, value), false, `${part} ${value}`));
    });
    assert.equal(cronFields.isValidExpression('99999999999999999999 * * * *'), false);
    assert.equal(cronFields.isValidExpression('*/99999999999999999999 * * * *'), false);
    assert.equal(cronFields.isValidExpression('*/100 * * * *'), false);
    assert.equal(cronFields.isValidExpression('99 99 99 99 99'), true);
});

test('a name that is not a part is never valid', () => {
    assert.equal(cronFields.isValidPart('second', '0'), false);
    assert.equal(cronFields.isValidPart('constructor', '0'), false);
});

test('a stored expression splits on runs of whitespace after trimming', () => {
    assert.deepEqual(
        plain(cronFields.split('  */5\t0  * *   1-5 ')),
        {parts: parts('*/5', '0', '*', '*', '1-5'), fits: true, sixth: ''}
    );
});

test('only the whitespace Magento\'s scheduler splits on separates parts; a no-break space belongs to a part', () => {
    assert.deepEqual(
        plain(cronFields.split('\u00a00 0\u00a0* * *')),
        {parts: parts('\u00a00', '0\u00a0*', '*', '*', ''), fits: false, sixth: ''}
    );
    assert.equal(cronFields.isValidExpression('0\u00a00 * * * *'), false);
    assert.equal(cronFields.isValidExpression('\u00a00 0 * * *'), false);
    assert.equal(cronFields.isValidExpression('\v0\f0\r*\n*\t* '), true);
});

test('an empty expression gives five empty parts and fits', () => {
    assert.deepEqual(plain(cronFields.split('')), {parts: parts('', '', '', '', ''), fits: true, sixth: ''});
});

test('fewer than five parts do not fit, and the missing parts are empty', () => {
    assert.deepEqual(plain(cronFields.split('*/5 0')), {parts: parts('*/5', '0', '', '', ''), fits: false, sixth: ''});
});

test('a sixth part fits and is kept apart from the five, as Magento accepts and never reads it', () => {
    assert.deepEqual(
        plain(cronFields.split('0 0 * * * 2026')),
        {parts: parts('0', '0', '*', '*', '*'), fits: true, sixth: '2026'}
    );
});

test('more than six parts do not fit, and the extra parts stay with the last one', () => {
    assert.deepEqual(
        plain(cronFields.split('0 0 * * * 2026 x')),
        {parts: parts('0', '0', '*', '*', '* 2026 x'), fits: false, sixth: ''}
    );
});

test('five parts compose into one expression', () => {
    assert.equal(cronFields.compose(parts(' 0', '3 ', '*', '*', '1')), '0 3 * * 1');
});

test('parts that are all empty evaluate as empty', () => {
    assert.deepEqual(plain(cronFields.evaluate(parts('', ' ', '', '', ''))), {state: 'empty'});
});

test('valid parts evaluate as the composed expression', () => {
    assert.deepEqual(plain(cronFields.evaluate(parts('0', '3', '*', '*', '1'))), {state: 'valid', expression: '0 3 * * 1'});
});

test('invalid parts are named, and an empty part beside filled ones is invalid', () => {
    assert.deepEqual(
        plain(cronFields.evaluate(parts('1-2-3', '3', '', '*', '1'))),
        {state: 'invalid', invalidParts: ['minute', 'dayOfMonth']}
    );
});

test('a stored expression is acceptable when empty, or five valid parts with or without a sixth', () => {
    assert.equal(cronFields.isValidExpression(''), true);
    assert.equal(cronFields.isValidExpression(' \t '), true);
    assert.equal(cronFields.isValidExpression('0 0 * * * whatever'), true);
    assert.equal(cronFields.isValidExpression('*/5 * * * *'), true);
    assert.equal(cronFields.isValidExpression('0 2 * * mon'), true);
    assert.equal(cronFields.isValidExpression('* 0-5,10-59/5 2-10,15-25 january-june/2 mon-fri'), true);
    assert.equal(cronFields.isValidExpression('1 2 3 4 5 6'), true);
    assert.equal(cronFields.isValidExpression('0 0 * * * *'), true);
    assert.equal(cronFields.isValidExpression('99 * * * *'), true);
    assert.equal(cronFields.isValidExpression('*/5 * * *'), false);
    assert.equal(cronFields.isValidExpression('1 2 3 4 5 6 7'), false);
    assert.equal(cronFields.isValidExpression('a b c d e'), false);
    assert.equal(cronFields.isValidExpression('* * * L *'), false);
});

test('emptying every part of a field that held an expression evaluates as cleared', () => {
    assert.deepEqual(plain(cronFields.evaluateChange(parts('', '', '', '', ''), '*/5 * * * *')), {state: 'cleared'});
    assert.deepEqual(plain(cronFields.evaluateChange(parts('', '', '', '', ''), '0 0 * * * 2026')), {state: 'cleared'});
});

test('emptying every part of a field that held nothing evaluates as empty', () => {
    assert.deepEqual(plain(cronFields.evaluateChange(parts('', '', '', '', ''), '')), {state: 'empty'});
    assert.deepEqual(plain(cronFields.evaluateChange(parts('', ' ', '', '', ''), '  ')), {state: 'empty'});
});

test('a change that leaves parts filled evaluates as the parts do, whatever the field held', () => {
    assert.deepEqual(
        plain(cronFields.evaluateChange(parts('0', '*', '*', '*', '*'), '*/5 * * * *')),
        {state: 'valid', expression: '0 * * * *', value: '0 * * * *'}
    );
    assert.deepEqual(
        plain(cronFields.evaluateChange(parts('0', '', '', '', ''), '')),
        {state: 'invalid', invalidParts: ['hour', 'dayOfMonth', 'month', 'dayOfWeek']}
    );
});

test('a valid change of a field that held a sixth part keeps that part in the value it saves', () => {
    assert.deepEqual(
        plain(cronFields.evaluateChange(parts('0', '4', '*', '*', '*'), '0 3 * * * 2026')),
        {state: 'valid', expression: '0 4 * * *', value: '0 4 * * * 2026'}
    );
});

test('only an empty or a valid evaluation may be saved', () => {
    assert.equal(cronFields.isAcceptable({state: 'empty'}), true);
    assert.equal(cronFields.isAcceptable({state: 'valid', expression: '* * * * *'}), true);
    assert.equal(cronFields.isAcceptable({state: 'invalid', invalidParts: ['hour']}), false);
    assert.equal(cronFields.isAcceptable({state: 'cleared'}), false);
});
