/**
 * Cart Routes - CRUD for abandoned carts
 */

const express = require('express');
const router = express.Router();
const { getDb } = require('../utils/database');

// GET /api/carts - List carts with filtering
router.get('/', (req, res) => {
  try {
    const db = getDb();
    const { status = 'abandoned', page = 1, per_page = 20, search = '' } = req.query;

    let where = '1=1';
    const params = [];

    if (status && status !== 'all') {
      where += ' AND status = ?';
      params.push(status);
    }

    if (search) {
      where += ' AND (customer_name LIKE ? OR customer_email LIKE ?)';
      params.push(`%${search}%`, `%${search}%`);
    }

    const offset = (parseInt(page) - 1) * parseInt(per_page);
    const total = db.prepare(`SELECT COUNT(*) as count FROM abandoned_carts WHERE ${where}`).get(...params).count;

    params.push(parseInt(per_page), offset);
    const items = db.prepare(`
      SELECT id, session_id, store_url, customer_name, customer_email,
             customer_phone, cart_contents, cart_total, currency, recovery_token,
             status, messages_sent, abandoned_at, recovered_at, coupon_code, created_at
      FROM abandoned_carts WHERE ${where}
      ORDER BY CASE WHEN abandoned_at IS NULL THEN created_at ELSE abandoned_at END DESC
      LIMIT ? OFFSET ?
    `).all(...params);

    // Mask phone numbers
    const maskedItems = items.map(item => ({
      ...item,
      customer_phone: maskPhone(item.customer_phone),
      cart_contents: JSON.parse(item.cart_contents || '[]'),
    }));

    res.json({
      success: true,
      data: {
        items: maskedItems,
        total,
        pages: Math.ceil(total / parseInt(per_page)),
        page: parseInt(page),
      },
    });
  } catch (error) {
    res.status(500).json({ success: false, error: error.message });
  }
});

// GET /api/carts/:id - Get single cart detail
router.get('/:id', (req, res) => {
  try {
    const db = getDb();
    const cart = db.prepare('SELECT * FROM abandoned_carts WHERE id = ?').get(req.params.id);

    if (!cart) {
      return res.status(404).json({ success: false, error: 'Cart not found.' });
    }

    const messages = db.prepare('SELECT * FROM messages WHERE cart_id = ? ORDER BY created_at DESC').all(cart.id);

    res.json({
      success: true,
      data: {
        ...cart,
        customer_phone: maskPhone(cart.customer_phone),
        cart_contents: JSON.parse(cart.cart_contents || '[]'),
        messages,
      },
    });
  } catch (error) {
    res.status(500).json({ success: false, error: error.message });
  }
});

// POST /api/carts/:id/resend - Resend recovery message
router.post('/:id/resend', async (req, res) => {
  try {
    const db = getDb();
    const cart = db.prepare("SELECT * FROM abandoned_carts WHERE id = ? AND status = 'abandoned'").get(req.params.id);

    if (!cart) {
      return res.status(404).json({ success: false, error: 'Cart not found or not abandoned.' });
    }

    // Import the message sending function
    const { sendMessage } = require('../services/whatsapp');
    const { v4: uuidv4 } = require('uuid');

    const step = cart.messages_sent + 1;
    const storeName = db.prepare("SELECT value FROM settings WHERE key = 'store_name'").get();
    const baseUrl = db.prepare("SELECT value FROM settings WHERE key = 'recovery_base_url'").get();
    const recoveryLink = `${baseUrl ? baseUrl.value : ''}/recover/${cart.recovery_token}`;

    const message = `Hey ${cart.customer_name || 'there'}! 👋\n\nYour cart is still waiting at ${storeName ? storeName.value : 'our store'}.\n\n👉 Complete your order: ${recoveryLink}`;

    const result = await sendMessage(cart.customer_phone, message);

    // Log
    const now = new Date().toISOString();
    db.prepare(`
      INSERT INTO messages (cart_id, channel, phone_number, message_content, message_id, status, step_number, error_message, sent_at, created_at)
      VALUES (?, 'whatsapp', ?, ?, ?, ?, ?, ?, ?, ?)
    `).run(cart.id, cart.customer_phone, message, result.success ? result.messageId || '' : '', result.success ? 'sent' : 'failed', step, result.error || null, result.success ? now : null, now);

    db.prepare('UPDATE abandoned_carts SET messages_sent = messages_sent + 1, updated_at = ? WHERE id = ?').run(now, cart.id);

    res.json({ success: result.success, message: result.success ? 'Message sent.' : result.error });
  } catch (error) {
    res.status(500).json({ success: false, error: error.message });
  }
});

// DELETE /api/carts/:id - Delete a cart
router.delete('/:id', (req, res) => {
  try {
    const db = getDb();
    db.prepare('DELETE FROM messages WHERE cart_id = ?').run(req.params.id);
    db.prepare('DELETE FROM abandoned_carts WHERE id = ?').run(req.params.id);
    res.json({ success: true, message: 'Cart deleted.' });
  } catch (error) {
    res.status(500).json({ success: false, error: error.message });
  }
});

function maskPhone(phone) {
  if (!phone || phone.length < 6) return '****';
  return phone.substring(0, 4) + '*'.repeat(phone.length - 6) + phone.substring(phone.length - 2);
}

module.exports = router;
