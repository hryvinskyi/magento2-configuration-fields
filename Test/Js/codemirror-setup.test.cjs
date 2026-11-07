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

const ID = 'Hryvinskyi_ConfigurationFields/js/codemirror/';
const setup = load('Hryvinskyi_ConfigurationFields/js/codemirror-setup');

/**
 * @param {string} mode
 * @returns {Array<string>}
 */
const modulesOf = (mode) => plain(setup.modulesFor(mode));

test('a mode opens with its own language module and the addons its editing needs', () => {
    const css = modulesOf('css');

    assert.ok(css.includes(`${ID}mode/css/css`));
    assert.ok(css.includes(`${ID}addon/edit/closebrackets`));
    assert.ok(css.includes(`${ID}addon/fold/brace-fold`));
    assert.equal(css.includes(`${ID}addon/fold/xml-fold`), false);

    assert.ok(modulesOf('xml').includes(`${ID}addon/fold/xml-fold`));
    assert.ok(modulesOf('yaml').includes(`${ID}addon/fold/indent-fold`));
    assert.deepEqual(modulesOf('markdown'), [`${ID}mode/xml/xml`, `${ID}mode/markdown/markdown`]);
});

test('less and scss open like css, and json like javascript', () => {
    assert.deepEqual(modulesOf('text/x-less'), modulesOf('css'));
    assert.deepEqual(modulesOf('text/x-scss'), modulesOf('css'));
    assert.deepEqual(modulesOf('application/json'), modulesOf('javascript'));
});

test('php brings the markup modes it embeds', () => {
    const php = modulesOf('php');

    ['mode/xml/xml', 'mode/htmlmixed/htmlmixed', 'mode/clike/clike', 'mode/php/php'].forEach((name) => {
        assert.ok(php.includes(ID + name), name);
    });
});

test('every module listed is named once per mode', () => {
    ['css', 'javascript', 'htmlmixed', 'xml', 'php', 'sql', 'yaml', 'markdown', 'shell'].forEach((mode) => {
        const modules = modulesOf(mode);

        assert.equal(new Set(modules).size, modules.length, mode);
    });
});

test('a mode the module has no requirements for opens like an html editor', () => {
    assert.deepEqual(modulesOf('text/x-unknown'), modulesOf('htmlmixed'));
});

test('the default theme needs only the editor\'s and the fold gutter\'s stylesheets', () => {
    assert.deepEqual(plain(setup.stylesheetsFor('default')), [
        `${ID}lib/codemirror.css`,
        'Hryvinskyi_ConfigurationFields/css/foldgutter.css'
    ]);
    assert.deepEqual(plain(setup.stylesheetsFor('')), plain(setup.stylesheetsFor('default')));
});

test('another theme adds its own stylesheet between the two', () => {
    assert.deepEqual(plain(setup.stylesheetsFor('monokai')), [
        `${ID}lib/codemirror.css`,
        `${ID}theme/monokai.css`,
        'Hryvinskyi_ConfigurationFields/css/foldgutter.css'
    ]);
});

test('a read-only editor has no cursor', () => {
    assert.equal(setup.readOnlyOption(true), 'nocursor');
    assert.equal(setup.readOnlyOption(false), false);
});

test('the options carry the field\'s settings and the read-only state', () => {
    const options = setup.options({mode: 'css', theme: 'monokai', lineNumbers: false, lineWrapping: true, readOnly: true});

    assert.equal(options.mode, 'css');
    assert.equal(options.theme, 'monokai');
    assert.equal(options.lineNumbers, false);
    assert.equal(options.lineWrapping, true);
    assert.equal(options.readOnly, 'nocursor');
    assert.deepEqual(plain(options.gutters), ['CodeMirror-linenumbers', 'CodeMirror-foldgutter']);
    assert.equal(options.tabSize, 4);
    assert.equal(options.indentWithTabs, false);
});

test('Tab inserts four spaces or indents a selection, Shift-Tab outdents', () => {
    const {extraKeys} = setup.options({mode: 'css', theme: 'default', lineNumbers: true, lineWrapping: true, readOnly: false});
    const calls = [];
    const editor = (selected) => ({
        somethingSelected: () => selected,
        indentSelection: (direction) => calls.push(['indent', direction]),
        replaceSelection: (text, where) => calls.push(['replace', text, where])
    });

    extraKeys.Tab(editor(false));
    extraKeys.Tab(editor(true));
    extraKeys['Shift-Tab'](editor(true));

    assert.deepEqual(calls, [['replace', '    ', 'end'], ['indent', 'add'], ['indent', 'subtract']]);
});
