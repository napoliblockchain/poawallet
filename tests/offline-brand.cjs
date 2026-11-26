'use strict';

const assert = require('node:assert/strict');
const crypto = require('node:crypto');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const web = path.resolve(__dirname, '../web');
const html = fs.readFileSync(path.join(web, 'offline.html'), 'utf8');
let checks = 0;
function check(condition, label) {
  assert.ok(condition, label);
  console.log(`PASS: ${label}`);
  checks += 1;
}
check(/<img\b[^>]*src="\/brand\/icon\.svg"[^>]*alt="Napay(?: Wallet)?"/.test(html), 'offline page uses the current accessible brand icon');
check(!/\/css\/images\/logo\.png|\/bundles\/site\/images\/logo\.png|MegaPay/i.test(html), 'no old logo path or old product title');
check(/<title>Napay(?: Wallet)?<\/title>/.test(html), 'current product title');
check((html.match(/<div\b/g) || []).length === (html.match(/<\/div>/g) || []).length, 'offline wrapper markup is balanced');
check(fs.existsSync(path.join(web, 'brand/icon.svg')), 'brand asset exists locally');

const wallet = fs.existsSync(path.join(web, 'service-worker.js'));
const filename = wallet ? 'service-worker.js' : 'sw.js';
const source = fs.readFileSync(path.join(web, filename), 'utf8');
let precache;
const context = {
  importScripts() {},
  self: { addEventListener() {} },
  workbox: {
    setConfig() {},
    precaching: { cleanupOutdatedCaches() {}, precacheAndRoute(entries) { precache = entries; } },
    routing: { registerRoute() {} },
    strategies: { NetworkOnly: class {} },
  },
};
vm.runInNewContext(source, context, { filename });
if (wallet) {
  check(context.STATIC_FILES.includes('offline.html') && context.STATIC_FILES.includes('brand/icon.svg'), 'wallet precaches both offline page and current icon');
  check(context.CACHE_STATIC_NAME === 'poawallet-static-026' && context.CACHE_DYNAMIC_NAME === 'poawallet-dynamic-026', 'wallet cache version invalidates the previous offline page');
} else {
  check(Array.isArray(precache), 'generated Workbox manifest exists');
  for (const entry of precache) {
    const file = fs.readFileSync(path.join(web, entry.url));
    check(crypto.createHash('md5').update(file).digest('hex') === entry.revision, `precache revision matches ${entry.url}`);
  }
  check(precache.some(entry => entry.url === 'offline.html') && precache.some(entry => entry.url === 'brand/icon.svg'), 'Napay precaches both offline page and current icon');
  check(!precache.some(entry => entry.url === 'bundles/site/images/logo.png'), 'unused old logo removed from precache');
}
console.log(`PASS ${checks} offline brand checks`);
