jQuery(document).ready(function($) {
    'use strict';

    // Tabs navigation
    $('.nav-tab-wrapper a').on('click', function(e) {
        e.preventDefault();
        var tab_id = $(this).attr('href');

        $('.nav-tab').removeClass('nav-tab-active');
        $('.tab-content').removeClass('active');

        $(this).addClass('nav-tab-active');
        $(tab_id).addClass('active');
    });

    function show_notice(message, type = 'success') {
        var notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
        $('#storelly-admin-notices').html(notice);
        $('html, body').animate({ scrollTop: 0 }, 'slow');
    }

    function handle_ajax_request(action, data, success_message) {
        var button = $(this);
        var original_text = button.text();
        button.text('Processing...').prop('disabled', true);

        $.post(ajaxurl, data, function(response) {
            if (response.success) {
                show_notice(success_message, 'success');
                setTimeout(function() {
                    location.reload();
                }, 1000);
            } else {
                show_notice(response.data.message, 'error');
                button.text(original_text).prop('disabled', false);
            }
        }).fail(function() {
            show_notice('An unexpected error occurred.', 'error');
            button.text(original_text).prop('disabled', false);
        });
    }

    // Automatic Connection
    $('#storelly-auto-connect').on('click', function() {
        handle_ajax_request.call(this, 'spbwc_auto_connect', {
            action: 'spbwc_auto_connect',
            _ajax_nonce: $('#spbwc_connect_nonce').val()
        }, 'Connection successful! The page will now reload.');
    });

    // Manual Connection
    $('#storelly-manual-connect').on('click', function() {
        handle_ajax_request.call(this, 'spbwc_manual_connect', {
            action: 'spbwc_manual_connect',
            _ajax_nonce: $('#spbwc_connect_nonce').val(),
            consumer_key: $('#storelly_consumer_key').val(),
            consumer_secret: $('#storelly_consumer_secret').val()
        }, 'Connection successful! The page will now reload.');
    });

    // Disconnect
    $('#storelly-disconnect').on('click', function() {
        if (confirm('Are you sure you want to disconnect? This will remove your API keys.')) {
            handle_ajax_request.call(this, 'spbwc_disconnect', {
                action: 'spbwc_disconnect',
                _ajax_nonce: $('#spbwc_connect_nonce').val()
            }, 'Successfully disconnected. The page will now reload.');
        }
    });

    // General Settings
    $('#storelly-general-settings-form').on('submit', function(e) {
        e.preventDefault();
        var button = $(this).find('button[type="submit"]');
        var original_text = button.text();
        button.text('Saving...').prop('disabled', true);

        $.post(ajaxurl, {
            action: 'spbwc_save_general_settings',
            _ajax_nonce: $('#spbwc_connect_nonce').val(),
            settings: $(this).serialize()
        }, function(response) {
            if (response.success) {
                show_notice('Settings saved successfully.', 'success');
            } else {
                show_notice(response.data.message, 'error');
            }
            button.text(original_text).prop('disabled', false);
        }).fail(function() {
            show_notice('An unexpected error occurred.', 'error');
            button.text(original_text).prop('disabled', false);
        });
    });
});
