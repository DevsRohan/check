/**
 * Analytics Routes - Dashboard data
 */

const express = require('express');
const router = express.Router();
const { getDb } = require('../utils/database');

// GET /api/analytics/summary - Summary stats
router.get('/summary', (req, res) => {
  try {
    const db = getDb();
    const { period = '30days' } = req.query;
    const dateCondition = getDateCondition(period);

    const row = db.prepare(`
      SELECT
        COALESCE(SUM(carts_abandoned), 0) as total_abandoned,
        COALESCE(SUM(messages_sent), 0) as total_messages,
        COALESCE(SUM(messages_delivered), 0) as total_delivered,
        COALESCE(SUM(carts_recovered), 0) as total_recovered,
        COALESCE(SUM(revenue_recovered), 0) as total_revenue_recovered,
        COALESCE(SUM(revenue_lost), 0) as total_revenue_lost
      FROM analytics
      ${dateCondition}
    `).get();

    const recoveryRate = row.total_abandoned > 0
      ? ((row.total_recovered / row.total_abandoned) * 100).toFixed(1)
      : 0;

    res.json({
      success: true,
      data: {
        abandoned: row.total_abandoned,
        messages_sent: row.total_messages,
        recovered: row.total_recovered,
        revenue_recovered: row.total_revenue_recovered,
        revenue_lost: row.total_revenue_lost,
        recovery_rate: parseFloat(recoveryRate),
      },
    });
  } catch (error) {
    res.status(500).json({ success: false, error: error.message });
  }
});

// GET /api/analytics/chart - Chart data
router.get('/chart', (req, res) => {
  try {
    const db = getDb();
    const { period = '30days' } = req.query;
    const dateCondition = getDateCondition(period);

    const rows = db.prepare(`
      SELECT date, carts_abandoned, carts_recovered, revenue_recovered, messages_sent
      FROM analytics
      ${dateCondition}
      ORDER BY date ASC
    `).all();

    const data = {
      labels: rows.map(r => formatDate(r.date)),
      abandoned: rows.map(r => r.carts_abandoned),
      recovered: rows.map(r => r.carts_recovered),
      revenue: rows.map(r => r.revenue_recovered),
      messages: rows.map(r => r.messages_sent),
    };

    res.json({ success: true, data });
  } catch (error) {
    res.status(500).json({ success: false, error: error.message });
  }
});

// GET /api/analytics/live - Live stats
router.get('/live', (req, res) => {
  try {
    const db = getDb();

    const active = db.prepare("SELECT COUNT(*) as count FROM abandoned_carts WHERE status = 'active'").get().count;
    const abandoned = db.prepare("SELECT COUNT(*) as count FROM abandoned_carts WHERE status = 'abandoned'").get().count;
    const pendingValue = db.prepare("SELECT COALESCE(SUM(cart_total), 0) as total FROM abandoned_carts WHERE status = 'abandoned'").get().total;

    res.json({
      success: true,
      data: {
        active_carts: active,
        abandoned_carts: abandoned,
        pending_value: pendingValue,
      },
    });
  } catch (error) {
    res.status(500).json({ success: false, error: error.message });
  }
});

function getDateCondition(period) {
  const days = { '7days': 7, '30days': 30, '90days': 90, 'all': 0 }[period] || 30;
  if (days === 0) return '';
  const dateStr = new Date(Date.now() - days * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
  return `WHERE date >= '${dateStr}'`;
}

function formatDate(dateStr) {
  const d = new Date(dateStr);
  return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
}

module.exports = router;
