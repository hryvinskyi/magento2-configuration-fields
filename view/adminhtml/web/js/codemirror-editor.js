/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

define([
    'require',
    'jquery',
    'Hryvinskyi_ConfigurationFields/js/codemirror/lib/codemirror',
    'Hryvinskyi_ConfigurationFields/js/codemirror-setup',
    'Hryvinskyi_ConfigurationFields/js/stylesheet-link'
], function (require, $, CodeMirror, setup, stylesheetLink) {
    'use strict';

    var mounted = new WeakSet();

    /**
     * The x-magento-init entry: CodeMirror over the root's textarea, once per root
     *
     * The root `[data-role="hcf-code"]` carries `data-disabled="true"` while the value is inherited ("Use system
     * value" checked) or locked, and the editor is read-only for as long. The editor follows the checkbox (`inheritId`)
     * through its native `change` event; the page's own handler on the checkbox enables the textarea. Once the editor
     * exists it is published as `$(textarea).data('codemirror')` and announced by the textarea's
     * `codemirror:initialized` event, which carries it.
     *
     * @param {Object} config `{inputId, inheritId, mode, theme, lineNumbers, lineWrapping, readOnly}`
     * @param {HTMLElement} element The root
     */
    return function (config, element) {
        var document = element.ownerDocument,
            textarea = element.querySelector('textarea'),
            $textarea = $(textarea),
            mode = config.mode || 'htmlmixed',
            theme = config.theme || 'default',
            lineNumbers = config.lineNumbers !== false,
            lineWrapping = config.lineWrapping !== false,
            inherit = typeof config.inheritId === 'string' && config.inheritId !== ''
                ? document.getElementById(config.inheritId)
                : null,
            disabled = config.readOnly === true || element.getAttribute('data-disabled') === 'true',
            locked = disabled && !(inherit && inherit.checked);

        if (!textarea || mounted.has(element)) {
            return;
        }
        mounted.add(element);

        /**
         * @returns {Boolean}
         */
        function isReadOnly() {
            return locked || Boolean(inherit && inherit.checked);
        }

        /**
         * Mirror the read-only state onto the root and, once it exists, the editor
         *
         * @param {?Object} editor
         */
        function applyState(editor) {
            var readOnly = isReadOnly();

            if (readOnly) {
                element.setAttribute('data-disabled', 'true');
            } else {
                element.removeAttribute('data-disabled');
            }
            if (editor) {
                editor.setOption('readOnly', setup.readOnlyOption(readOnly));
            }
        }

        setup.stylesheetsFor(theme).forEach(function (id) {
            stylesheetLink.addOnce(document, require.toUrl(id));
        });

        // Load required mode modules then initialize editor
        require(setup.modulesFor(mode), function () {
            var editor = CodeMirror.fromTextArea(textarea, setup.options({
                mode: mode,
                theme: theme,
                lineNumbers: lineNumbers,
                lineWrapping: lineWrapping,
                readOnly: isReadOnly()
            }));

            // Set height based on content or default
            editor.setSize(null, Math.max(200, $textarea.height() || 200));

            // Update textarea when editor changes
            editor.on('change', function (cm) {
                $textarea.val(cm.getValue());
            });

            applyState(editor);
            if (inherit) {
                inherit.addEventListener('change', function () {
                    applyState(editor);
                });
            }

            // Store editor instance for external access
            $textarea.data('codemirror', editor);

            // Trigger event for external hooks
            $textarea.trigger('codemirror:initialized', [editor]);
        });
    };
});
