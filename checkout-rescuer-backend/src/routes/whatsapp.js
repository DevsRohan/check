/**
 * WhatsApp Routes - Status, QR, Send, Disconnect
 */

const express = require('express');
const router = express.Router();
const { getStatus, sendMessage, disconnect, restart } = require('../services/whatsapp');

// GET /api/whatsapp/status - Get connection status + QR code
router.get('/status', (req, res) => {
  const status = getStatus();
  res.json({ success: true, data: status });
});

// POST /api/whatsapp/send - Send a message
router.post('/send', async (req, res) => {
  const { phone, message } = req.body;

  if (!phone || !message) {
    return res.status(400).json({ success: false, error: 'Phone and message are required.' });
  }

  const result = await sendMessage(phone, message);
  res.json(result);
});

// POST /api/whatsapp/test - Send test message
router.post('/test', async (req, res) => {
  const { phone } = req.body;

  if (!phone) {
    return res.status(400).json({ success: false, error: 'Phone number is required.' });
  }

  const testMsg = `✅ Checkout Rescuer is connected!\n\nThis is a test message. Your WhatsApp cart recovery is working correctly.\n\nTimestamp: ${new Date().toLocaleString()}`;

  const result = await sendMessage(phone, testMsg);
  res.json(result);
});

// POST /api/whatsapp/disconnect - Disconnect WhatsApp
router.post('/disconnect', async (req, res) => {
  await disconnect();
  res.json({ success: true, message: 'WhatsApp disconnected.' });
});

// POST /api/whatsapp/restart - Restart WhatsApp connection
router.post('/restart', async (req, res) => {
  await restart();
  res.json({ success: true, message: 'WhatsApp restarting. Check status for QR code.' });
});

module.exports = router;
