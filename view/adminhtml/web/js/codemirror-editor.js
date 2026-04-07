/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

define([
    'jquery',
    'Hryvinskyi_ConfigurationFields/js/codemirror/lib/codemirror'
], function ($, CodeMirror) {
    'use strict';

    /**
     * Mode to required addons/modes mapping
     */
    var foldBase = [
        'Hryvinskyi_ConfigurationFields/js/codemirror/addon/fold/foldcode',
        'Hryvinskyi_ConfigurationFields/js/codemirror/addon/fold/foldgutter',
        'Hryvinskyi_ConfigurationFields/js/codemirror/addon/fold/comment-fold'
    ];

    var braceFold = foldBase.concat([
        'Hryvinskyi_ConfigurationFields/js/codemirror/addon/fold/brace-fold'
    ]);

    var xmlFold = foldBase.concat([
        'Hryvinskyi_ConfigurationFields/js/codemirror/addon/fold/xml-fold'
    ]);

    var allFold = foldBase.concat([
        'Hryvinskyi_ConfigurationFields/js/codemirror/addon/fold/brace-fold',
        'Hryvinskyi_ConfigurationFields/js/codemirror/addon/fold/xml-fold',
        'Hryvinskyi_ConfigurationFields/js/codemirror/addon/fold/indent-fold'
    ]);

    var modeRequirements = {
        'css': [
            'Hryvinskyi_ConfigurationFields/js/codemirror/mode/css/css',
            'Hryvinskyi_ConfigurationFields/js/codemirror/addon/edit/closebrackets',
            'Hryvinskyi_ConfigurationFields/js/codemirror/addon/edit/matchbrackets'
        ].concat(braceFold),
        'text/x-less': [
            'Hryvinskyi_ConfigurationFields/js/codemirror/mode/css/css',
            'Hryvinskyi_ConfigurationFields/js/codemirror/addon/edit/closebrackets',
            'Hryvinskyi_ConfigurationFields/js/codemirror/addon/edit/matchbrackets'
        ].concat(braceFold),
        'text/x-scss': [
            'Hryvinskyi_ConfigurationFields/js/codemirror/mode/css/css',
            'Hryvinskyi_ConfigurationFields/js/codemirror/addon/edit/closebrackets',
            'Hryvinskyi_ConfigurationFields/js/codemirror/addon/edit/matchbrackets'
        ].concat(braceFold),
        'javascript': [
            'Hryvinskyi_ConfigurationFields/js/codemirror/mode/javascript/javascript',
            'Hryvinskyi_ConfigurationFields/js/codemirror/addon/edit/closebrackets',
            'Hryvinskyi_ConfigurationFields/js/codemirror/addon/edit/matchbrackets'
        ].concat(braceFold),
        'application/json': [
            'Hryvinskyi_ConfigurationFields/js/codemirror/mode/javascript/javascript',
            'Hryvinskyi_ConfigurationFields/js/codemirror/addon/edit/closebrackets',
            'Hryvinskyi_ConfigurationFields/js/codemirror/addon/edit/matchbrackets'
        ].concat(braceFold),
        'htmlmixed': [
            'Hryvinskyi_ConfigurationFields/js/codemirror/mode/xml/xml',
            'Hryvinskyi_ConfigurationFields/js/codemirror/mode/javascript/javascript',
            'Hryvinskyi_ConfigurationFields/js/codemirror/mode/css/css',
            'Hryvinskyi_ConfigurationFields/js/codemirror/mode/htmlmixed/htmlmixed',
            'Hryvinskyi_ConfigurationFields/js/codemirror/addon/edit/closetag',
            'Hryvinskyi_ConfigurationFields/js/codemirror/addon/edit/closebrackets',
            'Hryvinskyi_ConfigurationFields/js/codemirror/addon/edit/matchbrackets'
        ].concat(allFold),
        'xml': [
            'Hryvinskyi_ConfigurationFields/js/codemirror/mode/xml/xml',
            'Hryvinskyi_ConfigurationFields/js/codemirror/addon/edit/closetag',
            'Hryvinskyi_ConfigurationFields/js/codemirror/addon/edit/matchbrackets'
        ].concat(xmlFold),
        'php': [
            'Hryvinskyi_ConfigurationFields/js/codemirror/mode/xml/xml',
            'Hryvinskyi_ConfigurationFields/js/codemirror/mode/javascript/javascript',
            'Hryvinskyi_ConfigurationFields/js/codemirror/mode/css/css',
            'Hryvinskyi_ConfigurationFields/js/codemirror/mode/htmlmixed/htmlmixed',
            'Hryvinskyi_ConfigurationFields/js/codemirror/mode/clike/clike',
            'Hryvinskyi_ConfigurationFields/js/codemirror/mode/php/php',
            'Hryvinskyi_ConfigurationFields/js/codemirror/addon/edit/closetag',
            'Hryvinskyi_ConfigurationFields/js/codemirror/addon/edit/closebrackets',
            'Hryvinskyi_ConfigurationFields/js/codemirror/addon/edit/matchbrackets'
        ].concat(allFold),
        'sql': [
            'Hryvinskyi_ConfigurationFields/js/codemirror/mode/sql/sql',
            'Hryvinskyi_ConfigurationFields/js/codemirror/addon/edit/matchbrackets'
        ].concat(braceFold),
        'yaml': [
            'Hryvinskyi_ConfigurationFields/js/codemirror/mode/yaml/yaml'
        ].concat(foldBase.concat([
            'Hryvinskyi_ConfigurationFields/js/codemirror/addon/fold/indent-fold'
        ])),
        'markdown': [
            'Hryvinskyi_ConfigurationFields/js/codemirror/mode/xml/xml',
            'Hryvinskyi_ConfigurationFields/js/codemirror/mode/markdown/markdown'
        ],
        'shell': [
            'Hryvinskyi_ConfigurationFields/js/codemirror/mode/shell/shell'
        ].concat(foldBase.concat([
            'Hryvinskyi_ConfigurationFields/js/codemirror/addon/fold/indent-fold'
        ]))
    };

    /**
     * Load CSS file dynamically
     *
     * @param {String} url
     */
    function loadCss(url) {
        var link = document.createElement('link');

        link.type = 'text/css';
        link.rel = 'stylesheet';
        link.href = require.toUrl(url);
        document.getElementsByTagName('head')[0].appendChild(link);
    }

    /**
     * Get required modules for a mode
     *
     * @param {String} mode
     * @return {Array}
     */
    function getRequiredModules(mode) {
        return modeRequirements[mode] || modeRequirements['htmlmixed'];
    }

    return function (config, element) {
        var $element = $(element),
            $textarea = $element.find('textarea'),
            inputId = config.inputId,
            mode = config.mode || 'htmlmixed',
            theme = config.theme || 'default',
            lineNumbers = config.lineNumbers !== false,
            lineWrapping = config.lineWrapping !== false,
            readOnly = config.readOnly || false,
            editor;

        // Load required CSS
        loadCss('Hryvinskyi_ConfigurationFields/js/codemirror/lib/codemirror.css');

        if (theme && theme !== 'default') {
            loadCss('Hryvinskyi_ConfigurationFields/js/codemirror/theme/' + theme + '.css');
        }

        // Load fold gutter CSS
        loadCss('Hryvinskyi_ConfigurationFields/css/foldgutter.css');

        // Load required mode modules then initialize editor
        require(getRequiredModules(mode), function () {
            editor = CodeMirror.fromTextArea($textarea[0], {
                mode: mode,
                theme: theme,
                lineNumbers: lineNumbers,
                lineWrapping: lineWrapping,
                readOnly: readOnly ? 'nocursor' : false,
                autoCloseBrackets: true,
                autoCloseTags: true,
                matchBrackets: true,
                foldGutter: true,
                gutters: ['CodeMirror-linenumbers', 'CodeMirror-foldgutter'],
                indentUnit: 4,
                tabSize: 4,
                indentWithTabs: false,
                extraKeys: {
                    'Tab': function (cm) {
                        if (cm.somethingSelected()) {
                            cm.indentSelection('add');
                        } else {
                            cm.replaceSelection('    ', 'end');
                        }
                    },
                    'Shift-Tab': function (cm) {
                        cm.indentSelection('subtract');
                    }
                }
            });

            // Set height based on content or default
            editor.setSize(null, Math.max(200, $textarea.height() || 200));

            // Update textarea when editor changes
            editor.on('change', function (cm) {
                $textarea.val(cm.getValue());
            });

            // Handle inherit checkbox state
            var $inheritCheckbox = $('#' + inputId + '_inherit');

            if ($inheritCheckbox.length) {
                $inheritCheckbox.on('change', function () {
                    var isInherited = $(this).is(':checked');

                    editor.setOption('readOnly', isInherited ? 'nocursor' : false);
                    $element.toggleClass('codemirror-disabled', isInherited);
                });

                // Set initial state
                if ($inheritCheckbox.is(':checked')) {
                    editor.setOption('readOnly', 'nocursor');
                    $element.addClass('codemirror-disabled');
                }
            }

            // Store editor instance for external access
            $textarea.data('codemirror', editor);

            // Trigger event for external hooks
            $textarea.trigger('codemirror:initialized', [editor]);
        });
    };
});
