/**
 * Copyright (c) 2025. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

define(['jquery'], function ($) {
    'use strict';
    return function (config) {
        var inputId = config.inputId;
        var value = config.value || '* * * * *';
        var $hiddenInput = $('#' + inputId);
        var $container = $('#cron-editor-ui-' + inputId);
        // HTML structure from test.html, adapted for Magento admin
        var html = `
        <div class="cron-editor-container">
            <div class="cron-editor-summary" id="summary-${inputId}"></div>
            <div class="cron-editor-row">
                <div class="cron-editor-group">
                    <input type="text" class="cron-editor-field" id="minute-${inputId}" value="*" maxlength="50">
                    <label class="cron-editor-label" for="minute-${inputId}">minute</label>
                </div>
                <div class="cron-editor-group">
                    <input type="text" class="cron-editor-field" id="hour-${inputId}" value="*" maxlength="50">
                    <label class="cron-editor-label" for="hour-${inputId}">hour</label>
                </div>
                <div class="cron-editor-group">
                    <input type="text" class="cron-editor-field" id="day-of-month-${inputId}" value="*" maxlength="50">
                    <label class="cron-editor-label" for="day-of-month-${inputId}">day (month)</label>
                </div>
                <div class="cron-editor-group">
                    <input type="text" class="cron-editor-field" id="month-${inputId}" value="*" maxlength="50">
                    <label class="cron-editor-label" for="month-${inputId}">month</label>
                </div>
                <div class="cron-editor-group">
                    <input type="text" class="cron-editor-field" id="day-of-week-${inputId}" value="*" maxlength="50">
                    <label class="cron-editor-label" for="day-of-week-${inputId}">day (week)</label>
                </div>
            </div>
            <div class="cron-editor-help">
                <p>Supported formats:</p>
                <div class="cron-editor-examples">
                    <div><span>*</span> any value</div>
                    <div><span>5</span> specific value</div>
                    <div><span>1-5</span> range</div>
                    <div><span>1,3,5</span> list</div>
                    <div><span>*/2</span> step values</div>
                </div>
            </div>
        </div>`;
        $container.html(html);
        var fieldIds = [
            'minute', 'hour', 'day-of-month', 'month', 'day-of-week'
        ];
        var patternKeys = ['minute', 'hour', 'dayOfMonth', 'month', 'dayOfWeek'];
        var patterns = {
            minute: /^(\*|([0-9]|[1-5][0-9])(-([0-9]|[1-5][0-9]))?(,([0-9]|[1-5][0-9])(-([0-9]|[1-5][0-9]))?)*|\*\/([0-9]|[1-5][0-9]))$/,
            hour: /^(\*|([0-9]|1[0-9]|2[0-3])(-([0-9]|1[0-9]|2[0-3]))?(,([0-9]|1[0-9]|2[0-3])(-([0-9]|1[0-9]|2[0-3]))?)*|\*\/([0-9]|1[0-9]|2[0-3]))$/,
            dayOfMonth: /^(\*|([1-9]|[12][0-9]|3[01])(-([1-9]|[12][0-9]|3[01]))?(,([1-9]|[12][0-9]|3[01])(-([1-9]|[12][0-9]|3[01]))?)*|\*\/([1-9]|[12][0-9]|3[01]))$/,
            month: /^(\*|([1-9]|1[0-2])(-([1-9]|1[0-2]))?(,([1-9]|1[0-2])(-([1-9]|1[0-2]))?)*|\*\/([1-9]|1[0-2]))$/,
            dayOfWeek: /^(\*|[0-6](-[0-6])?(,[0-6](-[0-6])?)*|\*\/[0-6])$/
        };
        var nameMaps = {
            month: {
                '1': $.mage.__('January'),
                '2': $.mage.__('February'),
                '3': $.mage.__('March'),
                '4': $.mage.__('April'),
                '5': $.mage.__('May'),
                '6': $.mage.__('June'),
                '7': $.mage.__('July'),
                '8': $.mage.__('August'),
                '9': $.mage.__('September'),
                '10': $.mage.__('October'),
                '11': $.mage.__('November'),
                '12': $.mage.__('December')
            },
            'day-of-week': {
                '0': $.mage.__('Sunday'),
                '1': $.mage.__('Monday'),
                '2': $.mage.__('Tuesday'),
                '3': $.mage.__('Wednesday'),
                '4': $.mage.__('Thursday'),
                '5': $.mage.__('Friday'),
                '6': $.mage.__('Saturday')
            }
        };
        var summaryParts = {};
        var $fields = fieldIds.map(function(id) {
            return $('#'+id+'-'+inputId);
        });
        // Set initial values from hidden input
        var initial = (value || '* * * * *').split(' ');
        $fields.forEach(function($f, i) {
            $f.val(initial[i] || '*');
        });
        function validateField(val, key) {
            if (!val) return false;
            return patterns[key].test(val);
        }
        function updateCronExpression() {
            var cronValues = $fields.map(function($f) { return $f.val().trim() || '*'; });
            var cronExpr = cronValues.join(' ');
            $hiddenInput.val(cronExpr);
            var hasError = false;
            $fields.forEach(function($f, i) {
                var valid = validateField($f.val().trim(), patternKeys[i]);
                $f.toggleClass('invalid', !valid);
                $('#'+fieldIds[i]+'-'+inputId).siblings('label').toggleClass('invalid', !valid);
                if (!valid) hasError = true;
            });
            if (hasError) {
                $('#summary-'+inputId).html('<span class="cron-editor-error">Invalid cron expression. Please check highlighted fields.</span>');
            } else {
                generateAndDisplaySummary(cronValues);
            }
        }
        function generateAndDisplaySummary(cronValues) {
            var minute = cronValues[0], hour = cronValues[1], dayOfMonth = cronValues[2], month = cronValues[3], dayOfWeek = cronValues[4];
            summaryParts = {
                prefix: 'At ',
                minute: parseExpressionPart(minute, 'minute', 0),
                hour: ' past ' + parseExpressionPart(hour, 'hour', 1),
                dayOfMonth: dayOfMonth !== '*' ? ' on ' + parseExpressionPart(dayOfMonth, 'day-of-month', 2) : '',
                month: month !== '*' ? ' in ' + parseExpressionPart(month, 'month', 3) : '',
                dayOfWeek: ''
            };
            if (dayOfWeek !== '*') {
                var dayCondition = dayOfMonth === '*' ? ' on ' : ' and on ';
                summaryParts.dayOfWeek = dayCondition + parseExpressionPart(dayOfWeek, 'day-of-week', 4);
            }
            displaySummaryWithHighlight();
        }
        function displaySummaryWithHighlight(highlightIndex) {
            var html = summaryParts.prefix;
            var parts = [
                {key: 'minute', index: 0},
                {key: 'hour', index: 1},
                {key: 'dayOfMonth', index: 2},
                {key: 'month', index: 3},
                {key: 'dayOfWeek', index: 4}
            ];
            parts.forEach(function(part) {
                if (summaryParts[part.key]) {
                    if (highlightIndex === part.index) {
                        html += '<span class="cron-editor-highlight">'+summaryParts[part.key]+'</span>';
                    } else {
                        html += summaryParts[part.key];
                    }
                }
            });
            $('#summary-'+inputId).html(html + '.');
        }
        function parseExpressionPart(expr, type, idx) {
            if (expr === '*') return 'every ' + fieldIds[idx].replace('-', ' ');
            if (expr.includes('*/')) {
                var step = expr.split('/')[1];
                return 'every ' + formatOrdinal(step) + ' ' + fieldIds[idx].replace('-', ' ');
            }
            if (expr.includes(',')) {
                var values = expr.split(',');
                return fieldIds[idx].replace('-', ' ') + 's ' + formatList(values, type);
            }
            if (expr.includes('-')) {
                var parts = expr.split('-');
                return fieldIds[idx].replace('-', ' ') + 's ' + formatValue(parts[0], type) + ' through ' + formatValue(parts[1], type);
            }
            return fieldIds[idx].replace('-', ' ') + ' ' + formatValue(expr, type);
        }
        function formatList(values, type) {
            if (values.length === 1) return formatValue(values[0], type);
            if (values.length === 2) return formatValue(values[0], type) + ' and ' + formatValue(values[1], type);
            var last = values.pop();
            return values.map(function(v) { return formatValue(v, type); }).join(', ') + ', and ' + formatValue(last, type);
        }
        function formatValue(val, type) {
            if (nameMaps[type] && nameMaps[type][val]) return nameMaps[type][val];
            return val;
        }
        function formatOrdinal(num) {
            var n = parseInt(num, 10);
            var s = ['th','st','nd','rd'], v = n%100;
            return n + (s[(v-20)%10] || s[v] || s[0]);
        }
        // Event listeners
        $fields.forEach(function($f, idx) {
            $f.on('input', updateCronExpression);
            $f.on('focus', function() { displaySummaryWithHighlight(idx); });
            $f.on('blur', function() { displaySummaryWithHighlight(null); });
            $f.siblings('label').on('click', function() { $f.focus(); });
        });
        // Add custom jQuery validation rule for cron expression
        if ($.validator && $.validator.addMethod) {
            $.validator.addMethod('validate-cron-expression', function(value, element) {
                // Check if any field is invalid
                var isValid = true;
                $fields.forEach(function($f, i) {
                    if (!validateField($f.val().trim(), patternKeys[i])) {
                        isValid = false;
                    }
                });
                return isValid;
            }, $.mage.__('Invalid cron expression. Please check highlighted fields.'));
        }
        // Initialize
        updateCronExpression();
    };
});
