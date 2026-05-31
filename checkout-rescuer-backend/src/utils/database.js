/**
 * SQLite Database Manager
 */

const Database = require('better-sqlite3');
const path = require('path');
const fs = require('fs');

const DB_PATH = process.env.DB_PATH || path.join(__dirname, '../../data/checkout-rescuer.db');

let db = null;

function getDb() {
  if (!db) {
    const dir = path.dirname(DB_PATH);
    if (!fs.existsSync(dir)) {
      fs.mkdirSync(dir, { recursive: true });
    }
    db = new Database(DB_PATH);
    db.pragma('journal_mode = WAL');
    db.pragma('busy_timeout = 5000');
  }
  return db;
}

function initDatabase() {
  const database = getDb();

  database.exec(`
    CREATE TABLE IF NOT EXISTS abandoned_carts (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      session_id TEXT NOT NULL,
      store_url TEXT NOT NULL DEFAULT '',
      customer_name TEXT DEFAULT '',
      customer_email TEXT DEFAULT '',
      customer_phone TEXT NOT NULL,
      cart_contents TEXT NOT NULL DEFAULT '[]',
      cart_total REAL NOT NULL DEFAULT 0,
      currency TEXT NOT NULL DEFAULT 'USD',
      recovery_token TEXT UNIQUE NOT NULL,
      status TEXT NOT NULL DEFAULT 'active',
      messages_sent INTEGER NOT NULL DEFAULT 0,
      consent_given INTEGER NOT NULL DEFAULT 0,
      last_activity TEXT NOT NULL,
      abandoned_at TEXT,
      recovered_at TEXT,
      coupon_code TEXT,
      created_at TEXT NOT NULL,
      updated_at TEXT NOT NULL
    );

    CREATE INDEX IF NOT EXISTS idx_carts_status ON abandoned_carts(status);
    CREATE INDEX IF NOT EXISTS idx_carts_token ON abandoned_carts(recovery_token);
    CREATE INDEX IF NOT EXISTS idx_carts_session ON abandoned_carts(session_id);
    CREATE INDEX IF NOT EXISTS idx_carts_last_activity ON abandoned_carts(last_activity);

    CREATE TABLE IF NOT EXISTS messages (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      cart_id INTEGER NOT NULL,
      channel TEXT NOT NULL DEFAULT 'whatsapp',
      phone_number TEXT NOT NULL,
      message_content TEXT NOT NULL,
      message_id TEXT DEFAULT '',
      status TEXT NOT NULL DEFAULT 'queued',
      step_number INTEGER NOT NULL DEFAULT 1,
      error_message TEXT,
      sent_at TEXT,
      delivered_at TEXT,
      read_at TEXT,
      created_at TEXT NOT NULL,
      FOREIGN KEY (cart_id) REFERENCES abandoned_carts(id)
    );

    CREATE INDEX IF NOT EXISTS idx_messages_cart ON messages(cart_id);
    CREATE INDEX IF NOT EXISTS idx_messages_status ON messages(status);

    CREATE TABLE IF NOT EXISTS recoveries (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      cart_id INTEGER NOT NULL,
      order_id TEXT,
      recovered_amount REAL NOT NULL DEFAULT 0,
      currency TEXT NOT NULL DEFAULT 'USD',
      channel TEXT NOT NULL DEFAULT 'whatsapp',
      coupon_used TEXT,
      discount_amount REAL NOT NULL DEFAULT 0,
      recovered_at TEXT NOT NULL,
      FOREIGN KEY (cart_id) REFERENCES abandoned_carts(id)
    );

    CREATE INDEX IF NOT EXISTS idx_recoveries_cart ON recoveries(cart_id);

    CREATE TABLE IF NOT EXISTS analytics (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      date TEXT NOT NULL UNIQUE,
      carts_abandoned INTEGER NOT NULL DEFAULT 0,
      messages_sent INTEGER NOT NULL DEFAULT 0,
      messages_delivered INTEGER NOT NULL DEFAULT 0,
      carts_recovered INTEGER NOT NULL DEFAULT 0,
      revenue_recovered REAL NOT NULL DEFAULT 0,
      revenue_lost REAL NOT NULL DEFAULT 0,
      recovery_rate REAL NOT NULL DEFAULT 0
    );

    CREATE INDEX IF NOT EXISTS idx_analytics_date ON analytics(date);

    CREATE TABLE IF NOT EXISTS settings (
      key TEXT PRIMARY KEY,
      value TEXT NOT NULL
    );
  `);

  // Insert default settings if not exists
  const defaults = {
    'enabled': 'yes',
    'abandonment_timeout': '30',
    'max_messages': '2',
    'message_1_delay': '30',
    'message_2_delay': '1440',
    'enable_discount': 'yes',
    'discount_type': 'percent',
    'discount_amount': '10',
    'discount_message_step': '2',
    'message_template_1': 'Hey {{customer_name}}! 👋\n\nYou left some items in your cart at {{store_name}}.\n\n🛒 Your cart ({{cart_total}}):\n{{cart_items}}\n\n👉 Complete your order: {{recovery_link}}\n\nNeed help? Just reply!',
    'message_template_2': 'Hi {{customer_name}}! 🎁\n\nYour cart is still waiting! Use code {{coupon_code}} for {{discount_amount}} off.\n\n🛒 Cart: {{cart_total}}\n👉 {{recovery_link}}\n\nOffer expires in 24 hours! ⏰',
    'require_consent': 'yes',
    'country_code': '+91',
    'store_name': 'My Store',
    'recovery_base_url': '',
  };

  const insertSetting = database.prepare('INSERT OR IGNORE INTO settings (key, value) VALUES (?, ?)');
  for (const [key, value] of Object.entries(defaults)) {
    insertSetting.run(key, value);
  }

  return database;
}

module.exports = { getDb, initDatabase };
