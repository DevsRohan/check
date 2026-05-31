/**
 * Checkout Rescuer - Public JavaScript
 * Handles phone capture and cart activity heartbeat.
 *
 * @package Checkout_Rescuer
 */

(function($) {
    'use strict';

    var CR_Public = {
        heartbeatTimer: null,
        phoneSaved: false,

        init: function() {
            if (!crPublic.isCheckout) {
                return;
            }

            this.bindEvents();
            this.startHeartbeat();
        },

        bindEvents: function() {
            var self = this;

            // Listen for phone field changes (WooCommerce billing phone)
            $(document).on('change blur', '#billing_phone', function() {
                self.capturePhone();
            });

            // Also listen on focus out with a delay
            $(document).on('focusout', '#billing_phone', function() {
                setTimeout(function() {
                    self.capturePhone();
                }, 500);
            });

            // Listen for name/email changes for better tracking
            $(document).on('change blur', '#billing_first_name, #billing_email', function() {
                if (self.phoneSaved) {
                    self.capturePhone();
                }
            });
        },

        capturePhone: function() {
            var phone = $('#billing_phone').val();
            if (!phone || phone.length < 10) {
                return;
            }

            var name  = ($('#billing_first_name').val() || '') + ' ' + ($('#billing_last_name').val() || '');
            var email = $('#billing_email').val() || '';

            var consent = 1;
            if (crPublic.requireConsent === 'yes') {
                var $checkbox = $('#cr-consent-checkbox');
                if ($checkbox.length && !$checkbox.is(':checked')) {
                    consent = 0;
                    return; // Don't save without consent
                }
            }

            $.ajax({
                url: crPublic.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'cr_save_phone',
                    nonce: crPublic.nonce,
                    phone: phone.trim(),
                    name: name.trim(),
                    email: email.trim(),
                    consent: consent
                },
                success: function(response) {
                    if (response.success) {
                        CR_Public.phoneSaved = true;
                    }
                }
            });
        },

        startHeartbeat: function() {
            var self = this;

            this.heartbeatTimer = setInterval(function() {
                if (!self.phoneSaved) {
                    return;
                }
                self.sendHeartbeat();
            }, crPublic.heartbeatInterval);

            // Also send on page visibility change
            $(document).on('visibilitychange', function() {
                if (document.visibilityState === 'hidden' && self.phoneSaved) {
                    // Page is being left - send final heartbeat
                    self.sendHeartbeat();
                }
            });
        },

        sendHeartbeat: function() {
            $.ajax({
                url: crPublic.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'cr_heartbeat',
                    nonce: crPublic.nonce
                }
            });
        }
    };

    $(document).ready(function() {
        CR_Public.init();
    });

})(jQuery);
