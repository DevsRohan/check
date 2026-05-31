FROM node:18-slim

# Install chromium + build tools for native modules (better-sqlite3)
RUN apt-get update && apt-get install -y \
    chromium \
    python3 \
    make \
    g++ \
    fonts-ipafont-gothic \
    fonts-wqy-zenhei \
    fonts-thai-tlwg \
    fonts-kacst \
    fonts-freefont-ttf \
    --no-install-recommends \
    && rm -rf /var/lib/apt/lists/*

ENV PUPPETEER_EXECUTABLE_PATH=/usr/bin/chromium
ENV PUPPETEER_SKIP_CHROMIUM_DOWNLOAD=true
ENV NODE_ENV=production

WORKDIR /app

# Create required directories first
RUN mkdir -p /app/data /app/.wwebjs_auth /app/.wwebjs_cache

COPY package.json ./
RUN npm install --omit=dev

COPY . .

# Make data dirs writable
RUN chmod -R 777 /app/data /app/.wwebjs_auth /app/.wwebjs_cache

EXPOSE 7860

CMD ["node", "src/index.js"]
