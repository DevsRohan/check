/**
 * Settings Routes - Plugin configuration
 */

const express = require('express');
const router = express.Router();
const { getDb } = require('../utils/database');

// GET /api/settings - Get all settings
router.get('/', (req, res) => {
  try {
    const db = getDb();
    const rows = db.prepare('SELECT key, value FROM settings').all();

    const settings = {};
    for (const row of rows) {
      settings[row.key] = row.value;
    }

    res.json({ success: true, data: settings });
  } catch (error) {
    res.status(500).json({ success: false, error: error.message });
  }
});

// POST /api/settings - Update settings
router.post('/', (req, res) => {
  try {
    const db = getDb();
    const { settings } = req.body;

    if (!settings || typeof settings !== 'object') {
      return res.status(400).json({ success: false, error: 'Settings object is required.' });
    }

    const allowedKeys = [
      'enabled', 'abandonment_timeout', 'max_messages',
      'message_1_delay', 'message_2_delay', 'enable_discount',
      'discount_type', 'discount_amount', 'discount_message_step',
      'message_template_1', 'message_template_2',
      'require_consent', 'country_code', 'store_name', 'recovery_base_url',
    ];

    const upsert = db.prepare('INSERT OR REPLACE INTO settings (key, value) VALUES (?, ?)');

    const updateMany = db.transaction(() => {
      for (const [key, value] of Object.entries(settings)) {
        if (allowedKeys.includes(key)) {
          upsert.run(key, String(value));
        }
      }
    });
    updateMany();

    res.json({ success: true, message: 'Settings saved.' });
  } catch (error) {
    res.status(500).json({ success: false, error: error.message });
  }
});

module.exports = router;
