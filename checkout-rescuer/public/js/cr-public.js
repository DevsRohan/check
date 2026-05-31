/**
 * Checkout Rescuer - Frontend Cart Tracker
 * Supports BOTH classic WooCommerce checkout AND WooCommerce Blocks checkout
 */
(function($) {
  'use strict';

  var CRTracker = {
    saved: false,
    heartbeatTimer: null,
    consentInjected: false,

    init: function() {
      this.injectConsentIfNeeded();
      this.bindEvents();
      this.startHeartbeat();
    },

    /**
     * For Blocks checkout: inject consent checkbox if WC Checkout Fields API didn't add it
     */
    injectConsentIfNeeded: function() {
      // If classic consent field already exists, skip
      if ($('#cr-consent-checkbox').length) return;

      // If consent not required, skip
      if (crPublic.requireConsent !== 'yes') return;

      // Find the phone field and inject consent after it
      var self = this;
      var attempts = 0;
      var tryInject = function() {
        attempts++;
        // Look for phone field in blocks checkout
        var $phoneField = $('[id*="phone"]').closest('.wc-block-components-text-input, .wc-block-components-address-form__phone');

        // If not found, try broader selectors
        if (!$phoneField.length) {
          $phoneField = $('input[autocomplete="tel"]').closest('.wc-block-components-text-input');
        }

        // If still not found and #cr-consent-checkbox doesn't exist, try after billing form
        if (!$phoneField.length) {
          $phoneField = $('.wc-block-checkout__contact-fields, .wc-block-components-address-form');
        }

        if ($phoneField.length && !$('#cr-consent-checkbox').length) {
          var consentHtml = '<div class="cr-consent-field" id="cr-consent-wrapper" style="margin:16px 0;padding:14px 18px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;">' +
            '<label style="display:flex;align-items:flex-start;gap:12px;cursor:pointer;font-size:14px;line-height:1.5;color:#374151;">' +
              '<input type="checkbox" id="cr-consent-checkbox" name="cr_consent" value="1" style="margin-top:3px;width:18px;height:18px;accent-color:#10b981;">' +
              '<span>' + (crPublic.consentText || 'I agree to receive order updates via WhatsApp') + '</span>' +
            '</label>' +
          '</div>';

          $phoneField.last().after(consentHtml);
          self.consentInjected = true;
        } else if (attempts < 20 && !$('#cr-consent-checkbox').length) {
          // Blocks checkout loads async, retry
          setTimeout(tryInject, 500);
        }
      };

      // Start trying after a short delay (blocks checkout renders async)
      setTimeout(tryInject, 1000);
    },

    bindEvents: function() {
      var self = this;

      // Classic checkout: billing_phone
      $(document).on('change blur', '#billing_phone', function() {
        setTimeout(function() { self.capture(); }, 300);
      });

      // Blocks checkout: phone input (various selectors)
      $(document).on('change blur', 'input[id*="phone"], input[autocomplete="tel"]', function() {
        setTimeout(function() { self.capture(); }, 300);
      });

      // Name/email changes
      $(document).on('change blur', '#billing_first_name, #billing_email, input[id*="first-name"], input[id*="email"], input[autocomplete="given-name"], input[autocomplete="email"]', function() {
        if (self.saved) self.capture();
      });

      // Consent checkbox change
      $(document).on('change', '#cr-consent-checkbox', function() {
        if ($(this).is(':checked')) self.capture();
      });

      // Also listen for WC Blocks checkout field
      $(document).on('change', '[id*="checkout-rescuer"]', function() {
        self.capture();
      });
    },

    capture: function() {
      // Get phone - try multiple selectors (classic + blocks)
      var phone = ($('#billing_phone').val() || $('input[id*="phone"]').val() || $('input[autocomplete="tel"]').val() || '').trim();

      if (!phone || phone.length < 10) return;

      // Check consent
      if (crPublic.requireConsent === 'yes') {
        var $cb = $('#cr-consent-checkbox');
        // Also check blocks checkout field
        var $blocksCb = $('[id*="checkout-rescuer--consent"] input[type="checkbox"]');
        var hasConsent = ($cb.length && $cb.is(':checked')) || ($blocksCb.length && $blocksCb.is(':checked'));

        if (!hasConsent && crPublic.requireConsent === 'yes') return;
      }

      // Get name - try multiple selectors
      var firstName = ($('#billing_first_name').val() || $('input[id*="first-name"]').val() || $('input[autocomplete="given-name"]').val() || '').trim();
      var lastName = ($('#billing_last_name').val() || $('input[id*="last-name"]').val() || $('input[autocomplete="family-name"]').val() || '').trim();
      var name = (firstName + ' ' + lastName).trim();

      // Get email
      var email = ($('#billing_email').val() || $('input[id*="email"]').val() || $('input[autocomplete="email"]').val() || '').trim();

      // Add country code if not present
      if (phone.indexOf('+') !== 0 && phone.indexOf('00') !== 0) {
        phone = crPublic.countryCode + phone.replace(/^0+/, '');
      }

      // Send via AJAX (works for both classic and blocks)
      $.ajax({
        url: crPublic.ajaxUrl,
        type: 'POST',
        data: {
          action: 'cr_track_cart',
          nonce: crPublic.nonce,
          phone: phone,
          name: name,
          email: email,
          consent: 1
        },
        success: function(r) {
          if (r.success) CRTracker.saved = true;
        }
      });
    },

    startHeartbeat: function() {
      var self = this;
      this.heartbeatTimer = setInterval(function() {
        if (!self.saved) return;
        $.ajax({
          url: crPublic.ajaxUrl,
          type: 'POST',
          data: {
            action: 'cr_heartbeat',
            nonce: crPublic.nonce
          }
        });
      }, 30000);

      // Send heartbeat on page leave
      $(window).on('beforeunload', function() {
        if (self.saved) {
          var xhr = new XMLHttpRequest();
          xhr.open('POST', crPublic.ajaxUrl, false);
          xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
          xhr.send('action=cr_heartbeat&nonce=' + encodeURIComponent(crPublic.nonce));
        }
      });
    }
  };

  // Init when ready (with slight delay for blocks checkout to render)
  $(document).ready(function() {
    setTimeout(function() {
      CRTracker.init();
    }, 500);
  });

})(jQuery);
