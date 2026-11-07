/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const {load} = require('./amd.cjs');

const cronLocale = load('Hryvinskyi_ConfigurationFields/js/cron-locale');
const AVAILABLE = ['en', 'de', 'fr', 'nb', 'pt_BR', 'pt_PT', 'zh_CN', 'zh_TW'];

test('a locale the list holds as written is taken as it is', () => {
    assert.equal(cronLocale.resolve('pt_BR', AVAILABLE), 'pt_BR');
    assert.equal(cronLocale.resolve('zh_TW', AVAILABLE), 'zh_TW');
});

test('a locale the list holds with a hyphen is taken in the list\'s spelling', () => {
    assert.equal(cronLocale.resolve('de_DE', ['en', 'de-DE', 'de']), 'de-DE');
    assert.equal(cronLocale.resolve('pt-PT', AVAILABLE), 'pt_PT');
});

test('a region the list does not hold falls back to the language', () => {
    assert.equal(cronLocale.resolve('de_AT', AVAILABLE), 'de');
    assert.equal(cronLocale.resolve('nb_NO', AVAILABLE), 'nb');
    assert.equal(cronLocale.resolve('fr_CA', AVAILABLE), 'fr');
});

test('a script between language and region does not hide the region', () => {
    assert.equal(cronLocale.resolve('zh_Hans_CN', AVAILABLE), 'zh_CN');
    assert.equal(cronLocale.resolve('zh_Hant_TW', AVAILABLE), 'zh_TW');
});

test('a language the list does not hold falls back to en', () => {
    assert.equal(cronLocale.resolve('xx_YY', AVAILABLE), 'en');
    assert.equal(cronLocale.resolve('pt_AO', AVAILABLE), 'en');
    assert.equal(cronLocale.resolve('zh_HK', AVAILABLE), 'en');
});

test('an English locale resolves to en', () => {
    assert.equal(cronLocale.resolve('en_US', AVAILABLE), 'en');
    assert.equal(cronLocale.resolve('en_GB', AVAILABLE), 'en');
});

test('a missing, empty or unreadable locale resolves to en', () => {
    [undefined, null, '', 5, {}, '_', '  '].forEach((locale) => {
        assert.equal(cronLocale.resolve(locale, AVAILABLE), 'en');
    });
});

test('an empty list of locales resolves to en', () => {
    assert.equal(cronLocale.resolve('de_DE', []), 'en');
});
