// Invio token concluso con pushoptions null: niente eccezione, invio tolto dalla coda del browser, notifica solo con titolo.
const fs = require('fs'), vm = require('vm'), path = require('path');
let checks = 0;
const check = (ok, label) => { if (!ok) { console.error('FAIL: ' + label); process.exit(1); } checks++; console.log('PASS: ' + label); };
const src = fs.readFileSync(path.join(__dirname, '../../web/src/js/idb-utility.js'), 'utf8');
function run(pushoptions) {
  const deleted = [], shown = [];
  const el = new Proxy(function () {}, { get: () => () => el, apply: () => el });
  const ctx = { console: { log() {} }, setTimeout() {}, $: () => el, deleted, shown, idb: { open: () => Promise.resolve({}) },
    displayPushNotification: (o) => shown.push(o), deleteItemFromData: (store, id) => deleted.push([store, id]),
    readFromId: () => Promise.resolve([]), checkPendingTransactions() {}, self: {}, window: {} };
  vm.createContext(ctx);
  vm.runInContext(src + '\nthis.erc20 = erc20;', ctx);
  // le funzioni del file che toccano IndexedDB e le notifiche sono sostituite dopo il caricamento
  ctx.displayPushNotification = (o) => shown.push(o);
  ctx.deleteItemFromData = (store, id) => deleted.push([store, id]);
  ctx.erc20.manageData('pending', [{ id: 'abc', status: 'complete', row: '', balance: '1', formatted_balance: '1', pushoptions }], 'abc');
  return { deleted, shown };
}
let r = run(null);
check(r.deleted.length === 1 && r.deleted[0][1] === 'abc' && r.shown.length === 0, 'pushoptions null: sent item removed from the queue, no notification, no exception');
r = run({ title: 'Napay', body: 'x' });
check(r.deleted.length === 1 && r.shown.length === 1, 'pushoptions with title: notification shown and item removed');
r = run({});
check(r.deleted.length === 1 && r.shown.length === 0, 'pushoptions without title: no notification, item removed');
console.log('PASS ' + checks + ' idb-utility pending checks');
