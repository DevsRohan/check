=== Checkout Rescuer - WhatsApp & SMS Cart Recovery for WooCommerce ===
Contributors: checkoutrescuer
Tags: woocommerce, abandoned cart, whatsapp, sms, cart recovery
Requires at least: 6.0
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Recover abandoned WooCommerce carts via WhatsApp & SMS. 95% open rate vs 20% email. 3-5x higher recovery.

== Description ==

**Stop losing sales to abandoned carts.** Checkout Rescuer sends automated WhatsApp and SMS recovery messages to customers who abandon their carts — where they actually read them.

= Why WhatsApp & SMS? =

* **95% open rate** for WhatsApp messages (vs 20% for email)
* **3-5x higher recovery rate** compared to email cart recovery
* Messages are read within **3 minutes** on average
* Customers can **reply instantly** with questions

= Features =

* **Automatic cart detection** — Captures phone numbers during checkout
* **WhatsApp recovery messages** — Send via Twilio WhatsApp API
* **SMS fallback** — Automatically falls back to SMS if WhatsApp fails
* **Multi-step sequences** — Up to 2 follow-up messages
* **Auto-discount coupons** — Generate time-limited discount codes
* **Recovery links** — One-click cart restoration
* **Analytics dashboard** — Track recovered revenue, rates, and trends
* **GDPR compliant** — Consent checkbox and data retention controls
* **Encrypted storage** — Phone numbers encrypted at rest

= How It Works =

1. Customer adds items to cart and enters phone number at checkout
2. Customer abandons checkout (closes tab, navigates away)
3. After 30 minutes, customer receives a WhatsApp/SMS message with their cart and a recovery link
4. Customer clicks the link, cart is restored, and they complete their purchase

= Requirements =

* WooCommerce 7.0+
* PHP 7.4+
* A Twilio account (for WhatsApp/SMS sending)

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/checkout-rescuer/`
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Follow the onboarding wizard to connect your Twilio account
4. Configure your message templates and timing
5. Start recovering abandoned carts!

== Frequently Asked Questions ==

= Do I need a Twilio account? =
Yes. Twilio handles the actual message delivery. They offer a free trial with enough credits to test.

= How much does Twilio cost? =
WhatsApp messages cost approximately $0.005-0.05 per message depending on region. SMS varies by country.

= Is it GDPR compliant? =
Yes. The plugin includes consent checkboxes, data encryption, and configurable data retention policies.

= Does it work with variable products? =
Yes. All product types including simple, variable, grouped, and bundled products are supported.

== Changelog ==

= 1.0.0 =
* Initial release
* WhatsApp cart recovery
* SMS cart recovery with fallback
* Auto-discount coupon generation
* Multi-step message sequences
* Analytics dashboard
* Onboarding wizard
* GDPR consent handling
* Encrypted phone storage
