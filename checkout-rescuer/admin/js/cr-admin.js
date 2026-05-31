/**
 * Checkout Rescuer - Admin SPA
 * Premium Silicon Valley Dashboard
 */
(function($) {
'use strict';

var CR = {
  currentPage: 'dashboard',
  waPolling: null,

  init: function() {
    this.currentPage = this.detectPage();
    this.render();
    this.startWaPolling();
  },

  detectPage: function() {
    var page = crAdmin.page || 'checkout-rescuer';
    if (page === 'checkout-rescuer-settings') return 'settings';
    return 'dashboard';
  },

  render: function() {
    var html = this.buildLayout();
    $('#cr-app').html(html);
    this.bindNav();
    this.navigateTo(this.currentPage);
  },


  buildLayout: function() {
    return '<div class="cr-layout">' +
      '<div class="cr-topnav">' +
        '<div class="cr-topnav-brand"><span class="cr-brand-icon">&#x1F4F1;</span><h2>Checkout Rescuer</h2></div>' +
        '<a class="cr-nav-item active" data-page="dashboard"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>Dashboard</a>' +
        '<a class="cr-nav-item" data-page="carts"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61L23 6H6"/></svg>Carts</a>' +
        '<a class="cr-nav-item" data-page="messages"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>Messages</a>' +
        '<a class="cr-nav-item" data-page="whatsapp"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/></svg>WhatsApp</a>' +
        '<a class="cr-nav-item" data-page="settings"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68 1.65 1.65 0 0010 3.17V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9c.17.59.56 1.08 1.08 1.24h.09a2 2 0 010 4h-.09c-.59.17-1.08.56-1.08 1.08z"/></svg>Settings</a>' +
      '</div>' +
      '<main class="cr-main" id="cr-content"></main>' +
    '</div>';
  },


  bindNav: function() {
    var self = this;
    $(document).on('click', '.cr-nav-item', function(e) {
      e.preventDefault();
      var page = $(this).data('page');
      self.navigateTo(page);
    });
  },

  navigateTo: function(page) {
    this.currentPage = page;
    $('.cr-nav-item').removeClass('active');
    $('.cr-nav-item[data-page="' + page + '"]').addClass('active');

    switch(page) {
      case 'dashboard': this.renderDashboard(); break;
      case 'carts': this.renderCarts(); break;
      case 'messages': this.renderMessages(); break;
      case 'whatsapp': this.renderWhatsApp(); break;
      case 'settings': this.renderSettings(); break;
    }
  },

  // === DASHBOARD ===
  renderDashboard: function() {
    var $c = $('#cr-content');
    $c.html(
      '<div class="cr-page-header"><div><h1 class="cr-page-title">Dashboard</h1><p class="cr-page-subtitle">Your cart recovery performance</p></div>' +
      '<select class="cr-period-select" id="cr-period"><option value="7days">Last 7 Days</option><option value="30days" selected>Last 30 Days</option><option value="90days">Last 90 Days</option><option value="all">All Time</option></select></div>' +
      '<div id="cr-wa-bar"></div>' +
      '<div class="cr-stats-grid" id="cr-stats">Loading...</div>' +
      '<div id="cr-live-bar"></div>' +
      '<div class="cr-card"><div class="cr-card-header"><h3>Recovery Trend</h3></div><div class="cr-card-body"><div class="cr-chart-container"><canvas id="cr-chart"></canvas></div></div></div>' +
      '<div class="cr-two-col" id="cr-dashboard-tables"></div>'
    );

    this.loadDashboard('30days');
    var self = this;
    $('#cr-period').on('change', function() { self.loadDashboard($(this).val()); });
  },

  loadDashboard: function(period) {
    var self = this;
    $.post(crAdmin.ajaxUrl, { action: 'cr_get_dashboard', nonce: crAdmin.nonce, period: period }, function(r) {
      if (!r.success) return;
      var d = r.data;
      self.renderStats(d.summary);
      self.renderLiveBar(d.live);
      self.renderChart(d.chart);
      self.renderWaBar(d.whatsapp);
    });
  },


  renderStats: function(s) {
    if (!s) s = {};
    var cur = crAdmin.currency;
    $('#cr-stats').html(
      '<div class="cr-stat-card cr-stat-revenue"><div class="cr-stat-label">Revenue Recovered</div><div class="cr-stat-value">' + cur + this.fmt(s.revenue_recovered||0) + '</div></div>' +
      '<div class="cr-stat-card cr-stat-rate"><div class="cr-stat-label">Recovery Rate</div><div class="cr-stat-value">' + (s.recovery_rate||0) + '%</div></div>' +
      '<div class="cr-stat-card cr-stat-carts"><div class="cr-stat-label">Carts Recovered</div><div class="cr-stat-value">' + (s.recovered||0) + '</div></div>' +
      '<div class="cr-stat-card cr-stat-messages"><div class="cr-stat-label">Messages Sent</div><div class="cr-stat-value">' + (s.messages_sent||0) + '</div></div>'
    );
  },

  renderLiveBar: function(l) {
    if (!l) return;
    var cur = crAdmin.currency;
    $('#cr-live-bar').html(
      '<div class="cr-live-bar">' +
        '<div class="cr-live-item"><span class="cr-live-dot cr-live-dot-green"></span>' + (l.active_carts||0) + ' active carts</div>' +
        '<div class="cr-live-item"><span class="cr-live-dot cr-live-dot-yellow"></span>' + (l.abandoned_carts||0) + ' awaiting recovery</div>' +
        '<div class="cr-live-item"><span class="cr-live-dot cr-live-dot-red"></span>' + cur + this.fmt(l.pending_value||0) + ' recoverable</div>' +
      '</div>'
    );
  },

  renderWaBar: function(wa) {
    if (!wa) { $('#cr-wa-bar').html(''); return; }
    var dotClass = wa.status === 'connected' ? 'connected' : (wa.status === 'qr_ready' || wa.status === 'connecting') ? 'connecting' : 'disconnected';
    var label = wa.status === 'connected' ? 'WhatsApp Connected' + (wa.info ? ' (' + this.esc(wa.info.phone) + ')' : '') : wa.status === 'qr_ready' ? 'Scan QR Code to connect' : 'WhatsApp Disconnected';
    var sub = wa.status === 'connected' ? 'Messages are being delivered' : 'Go to WhatsApp tab to connect';

    $('#cr-wa-bar').html(
      '<div class="cr-wa-status"><span class="cr-wa-dot ' + dotClass + '"></span><div class="cr-wa-info"><strong>' + label + '</strong><span>' + sub + '</span></div></div>'
    );
  },


  renderChart: function(data) {
    var canvas = document.getElementById('cr-chart');
    if (!canvas) return;
    var ctx = canvas.getContext('2d');
    canvas.width = canvas.offsetWidth * 2;
    canvas.height = 520;

    ctx.clearRect(0, 0, canvas.width, canvas.height);

    if (!data || !data.labels || !data.labels.length) {
      ctx.font = '14px Inter, sans-serif';
      ctx.fillStyle = '#64748b';
      ctx.textAlign = 'center';
      ctx.fillText('Chart data will appear once carts are tracked.', canvas.width/2, canvas.height/2);
      return;
    }

    var w = canvas.width, h = canvas.height;
    var pad = { top: 30, right: 30, bottom: 50, left: 60 };
    var cw = w - pad.left - pad.right;
    var ch = h - pad.top - pad.bottom;
    var max = Math.max.apply(null, data.revenue.concat([100]));
    var step = cw / (data.labels.length - 1 || 1);

    // Grid
    ctx.strokeStyle = '#e2e8f0'; ctx.lineWidth = 1;
    for (var i = 0; i <= 4; i++) {
      var y = pad.top + (ch/4)*i;
      ctx.beginPath(); ctx.moveTo(pad.left, y); ctx.lineTo(w-pad.right, y); ctx.stroke();
    }

    // Area fill
    ctx.beginPath();
    ctx.moveTo(pad.left, pad.top + ch);
    for (var j = 0; j < data.revenue.length; j++) {
      ctx.lineTo(pad.left + step*j, pad.top + ch - (data.revenue[j]/max*ch));
    }
    ctx.lineTo(pad.left + step*(data.revenue.length-1), pad.top + ch);
    ctx.closePath();
    var grad = ctx.createLinearGradient(0, pad.top, 0, pad.top+ch);
    grad.addColorStop(0, 'rgba(16,185,129,0.2)');
    grad.addColorStop(1, 'rgba(16,185,129,0.01)');
    ctx.fillStyle = grad; ctx.fill();

    // Line
    ctx.beginPath(); ctx.strokeStyle = '#10b981'; ctx.lineWidth = 3;
    for (var k = 0; k < data.revenue.length; k++) {
      var xk = pad.left + step*k, yk = pad.top + ch - (data.revenue[k]/max*ch);
      k === 0 ? ctx.moveTo(xk, yk) : ctx.lineTo(xk, yk);
    }
    ctx.stroke();

    // Dots
    ctx.fillStyle = '#10b981';
    for (var d = 0; d < data.revenue.length; d++) {
      ctx.beginPath();
      ctx.arc(pad.left + step*d, pad.top + ch - (data.revenue[d]/max*ch), 4, 0, Math.PI*2);
      ctx.fill();
    }

    // Labels
    ctx.font = '20px Inter'; ctx.fillStyle = '#64748b'; ctx.textAlign = 'center';
    var interval = Math.max(1, Math.ceil(data.labels.length / 8));
    for (var l = 0; l < data.labels.length; l += interval) {
      ctx.fillText(data.labels[l], pad.left + step*l, h - 15);
    }
  },


  // === CARTS PAGE ===
  renderCarts: function() {
    var $c = $('#cr-content');
    $c.html(
      '<div class="cr-page-header"><div><h1 class="cr-page-title">Abandoned Carts</h1><p class="cr-page-subtitle">Manage and recover abandoned carts</p></div></div>' +
      '<div class="cr-filters"><div class="cr-filter-tabs" id="cr-cart-tabs">' +
        '<button class="cr-tab active" data-status="abandoned">Abandoned</button>' +
        '<button class="cr-tab" data-status="recovered">Recovered</button>' +
        '<button class="cr-tab" data-status="active">Active</button>' +
        '<button class="cr-tab" data-status="all">All</button>' +
      '</div></div>' +
      '<div class="cr-card"><div class="cr-card-body-flush" id="cr-carts-table"></div></div>'
    );

    var self = this;
    this.loadCarts('abandoned');
    $(document).on('click', '#cr-cart-tabs .cr-tab', function() {
      $('#cr-cart-tabs .cr-tab').removeClass('active');
      $(this).addClass('active');
      self.loadCarts($(this).data('status'));
    });
  },

  loadCarts: function(status) {
    var self = this;
    $.post(crAdmin.ajaxUrl, { action: 'cr_get_carts', nonce: crAdmin.nonce, status: status }, function(r) {
      if (!r.success || !r.data) { $('#cr-carts-table').html('<div class="cr-empty"><h3>No carts found</h3></div>'); return; }
      var items = r.data.items || [];
      if (!items.length) { $('#cr-carts-table').html('<div class="cr-empty"><h3>No carts in this status</h3><p>Abandoned carts will appear here once detected.</p></div>'); return; }

      var html = '<table class="cr-table"><thead><tr><th>Customer</th><th>Value</th><th>Items</th><th>Messages</th><th>Status</th><th>Actions</th></tr></thead><tbody>';
      for (var i = 0; i < items.length; i++) {
        var c = items[i];
        var itemCount = (c.cart_contents || []).length;
        var badge = self.statusBadge(c.status);
        html += '<tr><td><strong>' + self.esc(c.customer_name || 'Guest') + '</strong><br><small style="color:var(--cr-text-muted)">' + self.esc(c.customer_email||'') + '</small></td>';
        html += '<td><strong>' + crAdmin.currency + parseFloat(c.cart_total).toFixed(2) + '</strong></td>';
        html += '<td>' + itemCount + ' items</td>';
        html += '<td><span class="cr-badge cr-badge-info">' + c.messages_sent + ' sent</span></td>';
        html += '<td>' + badge + '</td>';
        html += '<td><button class="cr-btn cr-btn-sm cr-btn-primary cr-resend" data-id="' + c.id + '">Resend</button> ';
        html += '<button class="cr-btn cr-btn-sm cr-btn-danger cr-del-cart" data-id="' + c.id + '">Delete</button></td></tr>';
      }
      html += '</tbody></table>';
      $('#cr-carts-table').html(html);
    });
  },


  // === MESSAGES PAGE ===
  renderMessages: function() {
    var $c = $('#cr-content');
    $c.html(
      '<div class="cr-page-header"><div><h1 class="cr-page-title">Messages</h1><p class="cr-page-subtitle">Message delivery log</p></div></div>' +
      '<div class="cr-stats-grid" id="cr-msg-stats"></div>' +
      '<div class="cr-card"><div class="cr-card-body-flush" id="cr-msg-table"></div></div>'
    );
    this.loadMessages();
  },

  loadMessages: function() {
    var self = this;
    $.post(crAdmin.ajaxUrl, { action: 'cr_get_messages', nonce: crAdmin.nonce }, function(r) {
      if (!r.success) return;
      var stats = r.data.stats || {};
      var msgs = (r.data.messages && r.data.messages.items) || [];

      $('#cr-msg-stats').html(
        '<div class="cr-stat-card"><div class="cr-stat-label">Sent</div><div class="cr-stat-value">' + (stats.sent||0) + '</div></div>' +
        '<div class="cr-stat-card"><div class="cr-stat-label">Delivered</div><div class="cr-stat-value">' + (stats.delivered||0) + '</div></div>' +
        '<div class="cr-stat-card"><div class="cr-stat-label">Read</div><div class="cr-stat-value">' + (stats.read_count||0) + '</div></div>' +
        '<div class="cr-stat-card"><div class="cr-stat-label">Failed</div><div class="cr-stat-value" style="color:var(--cr-danger)">' + (stats.failed||0) + '</div></div>'
      );

      if (!msgs.length) { $('#cr-msg-table').html('<div class="cr-empty"><h3>No messages yet</h3><p>Recovery messages will appear here once sent.</p></div>'); return; }

      var html = '<table class="cr-table"><thead><tr><th>Customer</th><th>Channel</th><th>Step</th><th>Status</th><th>Sent</th></tr></thead><tbody>';
      for (var i = 0; i < msgs.length; i++) {
        var m = msgs[i];
        html += '<tr><td>' + self.esc(m.customer_name||m.customer_email||'Unknown') + '</td>';
        html += '<td><span class="cr-badge cr-badge-whatsapp">WhatsApp</span></td>';
        html += '<td>#' + m.step_number + '</td>';
        html += '<td>' + self.msgBadge(m.status) + '</td>';
        html += '<td>' + (m.sent_at ? self.timeAgo(m.sent_at) : '—') + '</td></tr>';
      }
      html += '</tbody></table>';
      $('#cr-msg-table').html(html);
    });
  },


  // === WHATSAPP PAGE ===
  renderWhatsApp: function() {
    var $c = $('#cr-content');
    $c.html(
      '<div class="cr-page-header"><div><h1 class="cr-page-title">WhatsApp Connection</h1><p class="cr-page-subtitle">Connect your WhatsApp Business account via QR code</p></div></div>' +
      '<div class="cr-card" id="cr-wa-card"><div class="cr-card-body" style="text-align:center;padding:40px;"><div class="cr-loader"></div><p>Loading WhatsApp status...</p></div></div>' +
      '<div class="cr-card"><div class="cr-card-header"><h3>Send Test Message</h3></div><div class="cr-card-body">' +
        '<div class="cr-field"><label class="cr-label">Phone Number (with country code)</label><input type="text" id="cr-test-phone" class="cr-input" placeholder="+919876543210" style="max-width:300px"></div>' +
        '<button class="cr-btn cr-btn-primary" id="cr-test-btn">Send Test</button>' +
        '<div id="cr-test-result" style="margin-top:12px"></div>' +
      '</div></div>'
    );

    this.pollWhatsApp();
    var self = this;

    $(document).off('click', '#cr-test-btn').on('click', '#cr-test-btn', function() {
      var phone = $('#cr-test-phone').val();
      if (!phone) { self.toast('Enter phone number', 'error'); return; }
      $(this).prop('disabled', true).text('Sending...');
      $.post(crAdmin.ajaxUrl, { action: 'cr_test_message', nonce: crAdmin.nonce, phone: phone }, function(r) {
        $('#cr-test-btn').prop('disabled', false).text('Send Test');
        if (r.success) { $('#cr-test-result').html('<span style="color:var(--cr-success)">&#x2705; ' + self.esc(r.data) + '</span>'); }
        else { $('#cr-test-result').html('<span style="color:var(--cr-danger)">&#x274C; ' + self.esc(r.data||'Failed') + '</span>'); }
      });
    });

    $(document).off('click', '#cr-wa-disconnect').on('click', '#cr-wa-disconnect', function() {
      $.post(crAdmin.ajaxUrl, { action: 'cr_disconnect_whatsapp', nonce: crAdmin.nonce }, function() {
        self.toast('Disconnected', 'success');
        self.pollWhatsApp();
      });
    });

    $(document).off('click', '#cr-wa-restart').on('click', '#cr-wa-restart', function() {
      $.post(crAdmin.ajaxUrl, { action: 'cr_restart_whatsapp', nonce: crAdmin.nonce }, function() {
        self.toast('Restarting...', 'success');
        setTimeout(function() { self.pollWhatsApp(); }, 3000);
      });
    });
  },

  pollWhatsApp: function() {
    var self = this;
    $.post(crAdmin.ajaxUrl, { action: 'cr_get_whatsapp_status', nonce: crAdmin.nonce }, function(r) {
      if (!r.success || !r.data) {
        $('#cr-wa-card .cr-card-body').html('<div class="cr-empty"><h3>Backend not connected</h3><p>Please configure your backend URL in Settings first.</p></div>');
        return;
      }
      var wa = r.data;
      var html = '';

      if (wa.status === 'connected') {
        html = '<div style="text-align:center;padding:40px">' +
          '<div style="font-size:64px;margin-bottom:16px">&#x2705;</div>' +
          '<h2 style="color:var(--cr-success);margin-bottom:8px">WhatsApp Connected!</h2>' +
          '<p style="color:var(--cr-text-muted);margin-bottom:8px">Logged in as: <strong>' + (wa.info ? self.esc(wa.info.name) + ' (' + self.esc(wa.info.phone) + ')' : 'Unknown') + '</strong></p>' +
          '<p style="color:var(--cr-text-muted);margin-bottom:24px">Messages are being sent automatically to abandoned cart customers.</p>' +
          '<button class="cr-btn cr-btn-danger" id="cr-wa-disconnect">Disconnect</button>' +
        '</div>';
      } else if (wa.status === 'qr_ready' && wa.qrCode) {
        html = '<div class="cr-qr-container">' +
          '<h3 style="margin-bottom:16px">Scan this QR code with WhatsApp</h3>' +
          '<img src="' + wa.qrCode + '" alt="QR Code" style="max-width:260px;border-radius:12px;background:#fff;padding:16px">' +
          '<p class="cr-qr-instruction">Open WhatsApp > Settings > Linked Devices > Link a Device</p>' +
        '</div>';
        // Auto-refresh
        setTimeout(function() { if (self.currentPage === 'whatsapp') self.pollWhatsApp(); }, 5000);
      } else {
        html = '<div style="text-align:center;padding:40px">' +
          '<div style="font-size:64px;margin-bottom:16px">&#x1F4F1;</div>' +
          '<h3 style="margin-bottom:8px">WhatsApp Disconnected</h3>' +
          '<p style="color:var(--cr-text-muted);margin-bottom:24px">Click restart to generate a new QR code.</p>' +
          '<button class="cr-btn cr-btn-primary" id="cr-wa-restart">Restart Connection</button>' +
        '</div>';
      }

      $('#cr-wa-card .cr-card-body').html(html);
    });
  },


  // === SETTINGS PAGE ===
  renderSettings: function() {
    var s = crAdmin.settings || {};
    var $c = $('#cr-content');
    $c.html(
      '<div class="cr-page-header"><div><h1 class="cr-page-title">Settings</h1><p class="cr-page-subtitle">Configure Checkout Rescuer</p></div>' +
      '<button class="cr-btn cr-btn-primary" id="cr-save-settings">Save Settings</button></div>' +
      '<div class="cr-settings-grid">' +
        '<div class="cr-settings-section"><h3>Connection</h3>' +
          '<div class="cr-field"><label class="cr-label">Backend URL (Hugging Face)</label><input type="text" class="cr-input" name="backend_url" value="' + this.esc(s.backend_url||'') + '" placeholder="https://your-space.hf.space"></div>' +
          '<div class="cr-field"><label class="cr-label">API Secret Key</label><input type="text" class="cr-input" name="api_key" value="' + this.esc(s.api_key||'') + '" placeholder="your-secret-key"></div>' +
          '<div class="cr-field"><label class="cr-label">Store Name</label><input type="text" class="cr-input" name="store_name" value="' + this.esc(s.store_name||'') + '"></div>' +
          '<div class="cr-field"><label class="cr-label">Default Country Code</label><input type="text" class="cr-input" name="country_code" value="' + this.esc(s.country_code||'+91') + '" style="max-width:120px"></div>' +
          '<div class="cr-field"><label class="cr-label">Recovery Base URL</label><input type="text" class="cr-input" name="recovery_base_url" value="' + this.esc(s.recovery_base_url || s.backend_url || '') + '" placeholder="https://your-space.hf.space"><p class="cr-help">URL used in recovery links sent to customers. Usually same as Backend URL.</p></div>' +
        '</div>' +
        '<div class="cr-settings-section"><h3>Cart Recovery</h3>' +
          '<div class="cr-field"><label class="cr-label">Enable Recovery</label><label class="cr-toggle"><input type="checkbox" name="enabled" ' + (s.enabled!=='no'?'checked':'') + '><span class="cr-toggle-slider"></span></label></div>' +
          '<div class="cr-field"><label class="cr-label">Abandonment Timeout (minutes)</label><input type="number" class="cr-input" name="abandonment_timeout" value="' + (s.abandonment_timeout||30) + '" min="5" style="max-width:120px"><p class="cr-help">Time after last activity before cart is abandoned</p></div>' +
          '<div class="cr-field"><label class="cr-label">Max Messages per Cart</label><select class="cr-select" name="max_messages" style="max-width:120px"><option value="1" ' + ((s.max_messages||2)==1?'selected':'') + '>1</option><option value="2" ' + ((s.max_messages||2)==2?'selected':'') + '>2</option></select></div>' +
          '<div class="cr-field"><label class="cr-label">1st Message Delay (min)</label><input type="number" class="cr-input" name="message_1_delay" value="' + (s.message_1_delay||30) + '" style="max-width:120px"></div>' +
          '<div class="cr-field"><label class="cr-label">2nd Message Delay (min)</label><input type="number" class="cr-input" name="message_2_delay" value="' + (s.message_2_delay||1440) + '" style="max-width:120px"><p class="cr-help">1440 = 24 hours</p></div>' +
        '</div>' +
        '<div class="cr-settings-section"><h3>Discount</h3>' +
          '<div class="cr-field"><label class="cr-label">Enable Auto-Discount</label><label class="cr-toggle"><input type="checkbox" name="enable_discount" ' + (s.enable_discount!=='no'?'checked':'') + '><span class="cr-toggle-slider"></span></label></div>' +
          '<div class="cr-field"><label class="cr-label">Discount Type</label><select class="cr-select" name="discount_type" style="max-width:180px"><option value="percent" ' + ((s.discount_type||'percent')==='percent'?'selected':'') + '>Percentage</option><option value="fixed" ' + ((s.discount_type||'percent')==='fixed'?'selected':'') + '>Fixed Amount</option></select></div>' +
          '<div class="cr-field"><label class="cr-label">Discount Amount</label><input type="number" class="cr-input" name="discount_amount" value="' + (s.discount_amount||10) + '" style="max-width:120px"></div>' +
          '<div class="cr-field"><label class="cr-label">Send Discount on Message #</label><select class="cr-select" name="discount_message_step" style="max-width:180px"><option value="1" ' + ((s.discount_message_step||2)==1?'selected':'') + '>1st Message</option><option value="2" ' + ((s.discount_message_step||2)==2?'selected':'') + '>2nd Message</option></select></div>' +
        '</div>' +
        '<div class="cr-settings-section"><h3>Consent & Privacy</h3>' +
          '<div class="cr-field"><label class="cr-label">Require Consent</label><label class="cr-toggle"><input type="checkbox" name="require_consent" ' + (s.require_consent!=='no'?'checked':'') + '><span class="cr-toggle-slider"></span></label><p class="cr-help">GDPR compliance recommended</p></div>' +
          '<div class="cr-field"><label class="cr-label">Consent Text</label><input type="text" class="cr-input" name="consent_text" value="' + this.esc(s.consent_text||'') + '"></div>' +
        '</div>' +
      '</div>' +
      '<div class="cr-card" style="margin-top:24px"><div class="cr-card-header"><h3>Message Templates</h3></div><div class="cr-card-body"><div class="cr-two-col">' +
        '<div class="cr-field"><label class="cr-label">Message #1 (Initial)</label><textarea class="cr-textarea" name="message_template_1" rows="6">' + this.esc(s.message_template_1||'') + '</textarea><p class="cr-help">Variables: {{customer_name}} {{store_name}} {{cart_total}} {{cart_items}} {{recovery_link}}</p></div>' +
        '<div class="cr-field"><label class="cr-label">Message #2 (With Discount)</label><textarea class="cr-textarea" name="message_template_2" rows="6">' + this.esc(s.message_template_2||'') + '</textarea><p class="cr-help">Additional: {{coupon_code}} {{discount_amount}}</p></div>' +
      '</div></div></div>'
    );

    var self = this;
    $('#cr-save-settings').on('click', function() { self.saveSettings($(this)); });
  },

  saveSettings: function($btn) {
    var settings = {};
    $('#cr-content').find('input[name], select[name], textarea[name]').each(function() {
      var $el = $(this), name = $el.attr('name');
      if ($el.attr('type') === 'checkbox') settings[name] = $el.is(':checked') ? 'yes' : 'no';
      else settings[name] = $el.val();
    });

    $btn.text('Saving...').prop('disabled', true);
    $.post(crAdmin.ajaxUrl, { action: 'cr_save_settings', nonce: crAdmin.nonce, settings: settings }, function(r) {
      $btn.text('Save Settings').prop('disabled', false);
      if (r.success) { CR.toast('Settings saved!', 'success'); crAdmin.settings = settings; }
      else CR.toast(r.data || 'Error', 'error');
    });
  },


  // === EVENT BINDINGS ===
  startWaPolling: function() {
    var self = this;
    $(document).off('click', '.cr-resend').on('click', '.cr-resend', function() {
      var id = $(this).data('id');
      $(this).text('Sending...').prop('disabled', true);
      $.post(crAdmin.ajaxUrl, { action: 'cr_resend_message', nonce: crAdmin.nonce, cart_id: id }, function(r) {
        if (r.success) CR.toast('Message sent!', 'success');
        else CR.toast(r.data || 'Failed', 'error');
        self.loadCarts($('#cr-cart-tabs .cr-tab.active').data('status') || 'abandoned');
      });
    });

    $(document).off('click', '.cr-del-cart').on('click', '.cr-del-cart', function() {
      if (!confirm('Delete this cart?')) return;
      var id = $(this).data('id');
      $.post(crAdmin.ajaxUrl, { action: 'cr_delete_cart', nonce: crAdmin.nonce, cart_id: id }, function() {
        CR.toast('Deleted', 'success');
        self.loadCarts($('#cr-cart-tabs .cr-tab.active').data('status') || 'abandoned');
      });
    });
  },

  // === UTILITIES ===
  fmt: function(n) { return parseFloat(n||0).toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2}); },
  esc: function(s) { return $('<div>').text(s||'').html(); },

  timeAgo: function(dateStr) {
    var diff = (Date.now() - new Date(dateStr).getTime()) / 1000;
    if (diff < 60) return 'Just now';
    if (diff < 3600) return Math.floor(diff/60) + 'm ago';
    if (diff < 86400) return Math.floor(diff/3600) + 'h ago';
    return Math.floor(diff/86400) + 'd ago';
  },

  statusBadge: function(status) {
    var map = { abandoned:'warning', recovered:'success', active:'info', converted:'neutral' };
    return '<span class="cr-badge cr-badge-' + (map[status]||'neutral') + '">' + (status||'unknown').charAt(0).toUpperCase() + (status||'').slice(1) + '</span>';
  },

  msgBadge: function(status) {
    var map = { sent:'info', delivered:'success', read:'success', failed:'danger', queued:'neutral' };
    return '<span class="cr-badge cr-badge-' + (map[status]||'neutral') + '">' + (status||'unknown').charAt(0).toUpperCase() + (status||'').slice(1) + '</span>';
  },

  toast: function(msg, type) {
    var $t = $('<div class="cr-toast cr-toast-' + type + '">' + msg + '</div>');
    $('body').append($t);
    setTimeout(function() { $t.fadeOut(300, function(){ $t.remove(); }); }, 3500);
  }
};

$(document).ready(function() { CR.init(); });
})(jQuery);
// END OF FILE
