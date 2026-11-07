/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {load} = require('./amd.cjs');
const {el, documentOf} = require('./fake-dom.cjs');

const BUNDLE = path.resolve(__dirname, '../../view/adminhtml/web/js/cronstrue.js');
const PARTS = ['minute', 'hour', 'dayOfMonth', 'month', 'dayOfWeek'];
const WORDING = {
    invalidExpression: 'Invalid cron expression.',
    invalidFields: 'Check the highlighted parts.',
    undescribable: 'This schedule cannot be described in words.'
};

/**
 * The vendored cronstrue bundle as the browser's module loader gets it: an anonymous module it evaluates for its exports
 *
 * @returns {Object} The bundle's exports
 */
function realBundle() {
    const exported = {};
    const bundleModule = {exports: exported};

    new Function('module', 'exports', fs.readFileSync(BUNDLE, 'utf8')).call(globalThis, bundleModule, exported);

    return bundleModule.exports;
}

/**
 * A cron field mounted through the editor's entry over the real bundle
 *
 * @param {string} stored
 * @param {string|undefined} locale Magento's locale code, absent when the config carries none
 * @returns {{summary: Object}}
 */
function mountField(stored, locale) {
    const editor = load('Hryvinskyi_ConfigurationFields/js/cron-editor', {
        jquery: {validator: {addMethod() {}}},
        'jquery/validate': {},
        'Hryvinskyi_ConfigurationFields/js/cronstrue': realBundle()
    });
    const inputs = PARTS.map((part) => el('input', {type: 'text', 'data-role': 'hcf-cron-field', 'data-part': part}));
    const summary = el('div', {'data-role': 'hcf-cron-summary'});
    const hidden = el('input', {type: 'hidden', 'data-role': 'hcf-cron-value', value: stored});
    const root = el('div', {'data-role': 'hcf-cron'}, [summary, ...inputs, hidden]);
    const config = {inputId: 'cron', inheritId: '', disabled: false, value: stored, wording: WORDING};

    documentOf([root]);
    editor(locale === undefined ? config : {...config, locale}, root);

    return {summary};
}

test('the bundle reports the locale codes the mapping chooses from', () => {
    const locales = Object.keys(realBundle().default.locales);

    assert.ok(locales.includes('en'));
    assert.ok(locales.includes('de'));
    assert.ok(locales.includes('pt_BR'));
});

test('the summary is in the locale of the config, through the real bundle', () => {
    assert.equal(mountField('*/5 * * * *', 'de_DE').summary.textContent, 'Alle 5 Minuten');
    assert.equal(mountField('*/5 * * * *', 'fr_FR').summary.textContent, 'Toutes les 5 minutes');
    assert.equal(mountField('*/5 * * * *', 'pt_BR').summary.textContent, 'A cada 5 minutos');
});

test('a locale with a region the bundle lacks is described in its language', () => {
    assert.equal(mountField('*/5 * * * *', 'de_AT').summary.textContent, 'Alle 5 Minuten');
});

test('a locale the bundle lacks, and a config without one, are described in English', () => {
    assert.equal(mountField('*/5 * * * *', 'xx_YY').summary.textContent, 'Every 5 minutes');
    assert.equal(mountField('*/5 * * * *', 'en_US').summary.textContent, 'Every 5 minutes');
    assert.equal(mountField('*/5 * * * *').summary.textContent, 'Every 5 minutes');
});

test('an expression the rule accepts and the bundle cannot describe shows the neutral wording, not an error', () => {
    const {summary} = mountField('61 * * * *', 'de_DE');

    assert.equal(summary.textContent, WORDING.undescribable);
    assert.equal(summary.hasAttribute('data-tone'), false);
});
