/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/*
 * What CodeMirror needs to open an editor in a given mode and theme, for the editors the module draws: the modules
 * and stylesheets to load first, and the options the editor is created with. Pure: no DOM, no loader, so both
 * editors, the configuration field's script and the UI form's component, ask the same answers.
 */
define([], function () {
    'use strict';

    var CODEMIRROR = 'Hryvinskyi_ConfigurationFields/js/codemirror/',
        DEFAULT_MODE = 'htmlmixed',
        INDENT = '    ';

    /**
     * @param {Array<String>} names Paths under the module's `js/codemirror/` directory
     * @returns {Array<String>} Module ids
     */
    function ids(names) {
        return names.map(function (name) {
            return CODEMIRROR + name;
        });
    }

    var foldBase = ['addon/fold/foldcode', 'addon/fold/foldgutter', 'addon/fold/comment-fold'],
        braceFold = foldBase.concat(['addon/fold/brace-fold']),
        xmlFold = foldBase.concat(['addon/fold/xml-fold']),
        indentFold = foldBase.concat(['addon/fold/indent-fold']),
        allFold = foldBase.concat(['addon/fold/brace-fold', 'addon/fold/xml-fold', 'addon/fold/indent-fold']),
        brackets = ['addon/edit/closebrackets', 'addon/edit/matchbrackets'],
        cssModules = ['mode/css/css'].concat(brackets, braceFold),
        javascriptModules = ['mode/javascript/javascript'].concat(brackets, braceFold),
        htmlModules = [
            'mode/xml/xml',
            'mode/javascript/javascript',
            'mode/css/css',
            'mode/htmlmixed/htmlmixed',
            'addon/edit/closetag'
        ].concat(brackets, allFold),

        /**
         * The modules and addons each editor mode needs, by CodeMirror mode name
         */
        modeRequirements = Object.freeze({
            'css': ids(cssModules),
            'text/x-less': ids(cssModules),
            'text/x-scss': ids(cssModules),
            'javascript': ids(javascriptModules),
            'application/json': ids(javascriptModules),
            'htmlmixed': ids(htmlModules),
            'xml': ids(['mode/xml/xml', 'addon/edit/closetag', 'addon/edit/matchbrackets'].concat(xmlFold)),
            'php': ids([
                'mode/xml/xml',
                'mode/javascript/javascript',
                'mode/css/css',
                'mode/htmlmixed/htmlmixed',
                'mode/clike/clike',
                'mode/php/php',
                'addon/edit/closetag'
            ].concat(brackets, allFold)),
            'sql': ids(['mode/sql/sql', 'addon/edit/matchbrackets'].concat(braceFold)),
            'yaml': ids(['mode/yaml/yaml'].concat(indentFold)),
            'markdown': ids(['mode/xml/xml', 'mode/markdown/markdown']),
            'shell': ids(['mode/shell/shell'].concat(indentFold))
        });

    /**
     * The module ids to load before an editor opens in this mode
     *
     * A mode the module has no requirements for opens like an HTML editor, which is how earlier versions treated it.
     *
     * @param {String} mode
     * @returns {Array<String>}
     */
    function modulesFor(mode) {
        return modeRequirements[mode] || modeRequirements[DEFAULT_MODE];
    }

    /**
     * The stylesheets an editor needs, as module ids: CodeMirror's own, the theme's unless it is the default one, and
     * the fold gutter's
     *
     * @param {String} theme
     * @returns {Array<String>}
     */
    function stylesheetsFor(theme) {
        var sheets = [CODEMIRROR + 'lib/codemirror.css'];

        if (theme && theme !== 'default') {
            sheets.push(CODEMIRROR + 'theme/' + theme + '.css');
        }
        sheets.push('Hryvinskyi_ConfigurationFields/css/foldgutter.css');

        return sheets;
    }

    /**
     * What CodeMirror's `readOnly` option is for a field that may not be edited: `nocursor` also hides the cursor
     *
     * @param {Boolean} readOnly
     * @returns {String|Boolean}
     */
    function readOnlyOption(readOnly) {
        return readOnly ? 'nocursor' : false;
    }

    /**
     * The options an editor is created with
     *
     * @param {{mode: String, theme: String, lineNumbers: Boolean, lineWrapping: Boolean, readOnly: Boolean}} settings
     * @returns {Object}
     */
    function options(settings) {
        return {
            mode: settings.mode,
            theme: settings.theme,
            lineNumbers: settings.lineNumbers,
            lineWrapping: settings.lineWrapping,
            readOnly: readOnlyOption(settings.readOnly),
            autoCloseBrackets: true,
            autoCloseTags: true,
            matchBrackets: true,
            foldGutter: true,
            gutters: ['CodeMirror-linenumbers', 'CodeMirror-foldgutter'],
            indentUnit: INDENT.length,
            tabSize: INDENT.length,
            indentWithTabs: false,
            extraKeys: {
                'Tab': function (cm) {
                    if (cm.somethingSelected()) {
                        cm.indentSelection('add');
                    } else {
                        cm.replaceSelection(INDENT, 'end');
                    }
                },
                'Shift-Tab': function (cm) {
                    cm.indentSelection('subtract');
                }
            }
        };
    }

    return {
        modulesFor: modulesFor,
        stylesheetsFor: stylesheetsFor,
        readOnlyOption: readOnlyOption,
        options: options
    };
});
