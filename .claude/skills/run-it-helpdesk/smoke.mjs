#!/usr/bin/env node
/**
 * IT HelpDesk smoke driver — Chrome CDP, no extra npm packages.
 *
 * Usage:
 *   SANCTUM_TOKEN=<token> node smoke.mjs [screenshot-dir]
 *
 * Env vars:
 *   FRONTEND_URL   default http://localhost:5174
 *   BACKEND_URL    default http://localhost:8000
 *   SANCTUM_TOKEN  Sanctum Bearer token
 *   CHROME_EXE     path to chrome.exe (Windows default provided)
 *
 * Create a token (run once from it-helpdesk-backend/):
 *   php artisan tinker --execute "echo App\Models\User::find(1)->createToken('smoke')->plainTextToken;"
 */

import { spawn } from 'child_process';
import http  from 'http';
import https from 'https';
import net   from 'net';
import fs    from 'fs';
import path  from 'path';

const FRONTEND_URL   = process.env.FRONTEND_URL   || 'http://localhost:5174';
const BACKEND_URL    = process.env.BACKEND_URL    || 'http://localhost:8000';
const TOKEN          = process.env.SANCTUM_TOKEN  || '';
const SCREENSHOT_DIR = process.argv[2] || '/tmp/helpdesk-screenshots';
const CHROME_EXE     = process.env.CHROME_EXE    || 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const DEBUG_PORT     = 9222;

// ── Utilities ─────────────────────────────────────────────────────────────────

const sleep = ms => new Promise(r => setTimeout(r, ms));

function httpGet(url, headers = {}) {
  return new Promise((resolve, reject) => {
    const mod  = url.startsWith('https') ? https : http;
    const opts = Object.assign(new URL(url), { headers });
    mod.get(opts, res => {
      const chunks = [];
      res.on('data', d => chunks.push(d));
      res.on('end', () => resolve({ status: res.statusCode, body: Buffer.concat(chunks).toString() }));
    }).on('error', reject);
  });
}

// ── Minimal CDP client with proper buffer accumulation ────────────────────────

class CDP {
  constructor(wsUrl) {
    this._wsUrl   = wsUrl;
    this._id      = 0;
    this._pending = new Map();
    this._events  = {};
    this._sock    = null;
    this._buf     = Buffer.alloc(0); // accumulated TCP data
  }

  static async connect(port = 9222, retries = 25) {
    for (let i = 0; i < retries; i++) {
      try {
        const { body } = await httpGet(`http://localhost:${port}/json`);
        const tabs = JSON.parse(body);
        const tab  = tabs.find(t => t.type === 'page') || tabs[0];
        if (tab?.webSocketDebuggerUrl) {
          const c = new CDP(tab.webSocketDebuggerUrl);
          await c._connect();
          return c;
        }
      } catch { /* not ready */ }
      await sleep(400);
    }
    throw new Error(`Chrome CDP not ready on port ${port}`);
  }

  _connect() {
    return new Promise((resolve, reject) => {
      const u    = new URL(this._wsUrl);
      const host = u.hostname;
      const port = parseInt(u.port) || 80;
      const resource = u.pathname + u.search;

      const sock = net.createConnection(port, host, () => {
        const key = Buffer.from(String(Math.random())).toString('base64');
        sock.write(
          `GET ${resource} HTTP/1.1\r\n` +
          `Host: ${host}:${port}\r\n` +
          `Upgrade: websocket\r\nConnection: Upgrade\r\n` +
          `Sec-WebSocket-Key: ${key}\r\nSec-WebSocket-Version: 13\r\n\r\n`
        );
      });
      this._sock = sock;

      let upgraded = false;
      sock.on('data', raw => {
        if (!upgraded) {
          const s = raw.toString('binary');
          if (s.includes('101')) {
            upgraded = true;
            resolve();
            // data after the HTTP headers is WS
            const hdEnd = raw.indexOf(Buffer.from('\r\n\r\n')) + 4;
            if (hdEnd > 4 && hdEnd < raw.length) {
              this._buf = raw.slice(hdEnd);
              this._processFrames();
            }
          }
          return;
        }
        this._buf = Buffer.concat([this._buf, raw]);
        this._processFrames();
      });
      sock.on('error', reject);
      sock.on('close', () => { /* ok */ });
    });
  }

