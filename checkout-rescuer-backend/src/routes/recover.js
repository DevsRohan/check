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

    const now = new Date().toISOString();

    // Mark as recovered
    db.prepare("UPDATE abandoned_carts SET status = 'recovered', recovered_at = ?, updated_at = ? WHERE id = ?")
      .run(now, now, cart.id);

    // Update analytics
    const today = now.split('T')[0];
    const existing = db.prepare('SELECT id FROM analytics WHERE date = ?').get(today);
    if (existing) {
      db.prepare('UPDATE analytics SET carts_recovered = carts_recovered + 1, revenue_recovered = revenue_recovered + ? WHERE date = ?')
        .run(cart.cart_total, today);
    } else {
      db.prepare('INSERT INTO analytics (date, carts_recovered, revenue_recovered) VALUES (?, 1, ?)')
        .run(today, cart.cart_total);
    }

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
