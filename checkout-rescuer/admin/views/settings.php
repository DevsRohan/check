<?php
/**
 * Settings view.
 *
 * @package Checkout_Rescuer
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$settings   = CR_Admin_Settings::get_all_settings();
$connection = CR_Admin_Settings::get_connection_status();
$variables  = CR_Admin_Settings::get_template_variables();
?>
<div class="cr-app" id="cr-settings">
    <div class="cr-header">
        <div class="cr-header-left">
            <h1 class="cr-title"><?php esc_html_e( 'Settings', 'checkout-rescuer' ); ?></h1>
            <p class="cr-subtitle"><?php esc_html_e( 'Configure your cart recovery settings', 'checkout-rescuer' ); ?></p>
        </div>
        <div class="cr-header-right">
            <button id="cr-save-settings" class="cr-btn cr-btn-primary"><?php esc_html_e( 'Save Settings', 'checkout-rescuer' ); ?></button>
        </div>
    </div>

    <!-- Connection Status -->
    <div class="cr-card cr-connection-card">
        <div class="cr-card-header">
            <h3><?php esc_html_e( 'Connection Status', 'checkout-rescuer' ); ?></h3>
        </div>
        <div class="cr-card-body">
            <div class="cr-connection-grid">
                <div class="cr-connection-item">
                    <span class="cr-connection-dot <?php echo $connection['twilio'] ? 'cr-dot-success' : 'cr-dot-error'; ?>"></span>
                    <span><?php esc_html_e( 'Twilio API', 'checkout-rescuer' ); ?></span>
                    <span class="cr-connection-status"><?php echo $connection['twilio'] ? esc_html__( 'Connected', 'checkout-rescuer' ) : esc_html__( 'Not Connected', 'checkout-rescuer' ); ?></span>
                </div>
                <div class="cr-connection-item">
                    <span class="cr-connection-dot <?php echo $connection['whatsapp'] ? 'cr-dot-success' : 'cr-dot-error'; ?>"></span>
                    <span><?php esc_html_e( 'WhatsApp', 'checkout-rescuer' ); ?></span>
                    <span class="cr-connection-status"><?php echo $connection['whatsapp'] ? esc_html__( 'Configured', 'checkout-rescuer' ) : esc_html__( 'Not Set', 'checkout-rescuer' ); ?></span>
                </div>
                <div class="cr-connection-item">
                    <span class="cr-connection-dot <?php echo $connection['sms'] ? 'cr-dot-success' : 'cr-dot-error'; ?>"></span>
                    <span><?php esc_html_e( 'SMS', 'checkout-rescuer' ); ?></span>
                    <span class="cr-connection-status"><?php echo $connection['sms'] ? esc_html__( 'Configured', 'checkout-rescuer' ) : esc_html__( 'Not Set', 'checkout-rescuer' ); ?></span>
                </div>
            </div>
        </div>
    </div>

    <div class="cr-settings-grid">
        <!-- General Settings -->
        <div class="cr-card">
            <div class="cr-card-header">
                <h3><?php esc_html_e( 'General', 'checkout-rescuer' ); ?></h3>
            </div>
            <div class="cr-card-body">
                <div class="cr-field">
                    <label class="cr-label"><?php esc_html_e( 'Enable Cart Recovery', 'checkout-rescuer' ); ?></label>
                    <label class="cr-toggle">
                        <input type="checkbox" name="enabled" value="yes" <?php checked( isset( $settings['enabled'] ) ? $settings['enabled'] : 'yes', 'yes' ); ?>>
                        <span class="cr-toggle-slider"></span>
                    </label>
                </div>
                <div class="cr-field">
                    <label class="cr-label"><?php esc_html_e( 'Abandonment Timeout (minutes)', 'checkout-rescuer' ); ?></label>
                    <input type="number" name="abandonment_timeout" value="<?php echo esc_attr( isset( $settings['abandonment_timeout'] ) ? $settings['abandonment_timeout'] : 30 ); ?>" class="cr-input" min="5" max="1440">
                    <p class="cr-help"><?php esc_html_e( 'Time after last activity before a cart is considered abandoned.', 'checkout-rescuer' ); ?></p>
                </div>
                <div class="cr-field">
                    <label class="cr-label"><?php esc_html_e( 'Default Country Code', 'checkout-rescuer' ); ?></label>
                    <input type="text" name="country_code" value="<?php echo esc_attr( isset( $settings['country_code'] ) ? $settings['country_code'] : '+1' ); ?>" class="cr-input cr-input-sm" placeholder="+1">
                </div>
                <div class="cr-field">
                    <label class="cr-label"><?php esc_html_e( 'Data Retention (days)', 'checkout-rescuer' ); ?></label>
                    <input type="number" name="data_retention_days" value="<?php echo esc_attr( isset( $settings['data_retention_days'] ) ? $settings['data_retention_days'] : 90 ); ?>" class="cr-input" min="7" max="365">
                </div>
            </div>
        </div>

        <!-- Messaging Settings -->
        <div class="cr-card">
            <div class="cr-card-header">
                <h3><?php esc_html_e( 'Messaging', 'checkout-rescuer' ); ?></h3>
            </div>
            <div class="cr-card-body">
                <div class="cr-field">
                    <label class="cr-label"><?php esc_html_e( 'Primary Channel', 'checkout-rescuer' ); ?></label>
                    <select name="primary_channel" class="cr-select">
                        <option value="whatsapp" <?php selected( isset( $settings['primary_channel'] ) ? $settings['primary_channel'] : 'whatsapp', 'whatsapp' ); ?>><?php esc_html_e( 'WhatsApp', 'checkout-rescuer' ); ?></option>
                        <option value="sms" <?php selected( isset( $settings['primary_channel'] ) ? $settings['primary_channel'] : 'whatsapp', 'sms' ); ?>><?php esc_html_e( 'SMS', 'checkout-rescuer' ); ?></option>
                    </select>
                </div>
                <div class="cr-field">
                    <label class="cr-label"><?php esc_html_e( 'Fallback to SMS', 'checkout-rescuer' ); ?></label>
                    <label class="cr-toggle">
                        <input type="checkbox" name="fallback_to_sms" value="yes" <?php checked( isset( $settings['fallback_to_sms'] ) ? $settings['fallback_to_sms'] : 'yes', 'yes' ); ?>>
                        <span class="cr-toggle-slider"></span>
                    </label>
                    <p class="cr-help"><?php esc_html_e( 'Send SMS if WhatsApp delivery fails.', 'checkout-rescuer' ); ?></p>
                </div>
                <div class="cr-field">
                    <label class="cr-label"><?php esc_html_e( 'Max Messages per Cart', 'checkout-rescuer' ); ?></label>
                    <select name="max_messages" class="cr-select">
                        <option value="1" <?php selected( isset( $settings['max_messages'] ) ? $settings['max_messages'] : 2, 1 ); ?>>1</option>
                        <option value="2" <?php selected( isset( $settings['max_messages'] ) ? $settings['max_messages'] : 2, 2 ); ?>>2</option>
                    </select>
                </div>
                <div class="cr-field">
                    <label class="cr-label"><?php esc_html_e( 'First Message Delay (minutes)', 'checkout-rescuer' ); ?></label>
                    <input type="number" name="message_1_delay" value="<?php echo esc_attr( isset( $settings['message_1_delay'] ) ? $settings['message_1_delay'] : 30 ); ?>" class="cr-input" min="5">
                </div>
                <div class="cr-field">
                    <label class="cr-label"><?php esc_html_e( 'Second Message Delay (minutes)', 'checkout-rescuer' ); ?></label>
                    <input type="number" name="message_2_delay" value="<?php echo esc_attr( isset( $settings['message_2_delay'] ) ? $settings['message_2_delay'] : 1440 ); ?>" class="cr-input" min="60">
                    <p class="cr-help"><?php esc_html_e( '1440 = 24 hours', 'checkout-rescuer' ); ?></p>
                </div>
            </div>
        </div>

        <!-- Discount Settings -->
        <div class="cr-card">
            <div class="cr-card-header">
                <h3><?php esc_html_e( 'Discount', 'checkout-rescuer' ); ?></h3>
            </div>
            <div class="cr-card-body">
                <div class="cr-field">
                    <label class="cr-label"><?php esc_html_e( 'Enable Auto-Discount', 'checkout-rescuer' ); ?></label>
                    <label class="cr-toggle">
                        <input type="checkbox" name="enable_discount" value="yes" <?php checked( isset( $settings['enable_discount'] ) ? $settings['enable_discount'] : 'yes', 'yes' ); ?>>
                        <span class="cr-toggle-slider"></span>
                    </label>
                </div>
                <div class="cr-field">
                    <label class="cr-label"><?php esc_html_e( 'Discount Type', 'checkout-rescuer' ); ?></label>
                    <select name="discount_type" class="cr-select">
                        <option value="percent" <?php selected( isset( $settings['discount_type'] ) ? $settings['discount_type'] : 'percent', 'percent' ); ?>><?php esc_html_e( 'Percentage', 'checkout-rescuer' ); ?></option>
                        <option value="fixed" <?php selected( isset( $settings['discount_type'] ) ? $settings['discount_type'] : 'percent', 'fixed' ); ?>><?php esc_html_e( 'Fixed Amount', 'checkout-rescuer' ); ?></option>
                    </select>
                </div>
                <div class="cr-field">
                    <label class="cr-label"><?php esc_html_e( 'Discount Amount', 'checkout-rescuer' ); ?></label>
                    <input type="number" name="discount_amount" value="<?php echo esc_attr( isset( $settings['discount_amount'] ) ? $settings['discount_amount'] : 10 ); ?>" class="cr-input" min="1">
                </div>
                <div class="cr-field">
                    <label class="cr-label"><?php esc_html_e( 'Send Discount on Message #', 'checkout-rescuer' ); ?></label>
                    <select name="discount_message_step" class="cr-select">
                        <option value="1" <?php selected( isset( $settings['discount_message_step'] ) ? $settings['discount_message_step'] : 2, 1 ); ?>><?php esc_html_e( 'First Message', 'checkout-rescuer' ); ?></option>
                        <option value="2" <?php selected( isset( $settings['discount_message_step'] ) ? $settings['discount_message_step'] : 2, 2 ); ?>><?php esc_html_e( 'Second Message', 'checkout-rescuer' ); ?></option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Consent Settings -->
        <div class="cr-card">
            <div class="cr-card-header">
                <h3><?php esc_html_e( 'Consent & Privacy', 'checkout-rescuer' ); ?></h3>
            </div>
            <div class="cr-card-body">
                <div class="cr-field">
                    <label class="cr-label"><?php esc_html_e( 'Require Consent', 'checkout-rescuer' ); ?></label>
                    <label class="cr-toggle">
                        <input type="checkbox" name="require_consent" value="yes" <?php checked( isset( $settings['require_consent'] ) ? $settings['require_consent'] : 'yes', 'yes' ); ?>>
                        <span class="cr-toggle-slider"></span>
                    </label>
                    <p class="cr-help"><?php esc_html_e( 'Recommended for GDPR compliance.', 'checkout-rescuer' ); ?></p>
                </div>
                <div class="cr-field">
                    <label class="cr-label"><?php esc_html_e( 'Consent Text', 'checkout-rescuer' ); ?></label>
                    <textarea name="consent_text" class="cr-textarea" rows="2"><?php echo esc_textarea( isset( $settings['consent_text'] ) ? $settings['consent_text'] : '' ); ?></textarea>
                </div>
                <div class="cr-field">
                    <label class="cr-label"><?php esc_html_e( 'Phone Field Label', 'checkout-rescuer' ); ?></label>
                    <input type="text" name="phone_field_label" value="<?php echo esc_attr( isset( $settings['phone_field_label'] ) ? $settings['phone_field_label'] : 'Phone (for order updates)' ); ?>" class="cr-input">
                </div>
            </div>
        </div>
    </div>

    <!-- Message Templates -->
    <div class="cr-card cr-card-full">
        <div class="cr-card-header">
            <h3><?php esc_html_e( 'Message Templates', 'checkout-rescuer' ); ?></h3>
            <div class="cr-template-vars">
                <span class="cr-help-label"><?php esc_html_e( 'Available variables:', 'checkout-rescuer' ); ?></span>
                <?php foreach ( $variables as $var => $desc ) : ?>
                    <code class="cr-var-tag" title="<?php echo esc_attr( $desc ); ?>"><?php echo esc_html( $var ); ?></code>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="cr-card-body">
            <div class="cr-templates-grid">
                <div class="cr-field">
                    <label class="cr-label"><?php esc_html_e( 'Message #1 (Initial Recovery)', 'checkout-rescuer' ); ?></label>
                    <textarea name="message_template_1" class="cr-textarea cr-textarea-lg" rows="6"><?php echo esc_textarea( isset( $settings['message_template_1'] ) ? $settings['message_template_1'] : '' ); ?></textarea>
                </div>
                <div class="cr-field">
                    <label class="cr-label"><?php esc_html_e( 'Message #2 (With Discount)', 'checkout-rescuer' ); ?></label>
                    <textarea name="message_template_2" class="cr-textarea cr-textarea-lg" rows="6"><?php echo esc_textarea( isset( $settings['message_template_2'] ) ? $settings['message_template_2'] : '' ); ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <!-- Test Message -->
    <div class="cr-card">
        <div class="cr-card-header">
            <h3><?php esc_html_e( 'Test Connection', 'checkout-rescuer' ); ?></h3>
        </div>
        <div class="cr-card-body">
            <div class="cr-test-form">
                <div class="cr-field">
                    <label class="cr-label"><?php esc_html_e( 'Phone Number', 'checkout-rescuer' ); ?></label>
                    <input type="text" id="cr-test-phone" class="cr-input" placeholder="+1234567890">
                </div>
                <div class="cr-field">
                    <label class="cr-label"><?php esc_html_e( 'Channel', 'checkout-rescuer' ); ?></label>
                    <select id="cr-test-channel" class="cr-select">
                        <option value="whatsapp"><?php esc_html_e( 'WhatsApp', 'checkout-rescuer' ); ?></option>
                        <option value="sms"><?php esc_html_e( 'SMS', 'checkout-rescuer' ); ?></option>
                    </select>
                </div>
                <button id="cr-send-test" class="cr-btn cr-btn-secondary"><?php esc_html_e( 'Send Test Message', 'checkout-rescuer' ); ?></button>
            </div>
            <div id="cr-test-result" class="cr-test-result" style="display:none;"></div>
        </div>
    </div>
</div>
