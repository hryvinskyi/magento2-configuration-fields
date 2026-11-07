/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/**
 * Which of the description locales a Magento locale code is described in, with no DOM and no bundle
 *
 * The locales to choose from are an input: the description bundle reports the codes it holds, so the list has one
 * owner and a bundle with more or fewer locales needs no change here.
 *
 * - A Magento code is a language and a region (`de_DE`), sometimes with a script between them (`zh_Hans_CN`). The
 *   region is the last part when it is two letters; a script is never read as one.
 * - The choice, in order: the language and region as the bundle spells them (`de_DE`, then `de-DE`), the language
 *   alone (`de`), then `en`.
 * - A missing, empty or unreadable code, a language the bundle lacks, and an empty list all give `en`.
 */
define([], function () {
    'use strict';

    var FALLBACK = 'en',
        LANGUAGE = /^[a-z]{2,3}$/i,
        REGION = /^[a-z]{2}$/i;

    /**
     * @param {*} code Magento's locale code, such as `de_DE`
     * @returns {{language: string, region: string}|null} Null when the code is not a readable locale
     */
    function parse(code) {
        var parts;

        if (typeof code !== 'string') {
            return null;
        }
        parts = code.trim().split(/[_-]/);
        if (!LANGUAGE.test(parts[0])) {
            return null;
        }

        return {
            language: parts[0].toLowerCase(),
            region: parts.length > 1 && REGION.test(parts[parts.length - 1])
                ? parts[parts.length - 1].toUpperCase()
                : ''
        };
    }

    /**
     * The codes to try for a parsed locale, most specific first
     *
     * @param {{language: string, region: string}} locale
     * @returns {string[]}
     */
    function candidates(locale) {
        var specific = locale.region === ''
            ? []
            : [locale.language + '_' + locale.region, locale.language + '-' + locale.region];

        return specific.concat([locale.language]);
    }

    /**
     * @param {*} code Magento's locale code, such as `de_DE`
     * @param {string[]} available The locale codes the description bundle holds
     * @returns {string} One of `available`, or `en`
     */
    function resolve(code, available) {
        var locale = parse(code),
            found;

        if (!locale || !Array.isArray(available)) {
            return FALLBACK;
        }
        found = candidates(locale).filter(function (candidate) {
            return available.indexOf(candidate) !== -1;
        })[0];

        return found === undefined ? FALLBACK : found;
    }

    return Object.freeze({resolve: resolve});
});
