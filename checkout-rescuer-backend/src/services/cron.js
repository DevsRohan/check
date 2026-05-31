/**
 * Cron Jobs - Abandonment detection and message dispatch
 */

const cron = require('node-cron');
const { getDb } = require('../utils/database');
const { sendMessage } = require('./whatsapp');
const { v4: uuidv4 } = require('uuid');

function initCronJobs() {
  // Check for abandoned carts every 5 minutes
  cron.schedule('*/5 * * * *', () => {
    processAbandonedCarts();
  });

  // Send recovery messages every 2 minutes
  cron.schedule('*/2 * * * *', () => {
    sendRecoveryMessages();
  });

  // Aggregate analytics daily at midnight
  cron.schedule('0 0 * * *', () => {
    aggregateAnalytics();
  });

  // Cleanup old data weekly
  cron.schedule('0 3 * * 0', () => {
    cleanupOldData();
  });

  console.log('[CRON] All jobs scheduled');
}

/**
 * Detect and mark abandoned carts
 */
function processAbandonedCarts() {
  try {
    const db = getDb();
    const timeout = parseInt(getSetting(db, 'abandonment_timeout') || '30');
    const enabled = getSetting(db, 'enabled');

    if (enabled !== 'yes') return;

    const thresholdDate = new Date(Date.now() - timeout * 60 * 1000).toISOString();
    const now = new Date().toISOString();

    const carts = db.prepare(`
      SELECT id, cart_total FROM abandoned_carts
      WHERE status = 'active'
      AND customer_phone != ''
      AND consent_given = 1
      AND last_activity < ?
      AND cart_total > 0
      LIMIT 50
    `).all(thresholdDate);

    if (carts.length === 0) return;

    const updateStmt = db.prepare(`
      UPDATE abandoned_carts SET status = 'abandoned', abandoned_at = ?, updated_at = ? WHERE id = ?
    `);

    let totalLost = 0;
    const updateMany = db.transaction(() => {
      for (const cart of carts) {
        updateStmt.run(now, now, cart.id);
        totalLost += cart.cart_total;
      }
    });
    updateMany();

    // Update analytics
    updateDailyAnalytics(db, { carts_abandoned: carts.length, revenue_lost: totalLost });

    console.log(`[CRON] Marked ${carts.length} carts as abandoned (${totalLost} value)`);
  } catch (error) {
    console.error('[CRON] processAbandonedCarts error:', error.message);
  }
}

/**
 * Send recovery messages to abandoned carts
 */
async function sendRecoveryMessages() {
  try {
    const db = getDb();
    const enabled = getSetting(db, 'enabled');
    if (enabled !== 'yes') return;

    const maxMessages = parseInt(getSetting(db, 'max_messages') || '2');

    const carts = db.prepare(`
      SELECT * FROM abandoned_carts
      WHERE status = 'abandoned'
      AND consent_given = 1
      AND messages_sent < ?
      ORDER BY abandoned_at ASC
      LIMIT 10
    `).all(maxMessages);

    if (carts.length === 0) return;

    for (const cart of carts) {
      const nextStep = cart.messages_sent + 1;
      const delayKey = `message_${nextStep}_delay`;
      const delay = parseInt(getSetting(db, delayKey) || '30');

      const abandonedTime = new Date(cart.abandoned_at).getTime();
      const sendAfter = abandonedTime + (delay * 60 * 1000);

      if (Date.now() < sendAfter) continue;

      // Build and send message
      await sendCartRecoveryMessage(db, cart, nextStep);
    }
  } catch (error) {
    console.error('[CRON] sendRecoveryMessages error:', error.message);
  }
}

/**
 * Send a single recovery message
 */
async function sendCartRecoveryMessage(db, cart, step) {
  try {
    // Generate coupon if needed
    let couponCode = '';
    const discountStep = parseInt(getSetting(db, 'discount_message_step') || '2');
    const enableDiscount = getSetting(db, 'enable_discount');

    if (step >= discountStep && enableDiscount === 'yes') {
      const discountType = getSetting(db, 'discount_type') || 'percent';
      const discountAmount = getSetting(db, 'discount_amount') || '10';
      couponCode = 'CR' + uuidv4().substring(0, 6).toUpperCase();

      // Store coupon on cart
      db.prepare('UPDATE abandoned_carts SET coupon_code = ?, updated_at = ? WHERE id = ?')
        .run(couponCode, new Date().toISOString(), cart.id);
    }

    // Build message from template
    const message = buildMessage(db, cart, step, couponCode);

    // Send via WhatsApp
    const result = await sendMessage(cart.customer_phone, message);

    // Log message
    const now = new Date().toISOString();
    db.prepare(`
      INSERT INTO messages (cart_id, channel, phone_number, message_content, message_id, status, step_number, error_message, sent_at, created_at)
      VALUES (?, 'whatsapp', ?, ?, ?, ?, ?, ?, ?, ?)
    `).run(
      cart.id,
      cart.customer_phone,
      message,
      result.success ? result.messageId : '',
      result.success ? 'sent' : 'failed',
      step,
      result.success ? null : result.error,
      result.success ? now : null,
      now
    );

    // Update cart messages_sent
    db.prepare('UPDATE abandoned_carts SET messages_sent = messages_sent + 1, updated_at = ? WHERE id = ?')
      .run(now, cart.id);

    // Update analytics
    updateDailyAnalytics(db, { messages_sent: 1 });

    if (result.success) {
      console.log(`[MSG] Sent step ${step} to ${cart.customer_phone} (cart #${cart.id})`);
    } else {
      console.log(`[MSG] Failed step ${step} to ${cart.customer_phone}: ${result.error}`);
    }
  } catch (error) {
    console.error(`[MSG] Error sending to cart #${cart.id}:`, error.message);
  }
}

