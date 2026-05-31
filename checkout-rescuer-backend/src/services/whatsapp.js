/**
 * WhatsApp Service - whatsapp-web.js integration
 * Handles QR code generation, connection, and message sending
 */

const { Client, LocalAuth } = require('whatsapp-web.js');
const qrcode = require('qrcode');
const path = require('path');

let client = null;
let qrCodeData = null;
let connectionStatus = 'disconnected'; // disconnected, qr_ready, connecting, connected
let clientInfo = null;

/**
 * Initialize WhatsApp client
 */
async function initWhatsApp() {
  client = new Client({
    authStrategy: new LocalAuth({
      dataPath: path.join(__dirname, '../../.wwebjs_auth'),
    }),
    puppeteer: {
      headless: true,
      args: [
        '--no-sandbox',
        '--disable-setuid-sandbox',
        '--disable-dev-shm-usage',
        '--disable-accelerated-2d-canvas',
        '--no-first-run',
        '--no-zygote',
        '--disable-gpu',
        '--single-process',
      ],
      executablePath: process.env.PUPPETEER_EXECUTABLE_PATH || undefined,
    },
    webVersionCache: {
      type: 'local',
      path: path.join(__dirname, '../../.wwebjs_cache'),
    },
  });

  client.on('qr', async (qr) => {
    console.log('[WA] QR Code received');
    connectionStatus = 'qr_ready';
    try {
      qrCodeData = await qrcode.toDataURL(qr, { width: 300, margin: 2 });
    } catch (err) {
      console.error('[WA] QR generation error:', err.message);
    }
  });

  client.on('authenticated', () => {
    console.log('[WA] Authenticated');
    connectionStatus = 'connecting';
    qrCodeData = null;
  });

  client.on('ready', () => {
    console.log('[WA] Client ready');
    connectionStatus = 'connected';
    clientInfo = client.info;
    qrCodeData = null;
  });

  client.on('auth_failure', (msg) => {
    console.error('[WA] Auth failure:', msg);
    connectionStatus = 'disconnected';
    qrCodeData = null;
  });

  client.on('disconnected', (reason) => {
    console.log('[WA] Disconnected:', reason);
    connectionStatus = 'disconnected';
    clientInfo = null;
    qrCodeData = null;

    // Auto-reconnect after 5 seconds
    setTimeout(() => {
      console.log('[WA] Attempting reconnection...');
      client.initialize().catch(err => {
        console.error('[WA] Reconnection failed:', err.message);
      });
    }, 5000);
  });

  client.on('message', (msg) => {
    // Handle incoming replies (for future features)
    console.log('[WA] Message received from:', msg.from);
  });

  try {
    await client.initialize();
  } catch (err) {
    console.error('[WA] Init error:', err.message);
    connectionStatus = 'disconnected';
  }
}

/**
 * Send a WhatsApp message
 * @param {string} phone - Phone number with country code (e.g., 919876543210)
 * @param {string} message - Message text
 * @returns {object} Result with success status
 */
async function sendMessage(phone, message) {
  if (connectionStatus !== 'connected' || !client) {
    return {
      success: false,
      error: 'WhatsApp is not connected. Please scan QR code first.',
    };
  }

  try {
    // Format phone number for WhatsApp
    const chatId = formatPhoneForWhatsApp(phone);

    // Check if number is registered on WhatsApp
    const isRegistered = await client.isRegisteredUser(chatId);
    if (!isRegistered) {
      return {
        success: false,
        error: `Number ${phone} is not registered on WhatsApp.`,
      };
    }

    // Send the message
    const result = await client.sendMessage(chatId, message);

    return {
      success: true,
      messageId: result.id ? result.id._serialized : '',
      timestamp: result.timestamp,
    };
  } catch (error) {
    console.error('[WA] Send error:', error.message);
    return {
      success: false,
      error: error.message,
    };
  }
}

/**
 * Format phone number for WhatsApp chat ID
 * @param {string} phone - Phone number
 * @returns {string} Formatted chat ID
 */
function formatPhoneForWhatsApp(phone) {
  // Remove all non-digit characters except leading +
  let cleaned = phone.replace(/[^\d]/g, '');

  // Remove leading zeros
  cleaned = cleaned.replace(/^0+/, '');

  return cleaned + '@c.us';
}

/**
 * Get current connection status
 */
function getStatus() {
  return {
    status: connectionStatus,
    qrCode: qrCodeData,
    info: clientInfo ? {
      name: clientInfo.pushname || '',
      phone: clientInfo.wid ? clientInfo.wid.user : '',
      platform: clientInfo.platform || '',
    } : null,
  };
}

/**
 * Disconnect WhatsApp
 */
async function disconnect() {
  if (client) {
    try {
      await client.logout();
    } catch (e) {
      // ignore
    }
    connectionStatus = 'disconnected';
    clientInfo = null;
    qrCodeData = null;
  }
}

/**
 * Restart WhatsApp (for reconnection)
 */
async function restart() {
  if (client) {
    try {
      await client.destroy();
    } catch (e) {
      // ignore
    }
  }
  connectionStatus = 'disconnected';
  clientInfo = null;
  qrCodeData = null;
  await initWhatsApp();
}

module.exports = {
  initWhatsApp,
  sendMessage,
  getStatus,
  disconnect,
  restart,
};
