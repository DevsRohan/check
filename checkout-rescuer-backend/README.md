---
title: Checkout Rescuer
emoji: 🛒
colorFrom: green
colorTo: emerald
sdk: docker
pinned: false
---

# Checkout Rescuer - Backend

WhatsApp cart recovery backend for WooCommerce. Deploys on Hugging Face Spaces.

## Quick Deploy to Hugging Face

1. Create a new Space on [huggingface.co/spaces](https://huggingface.co/spaces)
2. Select "Docker" as the SDK
3. Upload all files from this directory
4. Set environment variables in Space settings:
   - `API_SECRET_KEY` = your secret key
   - `PORT` = 7860 (default)

## Local Development

```bash
cp .env.example .env
npm install
npm run dev
```

## How It Works

1. Backend starts and generates WhatsApp QR code
2. Admin scans QR code from WordPress dashboard (WhatsApp tab)
3. WhatsApp connects via whatsapp-web.js
4. WooCommerce plugin sends cart data to backend API
5. Cron jobs detect abandoned carts and send WhatsApp messages automatically
6. Customer clicks recovery link -> redirected to store checkout

## API Endpoints

- `GET /api/health` - Health check
- `GET /api/whatsapp/status` - WhatsApp connection status + QR code
- `POST /api/whatsapp/test` - Send test message
- `POST /api/track/cart` - Track cart from WooCommerce
- `POST /api/track/heartbeat` - Cart activity heartbeat
- `POST /api/track/converted` - Mark cart as converted
- `GET /api/carts` - List abandoned carts
- `GET /api/carts/by-token/:token` - Get cart by recovery token
- `GET /api/messages` - Message log
- `GET /api/analytics/summary` - Dashboard stats
- `GET /api/analytics/chart` - Chart data
- `GET /api/settings` - Get settings
- `POST /api/settings` - Update settings
- `GET /recover/:token` - Recovery link redirect

## Requirements

- Node.js 18+
- Chromium (for whatsapp-web.js puppeteer)