/**
 * Build message from template with variable replacement
 */
function buildMessage(db, cart, step, couponCode) {
  const templateKey = `message_template_${step}`;
  let template = getSetting(db, templateKey) || getSetting(db, 'message_template_1') || 'Your cart is waiting! {{recovery_link}}';

  const cartItems = JSON.parse(cart.cart_contents || '[]');
  let itemsText = '';
  for (const item of cartItems) {
    itemsText += `- ${item.name} x${item.quantity}\n`;
  }

  const storeName = getSetting(db, 'store_name') || 'Our Store';
  const baseUrl = getSetting(db, 'recovery_base_url') || `http://localhost:${process.env.PORT || 7860}`;
  const recoveryLink = `${baseUrl}/recover/${cart.recovery_token}`;

  const discountType = getSetting(db, 'discount_type') || 'percent';
  const discountAmount = getSetting(db, 'discount_amount') || '10';
  const discountText = discountType === 'percent' ? `${discountAmount}%` : `${cart.currency}${discountAmount}`;

  const replacements = {
    '{{customer_name}}': cart.customer_name || 'there',
    '{{store_name}}': storeName,
    '{{cart_total}}': `${cart.currency} ${parseFloat(cart.cart_total).toFixed(2)}`,
    '{{cart_items}}': itemsText.trim(),
    '{{recovery_link}}': recoveryLink,
    '{{coupon_code}}': couponCode,
    '{{discount_amount}}': discountText,
    '{{currency}}': cart.currency,
  };

  for (const [key, value] of Object.entries(replacements)) {
    template = template.replace(new RegExp(key.replace(/[{}]/g, '\\$&'), 'g'), value);
  }

  return template;
}

/**
 * Get a setting value
 */
function getSetting(db, key) {
  const row = db.prepare('SELECT value FROM settings WHERE key = ?').get(key);
  return row ? row.value : null;
}

/**
 * Update daily analytics
 */
function updateDailyAnalytics(db, data) {
  const today = new Date().toISOString().split('T')[0];

  const existing = db.prepare('SELECT id FROM analytics WHERE date = ?').get(today);

  if (existing) {
    const sets = [];
    const values = [];
    for (const [key, val] of Object.entries(data)) {
      sets.push(`${key} = ${key} + ?`);
      values.push(val);
    }
    values.push(today);
    db.prepare(`UPDATE analytics SET ${sets.join(', ')} WHERE date = ?`).run(...values);
  } else {
    const cols = ['date', ...Object.keys(data)];
    const vals = [today, ...Object.values(data)];
    const placeholders = cols.map(() => '?').join(', ');
    db.prepare(`INSERT INTO analytics (${cols.join(', ')}) VALUES (${placeholders})`).run(...vals);
  }
}

/**
 * Aggregate analytics
 */
function aggregateAnalytics() {
  try {
    const db = getDb();
    const today = new Date().toISOString().split('T')[0];

    const row = db.prepare('SELECT carts_abandoned, carts_recovered FROM analytics WHERE date = ?').get(today);
    if (row && row.carts_abandoned > 0) {
      const rate = ((row.carts_recovered / row.carts_abandoned) * 100).toFixed(2);
      db.prepare('UPDATE analytics SET recovery_rate = ? WHERE date = ?').run(rate, today);
    }
  } catch (error) {
    console.error('[CRON] aggregateAnalytics error:', error.message);
  }
}

/**
 * Cleanup old data (90 days)
 */
function cleanupOldData() {
  try {
    const db = getDb();
    const cutoff = new Date(Date.now() - 90 * 24 * 60 * 60 * 1000).toISOString();

    db.prepare("DELETE FROM abandoned_carts WHERE status = 'converted' AND created_at < ?").run(cutoff);
    db.prepare('DELETE FROM messages WHERE created_at < ?').run(cutoff);

    console.log('[CRON] Old data cleaned up');
  } catch (error) {
    console.error('[CRON] cleanup error:', error.message);
  }
}

module.exports = { initCronJobs, processAbandonedCarts, sendRecoveryMessages };
