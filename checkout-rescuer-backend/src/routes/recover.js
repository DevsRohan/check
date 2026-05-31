/**
 * Recovery Link Handler
 * When customer clicks recovery link from WhatsApp message
 */

const { getDb } = require('../utils/database');

async function handleRecovery(req, res) {
  try {
    const { token } = req.params;

    if (!token || token.length < 20) {
      return res.redirect('/recovery-expired.html');
    }

    const db = getDb();

    const cart = db.prepare(`
      SELECT * FROM abandoned_carts WHERE recovery_token = ? AND status IN ('abandoned', 'active')
    `).get(token);

    if (!cart) {
      return res.redirect('/recovery-expired.html');
    }

    // Check if recovery link has expired (72 hours from abandonment)
    if (cart.abandoned_at) {
      const abandonedTime = new Date(cart.abandoned_at).getTime();
      const expiryMs = 72 * 60 * 60 * 1000; // 72 hours
      if (Date.now() - abandonedTime > expiryMs) {
        return res.redirect('/recovery-expired.html');
      }
    }

    const now = new Date().toISOString();

    // Mark as recovered (analytics will be updated when order is actually placed via track/converted)
    db.prepare("UPDATE abandoned_carts SET status = 'recovered', recovered_at = ?, updated_at = ? WHERE id = ?")
      .run(now, now, cart.id);

    // Build WooCommerce cart restoration URL
    const cartItems = JSON.parse(cart.cart_contents || '[]');
    let redirectUrl = cart.store_url || '/';

    // If store_url is set, redirect to WooCommerce with cart restoration params
    if (cart.store_url) {
      const params = new URLSearchParams();
      params.set('cr_restore', 'true');
      params.set('cr_token', token);
      params.set('cr_session', cart.session_id);

      if (cart.coupon_code) {
        params.set('cr_coupon', cart.coupon_code);
      }

      // Redirect to store checkout with params
      redirectUrl = `${cart.store_url}/checkout/?${params.toString()}`;
    }

    res.redirect(redirectUrl);
  } catch (error) {
    console.error('[RECOVER] Error:', error.message);
    res.redirect('/recovery-expired.html');
  }
}

module.exports = handleRecovery;
