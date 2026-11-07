/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const {load} = require('./amd.cjs');
const {el} = require('./fake-dom.cjs');

const stylesheetLink = load('Hryvinskyi_ConfigurationFields/js/stylesheet-link');

/**
 * @returns {{document: Object, head: Object}}
 */
function page() {
    const head = el('head');

    return {
        head,
        document: {
            head,
            createElement: (tag) => el(tag),
            querySelectorAll: (selector) => head.querySelectorAll(selector)
        }
    };
}

test('a stylesheet the page does not link yet is added to its head', () => {
    const {document, head} = page();

    assert.equal(stylesheetLink.addOnce(document, '/static/a.css'), true);

    const [link] = head.querySelectorAll('link');

    assert.equal(link.getAttribute('rel'), 'stylesheet');
    assert.equal(link.getAttribute('type'), 'text/css');
    assert.equal(link.getAttribute('href'), '/static/a.css');
});

test('asking again for the same address adds nothing, asking for another one adds it', () => {
    const {document, head} = page();

    stylesheetLink.addOnce(document, '/static/a.css');

    assert.equal(stylesheetLink.addOnce(document, '/static/a.css'), false);
    assert.equal(stylesheetLink.addOnce(document, '/static/b.css'), true);
    assert.deepEqual(head.querySelectorAll('link').map((link) => link.getAttribute('href')), [
        '/static/a.css',
        '/static/b.css'
    ]);
});

test('a link the layout already put in the head counts', () => {
    const {document, head} = page();

    head.appendChild(el('link', {rel: 'stylesheet', href: '/static/a.css'}));

    assert.equal(stylesheetLink.addOnce(document, '/static/a.css'), false);
    assert.equal(head.querySelectorAll('link').length, 1);
});
