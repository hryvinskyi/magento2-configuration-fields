/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/*
 * Adds a stylesheet to a page's head at run time, once: a page with several editors asks for the same one per editor.
 */
define([], function () {
    'use strict';

    /**
     * @param {Document} document
     * @param {String} href The stylesheet's address, as the loader resolves it
     * @returns {Boolean} Whether a link was added, false when the page already links this address
     */
    function addOnce(document, href) {
        var known = Array.prototype.slice.call(document.querySelectorAll('link[rel="stylesheet"]')),
            link;

        if (known.some(function (existing) {
            return existing.getAttribute('href') === href;
        })) {
            return false;
        }
        link = document.createElement('link');
        link.setAttribute('type', 'text/css');
        link.setAttribute('rel', 'stylesheet');
        link.setAttribute('href', href);
        document.head.appendChild(link);

        return true;
    }

    return {addOnce: addOnce};
});
