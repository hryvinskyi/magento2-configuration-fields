/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

'use strict';

/**
 * Loads one of the module's AMD files in Node; a dependency outside the module comes from the stubs the test passes
 */
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const ROOT = path.resolve(__dirname, '../../view/adminhtml/web/js');
const PREFIX = 'Hryvinskyi_ConfigurationFields/js/';

/**
 * @param {string} id e.g. "Hryvinskyi_ConfigurationFields/js/tag-values"
 * @param {Object<string, *>} [stubs] What a dependency outside the module resolves to, keyed by its id
 * @returns {*} What the module's factory returns
 */
function load(id, stubs = {}) {
    if (Object.prototype.hasOwnProperty.call(stubs, id)) {
        return stubs[id];
    }
    if (!id.startsWith(PREFIX)) {
        throw new Error(`No stub for ${id}`);
    }

    const file = path.join(ROOT, id.slice(PREFIX.length) + '.js');
    let exported;

    vm.runInNewContext(fs.readFileSync(file, 'utf8'), {
        define: (deps, factory) => {
            exported = factory(...deps.map((dep) => load(dep, stubs)));
        },
        AbortController,
        WeakSet,
        Map,
        Error
    }, {filename: file});

    return exported;
}

module.exports = {load};
