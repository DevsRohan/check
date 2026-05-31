/**
 * Checkout Rescuer - Frontend Cart Tracker
 * Captures phone from checkout and sends heartbeats
 */
(function($) {
  'use strict';

  var CRTracker = {
    saved: false,
    heartbeatTimer: null,

    init: function() {
      this.bindEvents();
      this.startHeartbeat();
    },

    bindEvents: function() {
      var self = this;

      // Capture phone on blur/change
      $(document).on('change blur', '#billing_phone', function() {
        setTimeout(function() { self.capture(); }, 300);
      });

      // Re-capture if name/email changes after phone saved
      $(document).on('change blur', '#billing_first_name, #billing_email', function() {
        if (self.saved) self.capture();
      });

      // Capture on consent check
      $(document).on('change', '#cr-consent-checkbox', function() {
        if ($(this).is(':checked')) self.capture();
      });
    },

    capture: function() {
      var phone = ($('#billing_phone').val() || '').trim();
      if (!phone || phone.length < 10) return;

      // Check consent
      if (crPublic.requireConsent === 'yes') {
        var $cb = $('#cr-consent-checkbox');
        if ($cb.length && !$cb.is(':checked')) return;
      }

      var name = (($('#billing_first_name').val() || '') + ' ' + ($('#billing_last_name').val() || '')).trim();
      var email = ($('#billing_email').val() || '').trim();

      // Add country code if not present
      if (phone.indexOf('+') !== 0 && phone.indexOf('00') !== 0) {
        phone = crPublic.countryCode + phone.replace(/^0+/, '');
      }

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
      }, 30000); // Every 30 seconds

      // Send heartbeat on page leave using synchronous XHR (sendBeacon doesn't support nonce verification)
      $(window).on('beforeunload', function() {
        if (self.saved) {
          var xhr = new XMLHttpRequest();
          xhr.open('POST', crPublic.ajaxUrl, false); // synchronous
          xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
          xhr.send('action=cr_heartbeat&nonce=' + encodeURIComponent(crPublic.nonce));
        }
      });
    }
  };

  $(document).ready(function() {
    CRTracker.init();
  });
})(jQuery);
