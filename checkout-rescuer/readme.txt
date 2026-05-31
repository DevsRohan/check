=== Checkout Rescuer - WhatsApp Cart Recovery ===
Contributors: checkoutrescuer
Tags: woocommerce, abandoned cart, whatsapp, cart recovery
Requires at least: 6.0
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPLv2 or later

Recover abandoned WooCommerce carts via WhatsApp messages. No Twilio needed. Direct WhatsApp connection via QR code.

== Description ==

Checkout Rescuer sends WhatsApp messages to customers who abandon their carts. Uses direct WhatsApp Web connection (QR code scan) - no expensive API fees.

= Features =
* Direct WhatsApp connection (scan QR code)
* Automatic cart abandonment detection
* Multi-step recovery messages
* Auto-discount coupon generation
* One-click cart restoration
* Analytics dashboard
* GDPR consent handling
* Premium SaaS-style admin UI

= How It Works =
1. Deploy the Node.js backend to Hugging Face (free)
2. Install this plugin on WordPress
3. Scan QR code with your WhatsApp Business
4. Plugin automatically tracks carts and sends recovery messages

== Installation ==
1. Upload `checkout-rescuer` to `/wp-content/plugins/`
2. Activate the plugin
3. Deploy the backend to Hugging Face Spaces
4. Configure backend URL in Settings
5. Scan WhatsApp QR code from the WhatsApp tab
6. Start recovering abandoned carts!

== Changelog ==
= 2.0.0 =
* Complete rebuild with Node.js backend
* WhatsApp Web direct connection (no Twilio)
* Hugging Face deployment support
* Premium dark-mode admin dashboard
* Real-time QR code scanning
