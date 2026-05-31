/**
 * Track Routes - Cart tracking from WooCommerce (public-facing)
 */

const express = require('express');
const router = express.Router();
const { getDb } = require('../utils/database');
const { v4: uuidv4 } = require('uuid');

// POST /api/track/cart - Save/update cart from WooCommerce
router.post('/cart', (req, res) => {
  try {
    const { session_id, store_url, customer_name, customer_email, customer_phone, cart_contents, cart_total, currency, consent } = req.body;

    if (!customer_phone || !session_id) {
      return res.status(400).json({ success: false, error: 'session_id and customer_phone are required.' });
    }

    // Validate phone
    const phone = sanitizePhone(customer_phone);
    if (!phone) {
      return res.status(400).json({ success: false, error: 'Invalid phone number.' });
    }

    const db = getDb();
    const now = new Date().toISOString();

    // Check if cart exists for this session
    const existing = db.prepare(`
      SELECT id FROM abandoned_carts
      WHERE session_id = ? AND status IN ('active', 'abandoned')
      ORDER BY id DESC LIMIT 1
    `).get(session_id);

    if (existing) {
      db.prepare(`
        UPDATE abandoned_carts SET
          customer_name = ?, customer_email = ?, customer_phone = ?,
          cart_contents = ?, cart_total = ?, currency = ?,
          last_activity = ?, updated_at = ?, consent_given = ?, store_url = ?
        WHERE id = ?
      `).run(
        customer_name || '', customer_email || '', phone,
        JSON.stringify(cart_contents || []), parseFloat(cart_total) || 0, currency || 'USD',
        now, now, consent ? 1 : 0, store_url || '', existing.id
      );
    } else {
      const token = uuidv4().replace(/-/g, '');

      db.prepare(`
        INSERT INTO abandoned_carts
          (session_id, store_url, customer_name, customer_email, customer_phone,
           cart_contents, cart_total, currency, recovery_token, status,
           consent_given, last_activity, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, ?, ?, ?)
      `).run(
        session_id, store_url || '', customer_name || '', customer_email || '', phone,
        JSON.stringify(cart_contents || []), parseFloat(cart_total) || 0, currency || 'USD',
        token, consent ? 1 : 0, now, now, now
      );
    }

    res.json({ success: true, message: 'Cart tracked.' });
  } catch (error) {
    console.error('[TRACK] Error:', error.message);
    res.status(500).json({ success: false, error: error.message });
  }
});

// POST /api/track/heartbeat - Update last activity
router.post('/heartbeat', (req, res) => {
  try {
    const { session_id, cart_contents, cart_total } = req.body;

    if (!session_id) {
      return res.status(400).json({ success: false, error: 'session_id is required.' });
    }

    const db = getDb();
    const now = new Date().toISOString();

    const updates = ['last_activity = ?', 'updated_at = ?'];
    const params = [now, now];

    if (cart_contents) {
      updates.push('cart_contents = ?');
      params.push(JSON.stringify(cart_contents));
    }
    if (cart_total !== undefined) {
      updates.push('cart_total = ?');
      params.push(parseFloat(cart_total) || 0);
    }

    params.push(session_id);

    db.prepare(`
      UPDATE abandoned_carts SET ${updates.join(', ')}
      WHERE session_id = ? AND status = 'active'
      ORDER BY id DESC LIMIT 1
    `).run(...params);

    res.json({ success: true });
  } catch (error) {
    res.status(500).json({ success: false, error: error.message });
  }
});

// POST /api/track/converted - Mark cart as converted
router.post('/converted', (req, res) => {
  try {
    const { session_id, order_id, order_total } = req.body;

    if (!session_id) {
      return res.status(400).json({ success: false, error: 'session_id is required.' });
    }

    const db = getDb();
    const now = new Date().toISOString();

    const cart = db.prepare(`
      SELECT id, status, cart_total, currency FROM abandoned_carts
      WHERE session_id = ? AND status IN ('active', 'abandoned', 'recovered')
      ORDER BY id DESC LIMIT 1
    `).get(session_id);

    if (!cart) {
      return res.json({ success: true, message: 'No cart found.' });
    }

    if (cart.status === 'abandoned' || cart.status === 'recovered') {
      // This is a recovery!
      db.prepare("UPDATE abandoned_carts SET status = 'recovered', recovered_at = ?, updated_at = ? WHERE id = ?")
        .run(now, now, cart.id);

      // Record recovery
      db.prepare(`
        INSERT INTO recoveries (cart_id, order_id, recovered_amount, currency, channel, recovered_at)
        VALUES (?, ?, ?, ?, 'whatsapp', ?)
      `).run(cart.id, order_id || '', parseFloat(order_total) || cart.cart_total, cart.currency, now);

      // Update analytics
      const today = now.split('T')[0];
      const existing = db.prepare('SELECT id FROM analytics WHERE date = ?').get(today);
      if (existing) {
        db.prepare('UPDATE analytics SET carts_recovered = carts_recovered + 1, revenue_recovered = revenue_recovered + ? WHERE date = ?')
          .run(parseFloat(order_total) || cart.cart_total, today);
      } else {
        db.prepare('INSERT INTO analytics (date, carts_recovered, revenue_recovered) VALUES (?, 1, ?)')
          .run(today, parseFloat(order_total) || cart.cart_total);
      }
    } else {
      db.prepare("UPDATE abandoned_carts SET status = 'converted', updated_at = ? WHERE id = ?")
        .run(now, cart.id);
    }

    res.json({ success: true, message: 'Cart marked as converted.' });
  } catch (error) {
    res.status(500).json({ success: false, error: error.message });
  }
});

function sanitizePhone(phone) {
  if (!phone) return '';
  let cleaned = phone.replace(/[^\d+]/g, '');
  const digits = cleaned.replace(/\D/g, '');
  if (digits.length < 10 || digits.length > 15) return '';
  return digits;
}

module.exports = router;
