(function ($) {
    'use strict';

    function showResult($el, message, stateClass) {
        $el.removeClass('is-success is-error is-pending').addClass(stateClass).text(message);
    }

    $(function () {
        var $button = $('#nexdine-test-vapi-connection');
        var $result = $('#nexdine-test-vapi-result');

        if (!$button.length || !$result.length || typeof nexdineAdmin === 'undefined') {
            return;
        }

        $button.on('click', function () {
            $button.prop('disabled', true);
            showResult($result, nexdineAdmin.labels.testing, 'is-pending');

            $.post(nexdineAdmin.ajaxUrl, {
                action: nexdineAdmin.action,
                nonce: nexdineAdmin.nonce
            })
                .done(function (response) {
                    if (response && response.success && response.data) {
                        showResult(
                            $result,
                            nexdineAdmin.labels.successPrefix + ' ' + response.data.status_code + '.',
                            'is-success'
                        );
                        return;
                    }

                    var message = nexdineAdmin.labels.errorPrefix;
                    if (response && response.data && response.data.message) {
                        message += ' ' + response.data.message;
                    }
                    showResult($result, message, 'is-error');
                })
                .fail(function (xhr) {
                    var message = nexdineAdmin.labels.errorPrefix;
                    if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                        message += ' ' + xhr.responseJSON.data.message;
                    }
                    showResult($result, message, 'is-error');
                })
                .always(function () {
                    $button.prop('disabled', false);
                });
        });
    });
})(jQuery);
