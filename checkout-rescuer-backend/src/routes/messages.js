/**
 * Message Routes - View message log
 */

const express = require('express');
const router = express.Router();
const { getDb } = require('../utils/database');

// GET /api/messages - List messages with filtering
router.get('/', (req, res) => {
  try {
    const db = getDb();
    const { status, channel, page = 1, per_page = 20 } = req.query;

    let where = '1=1';
    const params = [];

    if (status) {
      where += ' AND m.status = ?';
      params.push(status);
    }

    if (channel) {
      where += ' AND m.channel = ?';
      params.push(channel);
    }

    const offset = (parseInt(page) - 1) * parseInt(per_page);
    const total = db.prepare(`SELECT COUNT(*) as count FROM messages m WHERE ${where}`).get(...params).count;

    params.push(parseInt(per_page), offset);
    const items = db.prepare(`
      SELECT m.*, c.customer_name, c.customer_email, c.cart_total, c.currency
      FROM messages m
      LEFT JOIN abandoned_carts c ON m.cart_id = c.id
      WHERE ${where}
      ORDER BY m.created_at DESC
      LIMIT ? OFFSET ?
    `).all(...params);

    res.json({
      success: true,
      data: {
        items,
        total,
        pages: Math.ceil(total / parseInt(per_page)),
        page: parseInt(page),
      },
    });
  } catch (error) {
    res.status(500).json({ success: false, error: error.message });
  }
});

// GET /api/messages/stats - Message statistics
router.get('/stats', (req, res) => {
  try {
    const db = getDb();

    const stats = db.prepare(`
      SELECT
        COUNT(*) as total,
        SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
        SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered,
        SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
        SUM(CASE WHEN status = 'read' THEN 1 ELSE 0 END) as read_count
      FROM messages
    `).get();

    res.json({ success: true, data: stats });
  } catch (error) {
    res.status(500).json({ success: false, error: error.message });
  }
});

module.exports = router;
