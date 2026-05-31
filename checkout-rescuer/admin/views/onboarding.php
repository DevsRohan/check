<?php
/**
 * Onboarding wizard view.
 *
 * @package Checkout_Rescuer
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$steps        = CR_Admin_Onboarding::get_steps();
$instructions = CR_Admin_Onboarding::get_twilio_instructions();
?>
<div class="cr-app cr-onboarding" id="cr-onboarding">
    <div class="cr-onboarding-container">
        <!-- Progress -->
        <div class="cr-onboarding-progress">
            <div class="cr-progress-steps">
                <?php for ( $i = 1; $i <= 4; $i++ ) : ?>
                    <div class="cr-progress-step" data-step="<?php echo esc_attr( $i ); ?>">
                        <div class="cr-progress-dot<?php echo 1 === $i ? ' cr-progress-dot-active' : ''; ?>">
                            <span><?php echo esc_html( $i ); ?></span>
                        </div>
                        <?php if ( $i < 4 ) : ?>
                            <div class="cr-progress-line"></div>
                        <?php endif; ?>
                    </div>
                <?php endfor; ?>
            </div>
        </div>

        <!-- Step 1: Welcome -->
        <div class="cr-onboarding-step cr-step-active" data-step="1">
            <div class="cr-onboarding-content">
                <div class="cr-onboarding-icon">
                    <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                </div>
                <h2><?php echo esc_html( $steps[1]['title'] ); ?></h2>
                <p class="cr-onboarding-desc"><?php echo esc_html( $steps[1]['description'] ); ?></p>
                <div class="cr-onboarding-stats">
                    <div class="cr-ob-stat">
                        <span class="cr-ob-stat-value">95%</span>
                        <span class="cr-ob-stat-label"><?php esc_html_e( 'WhatsApp Open Rate', 'checkout-rescuer' ); ?></span>
                    </div>
                    <div class="cr-ob-stat">
                        <span class="cr-ob-stat-value">3-5x</span>
                        <span class="cr-ob-stat-label"><?php esc_html_e( 'Higher Recovery vs Email', 'checkout-rescuer' ); ?></span>
                    </div>
                    <div class="cr-ob-stat">
                        <span class="cr-ob-stat-value">~2 min</span>
                        <span class="cr-ob-stat-label"><?php esc_html_e( 'Setup Time', 'checkout-rescuer' ); ?></span>
                    </div>
                </div>
                <button class="cr-btn cr-btn-primary cr-btn-lg cr-next-step"><?php esc_html_e( 'Get Started', 'checkout-rescuer' ); ?></button>
            </div>
        </div>

        <!-- Step 2: Twilio Credentials -->
        <div class="cr-onboarding-step" data-step="2">
            <div class="cr-onboarding-content">
                <h2><?php echo esc_html( $steps[2]['title'] ); ?></h2>
                <p class="cr-onboarding-desc"><?php echo esc_html( $steps[2]['description'] ); ?></p>

                <div class="cr-onboarding-instructions">
                    <h4><?php esc_html_e( 'How to get your credentials:', 'checkout-rescuer' ); ?></h4>
                    <ol>
                        <?php foreach ( $instructions as $instruction ) : ?>
                            <li><?php echo esc_html( $instruction ); ?></li>
                        <?php endforeach; ?>
                    </ol>
                </div>

                <div class="cr-onboarding-form">
                    <div class="cr-field">
                        <label class="cr-label"><?php esc_html_e( 'Twilio Account SID', 'checkout-rescuer' ); ?></label>
                        <input type="text" id="cr-ob-sid" class="cr-input" placeholder="ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx">
                    </div>
                    <div class="cr-field">
                        <label class="cr-label"><?php esc_html_e( 'Twilio Auth Token', 'checkout-rescuer' ); ?></label>
                        <input type="password" id="cr-ob-token" class="cr-input" placeholder="Your auth token">
                    </div>
                </div>

                <div class="cr-onboarding-actions">
                    <button class="cr-btn cr-btn-secondary cr-prev-step"><?php esc_html_e( 'Back', 'checkout-rescuer' ); ?></button>
                    <button class="cr-btn cr-btn-primary cr-next-step"><?php esc_html_e( 'Continue', 'checkout-rescuer' ); ?></button>
                </div>
            </div>
        </div>

        <!-- Step 3: Phone Numbers -->
        <div class="cr-onboarding-step" data-step="3">
            <div class="cr-onboarding-content">
                <h2><?php echo esc_html( $steps[3]['title'] ); ?></h2>
                <p class="cr-onboarding-desc"><?php echo esc_html( $steps[3]['description'] ); ?></p>

                <div class="cr-onboarding-form">
                    <div class="cr-field">
                        <label class="cr-label"><?php esc_html_e( 'WhatsApp Business Number', 'checkout-rescuer' ); ?></label>
                        <input type="text" id="cr-ob-whatsapp" class="cr-input" placeholder="+14155238886">
                        <p class="cr-help"><?php esc_html_e( 'Your Twilio WhatsApp-enabled number (or sandbox number for testing)', 'checkout-rescuer' ); ?></p>
                    </div>
                    <div class="cr-field">
                        <label class="cr-label"><?php esc_html_e( 'SMS Number (Optional)', 'checkout-rescuer' ); ?></label>
                        <input type="text" id="cr-ob-phone" class="cr-input" placeholder="+1234567890">
                        <p class="cr-help"><?php esc_html_e( 'For SMS fallback when WhatsApp is unavailable', 'checkout-rescuer' ); ?></p>
                    </div>
                </div>

                <div class="cr-onboarding-actions">
                    <button class="cr-btn cr-btn-secondary cr-prev-step"><?php esc_html_e( 'Back', 'checkout-rescuer' ); ?></button>
                    <button class="cr-btn cr-btn-primary cr-next-step"><?php esc_html_e( 'Continue', 'checkout-rescuer' ); ?></button>
                </div>
            </div>
        </div>

        <!-- Step 4: Test & Launch -->
        <div class="cr-onboarding-step" data-step="4">
            <div class="cr-onboarding-content">
                <h2><?php echo esc_html( $steps[4]['title'] ); ?></h2>
                <p class="cr-onboarding-desc"><?php echo esc_html( $steps[4]['description'] ); ?></p>

                <div class="cr-onboarding-form">
                    <div class="cr-field">
                        <label class="cr-label"><?php esc_html_e( 'Your Phone (for test message)', 'checkout-rescuer' ); ?></label>
                        <input type="text" id="cr-ob-test-phone" class="cr-input" placeholder="+1234567890">
                    </div>
                    <button id="cr-ob-test-btn" class="cr-btn cr-btn-secondary"><?php esc_html_e( 'Send Test Message', 'checkout-rescuer' ); ?></button>
                    <div id="cr-ob-test-result" class="cr-test-result" style="display:none;"></div>
                </div>

                <div class="cr-onboarding-actions">
                    <button class="cr-btn cr-btn-secondary cr-prev-step"><?php esc_html_e( 'Back', 'checkout-rescuer' ); ?></button>
                    <button id="cr-ob-complete" class="cr-btn cr-btn-primary cr-btn-lg"><?php esc_html_e( 'Activate Checkout Rescuer', 'checkout-rescuer' ); ?></button>
                </div>
            </div>
        </div>

        <!-- Skip Link -->
        <div class="cr-onboarding-skip">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=checkout-rescuer&skip_onboarding=1' ) ); ?>"><?php esc_html_e( 'Skip setup (configure later)', 'checkout-rescuer' ); ?></a>
        </div>
    </div>
</div>
