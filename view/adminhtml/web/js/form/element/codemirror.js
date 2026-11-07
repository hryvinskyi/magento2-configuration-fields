/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

/*
 * The code editor as a UI form element: Magento's textarea component, which CodeMirror replaces once the template has
 * rendered it. The mode's modules, the stylesheets and the editor's options come from `js/codemirror-setup`; the
 * component only binds them to the element's observables. Its template draws the root `[data-role="hcf-code"]`, whose
 * `data-disabled="true"` follows the element's `disabled` observable. The stylesheet that colours the editor,
 * `css/codemirror-editor.css`, is linked by the page's layout through the `hryvinskyi_configuration_fields_code_editor`
 * handle.
 */
define([
    'require',
    'jquery',
    'Magento_Ui/js/form/element/textarea',
    'Hryvinskyi_ConfigurationFields/js/codemirror/lib/codemirror',
    'Hryvinskyi_ConfigurationFields/js/codemirror-setup',
    'Hryvinskyi_ConfigurationFields/js/stylesheet-link'
], function (require, $, Textarea, CodeMirror, setup, stylesheetLink) {
    'use strict';

    return Textarea.extend({
        defaults: {
            elementTmpl: 'Hryvinskyi_ConfigurationFields/form/element/codemirror',
            editorConfig: {
                mode: 'htmlmixed',
                theme: 'default',
                lineNumbers: true,
                lineWrapping: true
            }
        },

        /**
         * @inheritdoc
         */
        initObservable: function () {
            this._super();
            this.value.subscribe(this.setEditorValue.bind(this));

            return this;
        },

        /**
         * Open CodeMirror over the rendered textarea, once the mode's modules have loaded
         *
         * Once the editor exists it is published as `$(textarea).data('codemirror')` and announced by the textarea's
         * `codemirror:initialized` event, which carries it. An element destroyed while the modules load opens none.
         *
         * @param {HTMLTextAreaElement} textarea
         */
        initEditor: function (textarea) {
            var self = this,
                config = this.editorConfig || {},
                mode = config.mode || 'htmlmixed',
                theme = config.theme || 'default';

            setup.stylesheetsFor(theme).forEach(function (id) {
                stylesheetLink.addOnce(textarea.ownerDocument, require.toUrl(id));
            });

            require(setup.modulesFor(mode), function () {
                var editor;

                if (self.destroyed) {
                    return;
                }
                editor = CodeMirror.fromTextArea(textarea, setup.options({
                    mode: mode,
                    theme: theme,
                    lineNumbers: config.lineNumbers !== false,
                    lineWrapping: config.lineWrapping !== false,
                    readOnly: self.disabled()
                }));
                self.editor = editor;

                editor.setSize(null, Math.max(200, $(textarea).height() || 200));
                editor.on('change', function (cm) {
                    self.value(cm.getValue());
                });
                self.disabledSubscription = self.disabled.subscribe(function (isDisabled) {
                    editor.setOption('readOnly', setup.readOnlyOption(isDisabled));
                });

                $(textarea).data('codemirror', editor);
                $(textarea).trigger('codemirror:initialized', [editor]);
            });
        },

        /**
         * Set editor value when observable changes
         *
         * @param {String} newValue
         */
        setEditorValue: function (newValue) {
            if (this.editor && newValue !== this.editor.getValue()) {
                this.editor.setValue(newValue || '');
            }
        },

        /**
         * @inheritdoc
         */
        destroy: function (skipUpdate) {
            this.destroyed = true;
            if (this.disabledSubscription) {
                this.disabledSubscription.dispose();
                this.disabledSubscription = null;
            }
            if (this.editor) {
                this.editor.toTextArea();
                this.editor = null;
            }

            return this._super(skipUpdate);
        }
    });
});