  _processFrames() {
    while (this._buf.length >= 2) {
      const b1     = this._buf[1];
      const masked = !!(b1 & 0x80);
      let   payLen = b1 & 0x7f;
      let   offset = 2;

      if (payLen === 126) {
        if (this._buf.length < 4) return; // need more data
        payLen = this._buf.readUInt16BE(2);
        offset = 4;
      } else if (payLen === 127) {
        if (this._buf.length < 10) return;
        payLen = Number(this._buf.readBigUInt64BE(2));
        offset = 10;
      }

      if (masked) offset += 4;
      if (this._buf.length < offset + payLen) return; // incomplete frame, wait

      const payload = this._buf.slice(offset, offset + payLen).toString('utf8');
      this._buf = this._buf.slice(offset + payLen);

      try {
        const msg = JSON.parse(payload);
        if (msg.id !== undefined) {
          const cb = this._pending.get(msg.id);
          if (cb) { this._pending.delete(msg.id); cb(msg); }
        } else if (msg.method) {
          (this._events[msg.method] || []).forEach(fn => fn(msg.params));
        }
      } catch { /* ignore non-JSON or malformed */ }
    }
  }

  _sendRaw(obj) {
    const payload = Buffer.from(JSON.stringify(obj), 'utf8');
    const mask    = Buffer.from([
      Math.random() * 256 | 0, Math.random() * 256 | 0,
      Math.random() * 256 | 0, Math.random() * 256 | 0,
    ]);
    // XOR payload with mask (RFC 6455 §5.3 — client→server MUST be masked)
    const masked = Buffer.alloc(payload.length);
    for (let i = 0; i < payload.length; i++) masked[i] = payload[i] ^ mask[i % 4];

    let header;
    if (payload.length <= 125) {
      header = Buffer.from([0x81, 0x80 | payload.length]);
    } else if (payload.length <= 0xffff) {
      header = Buffer.alloc(4);
      header[0] = 0x81; header[1] = 0xfe; // 126 | 0x80
      header.writeUInt16BE(payload.length, 2);
    } else {
      header = Buffer.alloc(10);
      header[0] = 0x81; header[1] = 0xff; // 127 | 0x80
      header.writeBigUInt64BE(BigInt(payload.length), 2);
    }
    this._sock.write(Buffer.concat([header, mask, masked]));
  }

  send(method, params = {}) {
    return new Promise((resolve, reject) => {
      const id = ++this._id;
      this._pending.set(id, msg => {
        if (msg.error) reject(new Error(JSON.stringify(msg.error)));
        else resolve(msg.result ?? {});
      });
      this._sendRaw({ id, method, params });
    });
  }

  on(event, fn) {
    (this._events[event] = this._events[event] || []).push(fn);
    return this;
  }

  async navigate(url, waitMs = 2500) {
    await this.send('Page.navigate', { url });
    await sleep(waitMs);
  }

  async screenshot(filepath) {
    const { data } = await this.send('Page.captureScreenshot', { format: 'png' });
    fs.writeFileSync(filepath, Buffer.from(data, 'base64'));
    console.log(`  ✓ ${path.basename(filepath)}`);
    return filepath;
  }

  async eval(expr) {
    const r = await this.send('Runtime.evaluate', { expression: expr, returnByValue: true });
    return r.result?.value;
  }

  close() { try { this._sock?.destroy(); } catch {} }
}

// ── API smoke tests ───────────────────────────────────────────────────────────

async function smokeApi() {
  console.log('\n── API smoke ────────────────────────────────────────────');

  const r401 = await httpGet(`${BACKEND_URL}/api/auth/me`);
  if (r401.status !== 401) throw new Error(`Expected 401, got ${r401.status}`);
  console.log(`  ✓ Unauthenticated → 401`);

  if (!TOKEN) { console.log('  ⚠ SANCTUM_TOKEN not set — skipping auth tests'); return; }

  const rMe = await httpGet(`${BACKEND_URL}/api/auth/me`, { Authorization: `Bearer ${TOKEN}` });
  if (rMe.status !== 200) throw new Error(`/api/auth/me returned ${rMe.status}: ${rMe.body.slice(0, 200)}`);
  const user = JSON.parse(rMe.body);
  console.log(`  ✓ /api/auth/me → ${user.name} (${user.role})`);

  const rDepts = await httpGet(`${BACKEND_URL}/api/departments`, { Authorization: `Bearer ${TOKEN}` });
  console.log(`  ✓ /api/departments → ${JSON.parse(rDepts.body).length} depts`);

  const rTickets = await httpGet(`${BACKEND_URL}/api/tickets`, { Authorization: `Bearer ${TOKEN}` });
  console.log(`  ✓ /api/tickets → total=${JSON.parse(rTickets.body).total}`);

  const rDash = await httpGet(`${BACKEND_URL}/api/dashboard/stats`, { Authorization: `Bearer ${TOKEN}` });
  if (rDash.status !== 200) throw new Error(`/api/dashboard/stats → ${rDash.status}`);
  console.log(`  ✓ /api/dashboard/stats → 200`);

  const rAssets = await httpGet(`${BACKEND_URL}/api/assets`, { Authorization: `Bearer ${TOKEN}` });
  if (rAssets.status !== 200) throw new Error(`/api/assets → ${rAssets.status}`);
  console.log(`  ✓ /api/assets → total=${JSON.parse(rAssets.body).total}`);

  const rAssetMeta = await httpGet(`${BACKEND_URL}/api/assets/meta`, { Authorization: `Bearer ${TOKEN}` });
  if (rAssetMeta.status !== 200) throw new Error(`/api/assets/meta → ${rAssetMeta.status}`);
  console.log(`  ✓ /api/assets/meta → 200`);
}

