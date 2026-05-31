/**
 * Checkout Rescuer - Admin JavaScript
 *
 * @package Checkout_Rescuer
 */

(function($) {
    'use strict';

    var CRAdmin = {

        init: function() {
            this.bindDashboard();
            this.bindCarts();
            this.bindSettings();
            this.bindOnboarding();
            this.initChart();
        },

        // === Dashboard ===
        bindDashboard: function() {
            var self = this;
            $('#cr-period-select').on('change', function() {
                self.loadDashboardData($(this).val());
            });
        },

        loadDashboardData: function(period) {
            $.ajax({
                url: crAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'cr_get_dashboard_data',
                    nonce: crAdmin.nonce,
                    period: period
                },
                success: function(response) {
                    if (response.success) {
                        var d = response.data.summary;
                        $('#cr-stat-revenue').text(crAdmin.currency + CRAdmin.formatNumber(d.revenue_recovered));
                        $('#cr-stat-rate').text(d.recovery_rate + '%');
                        $('#cr-stat-recovered').text(d.recovered);
                        $('#cr-stat-messages').text(d.messages_sent);
                    }
                }
            });
        },


        // === Carts Page ===
        bindCarts: function() {
            var self = this;

            $(document).on('click', '.cr-resend-btn', function() {
                var cartId = $(this).data('cart-id');
                if (confirm(crAdmin.strings.confirmResend)) {
                    self.resendMessage(cartId, $(this));
                }
            });

            $(document).on('click', '.cr-delete-btn', function() {
                var cartId = $(this).data('cart-id');
                if (confirm(crAdmin.strings.confirmDelete)) {
                    self.deleteCart(cartId, $(this));
                }
            });
        },

        resendMessage: function(cartId, $btn) {
            $btn.prop('disabled', true);
            $.ajax({
                url: crAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'cr_resend_message',
                    nonce: crAdmin.nonce,
                    cart_id: cartId
                },
                success: function(response) {
                    if (response.success) {
                        CRAdmin.showToast(response.data.message, 'success');
                    } else {
                        CRAdmin.showToast(response.data.message, 'error');
                    }
                },
                error: function() {
                    CRAdmin.showToast(crAdmin.strings.error, 'error');
                },
                complete: function() {
                    $btn.prop('disabled', false);
                }
            });
        },

        deleteCart: function(cartId, $btn) {
            var $row = $btn.closest('tr');
            $.ajax({
                url: crAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'cr_delete_cart',
                    nonce: crAdmin.nonce,
                    cart_id: cartId
                },
                success: function(response) {
                    if (response.success) {
                        $row.fadeOut(300, function() { $(this).remove(); });
                        CRAdmin.showToast('Cart deleted.', 'success');
                    } else {
                        CRAdmin.showToast(response.data.message, 'error');
                    }
                }
            });
        },


        // === Settings Page ===
        bindSettings: function() {
            var self = this;

            $('#cr-save-settings').on('click', function() {
                self.saveSettings($(this));
            });

            $('#cr-send-test').on('click', function() {
                self.sendTestMessage();
            });
        },

        saveSettings: function($btn) {
            var settings = {};
            var $app = $('#cr-settings');

            $app.find('input[name], select[name], textarea[name]').each(function() {
                var $el = $(this);
                var name = $el.attr('name');
                if ($el.attr('type') === 'checkbox') {
                    settings[name] = $el.is(':checked') ? 'yes' : 'no';
                } else {
                    settings[name] = $el.val();
                }
            });

            $btn.text(crAdmin.strings.saving).prop('disabled', true);

            $.ajax({
                url: crAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'cr_save_settings',
                    nonce: crAdmin.nonce,
                    settings: settings
                },
                success: function(response) {
                    if (response.success) {
                        CRAdmin.showToast(crAdmin.strings.saved, 'success');
                    } else {
                        CRAdmin.showToast(response.data.message, 'error');
                    }
                },
                error: function() {
                    CRAdmin.showToast(crAdmin.strings.error, 'error');
                },
                complete: function() {
                    $btn.text('Save Settings').prop('disabled', false);
                }
            });
        },

        sendTestMessage: function() {
            var phone = $('#cr-test-phone').val();
            var channel = $('#cr-test-channel').val();
            var $result = $('#cr-test-result');

            if (!phone) {
                $result.show().removeClass('cr-result-success').addClass('cr-result-error').text('Please enter a phone number.');
                return;
            }

            $result.show().removeClass('cr-result-success cr-result-error').text(crAdmin.strings.testing);

            $.ajax({
                url: crAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'cr_test_connection',
                    nonce: crAdmin.nonce,
                    phone: phone,
                    channel: channel
                },
                success: function(response) {
                    if (response.success) {
                        $result.removeClass('cr-result-error').addClass('cr-result-success').text(crAdmin.strings.testSuccess);
                    } else {
                        $result.removeClass('cr-result-success').addClass('cr-result-error').text(crAdmin.strings.testFailed + response.data.message);
                    }
                },
                error: function() {
                    $result.removeClass('cr-result-success').addClass('cr-result-error').text(crAdmin.strings.error);
                }
            });
        },


        // === Onboarding ===
        bindOnboarding: function() {
            var self = this;
            var currentStep = 1;

            $(document).on('click', '.cr-next-step', function() {
                currentStep++;
                self.goToStep(currentStep);
            });

            $(document).on('click', '.cr-prev-step', function() {
                currentStep--;
                self.goToStep(currentStep);
            });

            $('#cr-ob-test-btn').on('click', function() {
                self.onboardingTest();
            });

            $('#cr-ob-complete').on('click', function() {
                self.completeOnboarding();
            });
        },

        goToStep: function(step) {
            $('.cr-onboarding-step').removeClass('cr-step-active');
            $('[data-step="' + step + '"]').filter('.cr-onboarding-step').addClass('cr-step-active');

            // Update progress dots
            $('.cr-progress-dot').each(function() {
                var dotStep = $(this).closest('.cr-progress-step').data('step');
                $(this).removeClass('cr-progress-dot-active cr-progress-dot-done');
                if (dotStep === step) {
                    $(this).addClass('cr-progress-dot-active');
                } else if (dotStep < step) {
                    $(this).addClass('cr-progress-dot-done');
                }
            });
        },

        onboardingTest: function() {
            var phone = $('#cr-ob-test-phone').val();
            var $result = $('#cr-ob-test-result');

            if (!phone) {
                $result.show().text('Enter your phone number first.').addClass('cr-result-error');
                return;
            }

            // First save credentials temporarily
            var sid = $('#cr-ob-sid').val();
            var token = $('#cr-ob-token').val();

            if (!sid || !token) {
                $result.show().text('Please enter Twilio credentials in Step 2.').addClass('cr-result-error');
                return;
            }

            $result.show().removeClass('cr-result-success cr-result-error').text('Saving credentials and sending test...');

            // Save first, then test
            $.ajax({
                url: crAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'cr_complete_onboarding',
                    nonce: crAdmin.nonce,
                    twilio_sid: sid,
                    twilio_token: token,
                    twilio_phone: $('#cr-ob-phone').val(),
                    whatsapp_number: $('#cr-ob-whatsapp').val()
                },
                success: function() {
                    // Now send test
                    $.ajax({
                        url: crAdmin.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'cr_test_connection',
                            nonce: crAdmin.nonce,
                            phone: phone,
                            channel: 'whatsapp'
                        },
                        success: function(response) {
                            if (response.success) {
                                $result.removeClass('cr-result-error').addClass('cr-result-success').text('Test message sent! Check your phone.');
                            } else {
                                $result.removeClass('cr-result-success').addClass('cr-result-error').text('Failed: ' + response.data.message);
                            }
                        }
                    });
                }
            });
        },

        completeOnboarding: function() {
            var sid = $('#cr-ob-sid').val();
            var token = $('#cr-ob-token').val();
            var phone = $('#cr-ob-phone').val();
            var whatsapp = $('#cr-ob-whatsapp').val();

            if (!sid || !token) {
                CRAdmin.showToast('Twilio SID and Token are required.', 'error');
                return;
            }

            $.ajax({
                url: crAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'cr_complete_onboarding',
                    nonce: crAdmin.nonce,
                    twilio_sid: sid,
                    twilio_token: token,
                    twilio_phone: phone,
                    whatsapp_number: whatsapp
                },
                success: function(response) {
                    if (response.success) {
                        CRAdmin.showToast('Setup complete! Redirecting...', 'success');
                        setTimeout(function() {
                            window.location.href = response.data.redirect;
                        }, 1500);
                    } else {
                        CRAdmin.showToast(response.data.message, 'error');
                    }
                }
            });
        },


        // === Chart ===
        initChart: function() {
            var $canvas = $('#cr-recovery-chart');
            if (!$canvas.length || typeof crChartData === 'undefined') {
                return;
            }

            var ctx = $canvas[0].getContext('2d');
            var data = crChartData;

            // Simple canvas chart (no external dependency)
            this.drawChart(ctx, $canvas[0], data);
        },

        drawChart: function(ctx, canvas, data) {
            if (!data.labels || !data.labels.length) {
                ctx.font = '14px Inter, sans-serif';
                ctx.fillStyle = '#64748b';
                ctx.textAlign = 'center';
                ctx.fillText('No data available yet. Charts will appear once carts are tracked.', canvas.width / 2, canvas.height / 2);
                return;
            }

            var width = canvas.width = canvas.offsetWidth * 2;
            var height = canvas.height = 560;
            ctx.scale(1, 1);

            var padding = { top: 40, right: 40, bottom: 60, left: 70 };
            var chartW = width - padding.left - padding.right;
            var chartH = height - padding.top - padding.bottom;

            var maxVal = Math.max.apply(null, data.revenue.concat([100]));
            var stepX = chartW / (data.labels.length - 1 || 1);

            // Grid lines
            ctx.strokeStyle = '#f1f5f9';
            ctx.lineWidth = 1;
            for (var i = 0; i <= 5; i++) {
                var y = padding.top + (chartH / 5) * i;
                ctx.beginPath();
                ctx.moveTo(padding.left, y);
                ctx.lineTo(width - padding.right, y);
                ctx.stroke();
            }

            // Revenue area
            ctx.beginPath();
            ctx.moveTo(padding.left, padding.top + chartH);
            for (var j = 0; j < data.revenue.length; j++) {
                var x = padding.left + stepX * j;
                var yVal = padding.top + chartH - (data.revenue[j] / maxVal * chartH);
                if (j === 0) ctx.lineTo(x, yVal);
                else ctx.lineTo(x, yVal);
            }
            ctx.lineTo(padding.left + stepX * (data.revenue.length - 1), padding.top + chartH);
            ctx.closePath();

            var gradient = ctx.createLinearGradient(0, padding.top, 0, padding.top + chartH);
            gradient.addColorStop(0, 'rgba(16, 185, 129, 0.15)');
            gradient.addColorStop(1, 'rgba(16, 185, 129, 0.01)');
            ctx.fillStyle = gradient;
            ctx.fill();

            // Revenue line
            ctx.beginPath();
            ctx.strokeStyle = '#10b981';
            ctx.lineWidth = 3;
            for (var k = 0; k < data.revenue.length; k++) {
                var xk = padding.left + stepX * k;
                var yk = padding.top + chartH - (data.revenue[k] / maxVal * chartH);
                if (k === 0) ctx.moveTo(xk, yk);
                else ctx.lineTo(xk, yk);
            }
            ctx.stroke();

            // X-axis labels
            ctx.font = '20px Inter, sans-serif';
            ctx.fillStyle = '#64748b';
            ctx.textAlign = 'center';
            var labelInterval = Math.ceil(data.labels.length / 10);
            for (var l = 0; l < data.labels.length; l += labelInterval) {
                var xl = padding.left + stepX * l;
                ctx.fillText(data.labels[l], xl, height - 20);
            }
        },


        // === Utilities ===
        formatNumber: function(num) {
            return parseFloat(num).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },

        showToast: function(message, type) {
            var $toast = $('<div class="cr-toast cr-toast-' + type + '">' + message + '</div>');
            $('body').append($toast);
            setTimeout(function() {
                $toast.fadeOut(300, function() { $(this).remove(); });
            }, 3500);
        }
    };

    $(document).ready(function() {
        CRAdmin.init();
    });

})(jQuery);
