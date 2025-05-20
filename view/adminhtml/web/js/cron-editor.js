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
        // Track validation state and current highlighted field
        var isExpressionInvalid = false;
        var currentHighlightIndex = null;
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

            // Update error state
            var wasInvalid = isExpressionInvalid;
            isExpressionInvalid = hasError;

            if (hasError) {
                $('#summary-'+inputId).html('<span class="cron-editor-error">Invalid cron expression. Please check highlighted fields.</span>');
            } else {
                generateAndDisplaySummary(cronValues);
                // If coming from error state, apply the current highlight
                if (wasInvalid && currentHighlightIndex !== null) {
                    displaySummaryWithHighlight(currentHighlightIndex);
                }
            }
        }

        function generateAndDisplaySummary(cronValues) {
            var minute = cronValues[0], hour = cronValues[1], dayOfMonth = cronValues[2], month = cronValues[3], dayOfWeek = cronValues[4];

            // Format time in HH:MM format for specific times
            var timeDisplay = '';
            var isSpecificTime = false;
            var minuteText = '';
            var hourText = '';

            if (minute !== '*' && hour !== '*' && !minute.includes('*') && !hour.includes('*') &&
                !minute.includes(',') && !hour.includes(',') &&
                !minute.includes('-') && !hour.includes('-') &&
                !minute.includes('/') && !hour.includes('/')) {
                // For single specific time
                var displayHour = hour.padStart(2, '0');
                var displayMinute = minute.padStart(2, '0');
                timeDisplay = displayHour + ':' + displayMinute;
                isSpecificTime = true;
            } else {
                // For patterns, generate separate parts for minute and hour
                minuteText = parseExpressionPart(minute, 'minute', 0);
                hourText = parseExpressionPart(hour, 'hour', 1).replace('past ', '');
                timeDisplay = minuteText + ' ' + hourText;
            }

            summaryParts = {
                prefix: 'At ',
                time: timeDisplay,
                isSpecificTime: isSpecificTime, // Flag to indicate if we have HH:MM format
                hourValue: hour.padStart(2, '0'),
                minuteValue: minute.padStart(2, '0'),
                minuteText: minuteText,
                hourText: hourText,
                dayOfMonth: dayOfMonth !== '*' ? ' on day-of-month ' + formatSimpleValue(dayOfMonth) : '',
                month: month !== '*' ? ' in ' + formatSimpleMonth(month) : '',
                dayOfWeek: ''
            };

            if (dayOfWeek !== '*') {
                var dayCondition = dayOfMonth === '*' ? ' on ' : ' and on ';
                summaryParts.dayOfWeek = dayCondition + parseExpressionPart(dayOfWeek, 'day-of-week', 4);
            }

            displaySummaryWithHighlight(currentHighlightIndex);
        }

        function formatSimpleValue(expr) {
            // For simple expressions without ranges or lists
            if (!expr.includes(',') && !expr.includes('-') && !expr.includes('/')) {
                return expr;
            }
            // For complex expressions, use the original parse logic
            return expr;
        }

        function formatSimpleMonth(expr) {
            // For single month value
            if (!expr.includes(',') && !expr.includes('-') && !expr.includes('/')) {
                return nameMaps.month[expr] || expr;
            }
            // For complex expressions
            return parseExpressionPart(expr, 'month', 3).replace('month ', '');
        }

        function displaySummaryWithHighlight(highlightIndex) {
            // If there's an error, maintain the error message and don't modify summary
            if (isExpressionInvalid) {
                return;
            }

            var html = summaryParts.prefix;

            // Special handling for time part with HH:MM format
            if (summaryParts.isSpecificTime) {
                if (highlightIndex === 0) { // Minute field focused
                    html += summaryParts.hourValue + ':' +
                        '<span class="cron-editor-highlight">' + summaryParts.minuteValue + '</span>';
                } else if (highlightIndex === 1) { // Hour field focused
                    html += '<span class="cron-editor-highlight">' + summaryParts.hourValue + '</span>' +
                        ':' + summaryParts.minuteValue;
                } else { // No field focused or other field focused
                    html += summaryParts.time;
                }
            } else {
                // For non-specific time formats, split the time part into minute and hour for separate highlighting
                var minutePart = summaryParts.minuteText;
                var hourPart = summaryParts.hourText;
                if ((minutePart || hourPart) && (highlightIndex === 0 || highlightIndex === 1)) {
                    html += (highlightIndex === 0
                        ? '<span class="cron-editor-highlight">' + minutePart + '</span>'
                        : minutePart
                    ) + ' ' +
                    (highlightIndex === 1
                        ? '<span class="cron-editor-highlight">' + hourPart + '</span>'
                        : hourPart
                    );
                } else {
                    html += summaryParts.time;
                }
            }

            // Handle other parts
            var parts = [
                {key: 'dayOfMonth', index: 2},
                {key: 'month', index: 3},
                {key: 'dayOfWeek', index: 4}
            ];

            parts.forEach(function(part) {
                if (summaryParts[part.key]) {
                    if (highlightIndex === part.index) {
                        html += '<span class="cron-editor-highlight">' + summaryParts[part.key] + '</span>';
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

        function showCronTableSection(idx) {
            // Hide all specific tbodies
            $('#cron-minute-tbody-'+inputId).hide();
            $('#cron-hour-tbody-'+inputId).hide();
            $('#cron-day-tbody-'+inputId).hide();
            $('#cron-month-tbody-'+inputId).hide();
            $('#cron-dow-tbody-'+inputId).hide();
            // Show the relevant tbody
            if(idx === 0) $('#cron-minute-tbody-'+inputId).show();
            else if(idx === 1) $('#cron-hour-tbody-'+inputId).show();
            else if(idx === 2) $('#cron-day-tbody-'+inputId).show();
            else if(idx === 3) $('#cron-month-tbody-'+inputId).show();
            else if(idx === 4) $('#cron-dow-tbody-'+inputId).show();
        }
        function hideAllCronTableSections() {
            $('#cron-minute-tbody-'+inputId).hide();
            $('#cron-hour-tbody-'+inputId).hide();
            $('#cron-day-tbody-'+inputId).hide();
            $('#cron-month-tbody-'+inputId).hide();
            $('#cron-dow-tbody-'+inputId).hide();
        }

        // Event listeners
        $fields.forEach(function($f, idx) {
            $f.on('input', updateCronExpression);
            $f.on('focus', function() {
                // Always highlight the field being focused, even with errors
                // We'll store the current highlight index for use when error is resolved
                currentHighlightIndex = idx;

                // Only update display if there's no error
                if (!isExpressionInvalid) {
                    displaySummaryWithHighlight(idx);
                }
                showCronTableSection(idx);
            });
            $f.on('blur', function() {
                // Clear highlight index when field is blurred
                currentHighlightIndex = null;

                // Only update display if there's no error
                if (!isExpressionInvalid) {
                    displaySummaryWithHighlight(null);
                }
                hideAllCronTableSections();
            });
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