// ── Screenshot flow ───────────────────────────────────────────────────────────

async function smokeScreenshots() {
  console.log('\n── Screenshots ──────────────────────────────────────────');
  fs.mkdirSync(SCREENSHOT_DIR, { recursive: true });

  const profileDir = path.join(SCREENSHOT_DIR, '.chrome-profile');
  fs.mkdirSync(profileDir, { recursive: true });

  const chrome = spawn(CHROME_EXE, [
    '--headless=new', '--no-sandbox', '--disable-gpu',
    `--remote-debugging-port=${DEBUG_PORT}`,
    `--user-data-dir=${profileDir}`,
    '--window-size=1280,800',
    'about:blank',
  ], { stdio: 'ignore' });

  let cdp;
  try {
    cdp = await CDP.connect(DEBUG_PORT);
    await cdp.send('Page.enable');
    await cdp.send('Runtime.enable');

    // 1. Login page
    await cdp.navigate(`${FRONTEND_URL}/login`, 3500);
    await cdp.screenshot(path.join(SCREENSHOT_DIR, '01-login.png'));

    if (!TOKEN) {
      console.log('  ⚠ Skipping authenticated pages (no SANCTUM_TOKEN)');
      return;
    }

    // 2. Inject token → dashboard
    await cdp.eval(`localStorage.setItem('token', ${JSON.stringify(TOKEN)})`);
    await cdp.navigate(`${FRONTEND_URL}/`, 4000);
    await cdp.screenshot(path.join(SCREENSHOT_DIR, '02-dashboard.png'));

    // 3. Tickets list
    await cdp.navigate(`${FRONTEND_URL}/tickets`, 2500);
    await cdp.screenshot(path.join(SCREENSHOT_DIR, '03-tickets.png'));

    // 4. Create ticket form
    await cdp.navigate(`${FRONTEND_URL}/tickets/create`, 2000);
    await cdp.screenshot(path.join(SCREENSHOT_DIR, '04-create-ticket.png'));

    // 5. Admin users
    await cdp.navigate(`${FRONTEND_URL}/admin/users`, 2000);
    await cdp.screenshot(path.join(SCREENSHOT_DIR, '05-admin-users.png'));

    // 6. Assets list (IT-only)
    await cdp.navigate(`${FRONTEND_URL}/assets`, 2500);
    await cdp.screenshot(path.join(SCREENSHOT_DIR, '06-assets.png'));

    // 7. Asset detail (first seeded asset)
    await cdp.navigate(`${FRONTEND_URL}/assets/1`, 2500);
    await cdp.screenshot(path.join(SCREENSHOT_DIR, '07-asset-detail.png'));

    // 8. Create asset form
    await cdp.navigate(`${FRONTEND_URL}/assets/create`, 2000);
    await cdp.screenshot(path.join(SCREENSHOT_DIR, '08-create-asset.png'));

  } finally {
    cdp?.close();
    await sleep(300);
    chrome.kill();
  }
}

// ── Main ──────────────────────────────────────────────────────────────────────

console.log('IT HelpDesk Smoke Driver');
console.log(`  Frontend : ${FRONTEND_URL}`);
console.log(`  Backend  : ${BACKEND_URL}`);
console.log(`  Output   : ${SCREENSHOT_DIR}`);

await smokeApi();
await smokeScreenshots();
console.log(`\n✓ All checks passed. Screenshots → ${SCREENSHOT_DIR}/`);
