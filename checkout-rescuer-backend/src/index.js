/**
 * Checkout Rescuer Backend
 * WhatsApp Cart Recovery Server for Hugging Face Deployment
 */

require('dotenv').config();

const express = require('express');
const cors = require('cors');
const helmet = require('helmet');
const path = require('path');
const rateLimit = require('express-rate-limit');

const { initDatabase } = require('./utils/database');
const { initWhatsApp } = require('./services/whatsapp');
const { initCronJobs } = require('./services/cron');
const authMiddleware = require('./middleware/auth');

// Routes
const whatsappRoutes = require('./routes/whatsapp');
const cartRoutes = require('./routes/carts');
const messageRoutes = require('./routes/messages');
const analyticsRoutes = require('./routes/analytics');
const settingsRoutes = require('./routes/settings');

const app = express();
const PORT = process.env.PORT || 7860;

// Security
app.use(helmet({ contentSecurityPolicy: false, crossOriginEmbedderPolicy: false }));
app.use(cors({ origin: process.env.CORS_ORIGIN || '*', credentials: true }));

// Rate limiting
const limiter = rateLimit({
  windowMs: 1 * 60 * 1000,
  max: 100,
  standardHeaders: true,
  legacyHeaders: false,
});
app.use('/api/', limiter);

// Body parsing
app.use(express.json({ limit: '10mb' }));
app.use(express.urlencoded({ extended: true, limit: '10mb' }));

// Static files (for QR code page)
app.use(express.static(path.join(__dirname, '../public')));

// Health check (no auth)
app.get('/api/health', (req, res) => {
  res.json({ status: 'ok', timestamp: new Date().toISOString(), version: '1.0.0' });
});

// API Routes
app.use('/api/whatsapp', authMiddleware, whatsappRoutes);
app.use('/api/carts', authMiddleware, cartRoutes);
app.use('/api/messages', authMiddleware, messageRoutes);
app.use('/api/analytics', authMiddleware, analyticsRoutes);
app.use('/api/settings', authMiddleware, settingsRoutes);

// Cart tracking endpoint (from WooCommerce - different auth)
app.use('/api/track', require('./routes/track'));

// Recovery link handler
app.get('/recover/:token', require('./routes/recover'));

// Error handler
app.use((err, req, res, next) => {
  console.error('[ERROR]', err.message);
  res.status(err.status || 500).json({
    success: false,
    error: process.env.NODE_ENV === 'production' ? 'Internal server error' : err.message,
  });
});

// Start server
async function start() {
  try {
    console.log('[INIT] Starting Checkout Rescuer Backend...');

    // Initialize database
    initDatabase();
    console.log('[DB] Database initialized');

    // Initialize WhatsApp
    await initWhatsApp();
    console.log('[WA] WhatsApp service initialized');

    // Initialize cron jobs
    initCronJobs();
    console.log('[CRON] Cron jobs scheduled');

    app.listen(PORT, '0.0.0.0', () => {
      console.log(`[SERVER] Running on port ${PORT}`);
      console.log(`[SERVER] QR Code page: http://localhost:${PORT}`);
    });
  } catch (error) {
    console.error('[FATAL] Failed to start:', error);
    process.exit(1);
  }
}

start();
