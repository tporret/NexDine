(function ($) {
    'use strict';

    function showResult($el, message, stateClass) {
        $el.removeClass('is-success is-error is-pending').addClass(stateClass).text(message);
    }

    function wireConnectionTest(options) {
        var $button = $(options.buttonSelector);
        var $result = $(options.resultSelector);

        if (!$button.length || !$result.length || typeof nexdineAdmin === 'undefined') {
            return;
        }

        $button.on('click', function () {
            $button.prop('disabled', true);
            showResult($result, options.testingLabel, 'is-pending');

            $.post(nexdineAdmin.ajaxUrl, {
                action: options.action,
                nonce: options.nonce
            })
                .done(function (response) {
                    if (response && response.success && response.data) {
                        showResult(
                            $result,
                            options.successPrefix + ' ' + response.data.status_code + '.',
                            'is-success'
                        );
                        return;
                    }

                    var message = options.errorPrefix;
                    if (response && response.data && response.data.message) {
                        message += ' ' + response.data.message;
                    }
                    showResult($result, message, 'is-error');
                })
                .fail(function (xhr) {
                    var message = options.errorPrefix;
                    if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                        message += ' ' + xhr.responseJSON.data.message;
                    }
                    showResult($result, message, 'is-error');
                })
                .always(function () {
                    $button.prop('disabled', false);
                });
        });
    }

    $(function () {
        if (typeof nexdineAdmin === 'undefined' || !nexdineAdmin.tests || !nexdineAdmin.labels) {
            return;
        }

        wireConnectionTest({
            buttonSelector: '#nexdine-test-vapi-connection',
            resultSelector: '#nexdine-test-vapi-result',
            action: nexdineAdmin.tests.vapi.action,
            nonce: nexdineAdmin.tests.vapi.nonce,
            testingLabel: nexdineAdmin.labels.vapiTesting,
            successPrefix: nexdineAdmin.labels.vapiSuccessPrefix,
            errorPrefix: nexdineAdmin.labels.vapiErrorPrefix
        });

        wireConnectionTest({
            buttonSelector: '#nexdine-test-google-calendar',
            resultSelector: '#nexdine-test-google-calendar-result',
            action: nexdineAdmin.tests.googleCalendar.action,
            nonce: nexdineAdmin.tests.googleCalendar.nonce,
            testingLabel: nexdineAdmin.labels.googleCalendarTesting,
            successPrefix: nexdineAdmin.labels.googleCalendarSuccessPrefix,
            errorPrefix: nexdineAdmin.labels.googleCalendarErrorPrefix
        });
    });
})(jQuery);
