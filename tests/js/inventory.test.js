import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';
import { webcrypto } from 'node:crypto';

const source = readFileSync(new URL('../../resources/views/inventory/partials/scripts.blade.php', import.meta.url), 'utf8');
const script = source.match(/<script>\s*\(\(\) => \{([\s\S]*?)\}\)\(\);\s*<\/script>/)?.[1];
assert.ok(script);
const { paginate, shipmentLimit, defaultSerialSelection, postingConfirmation, validateShipment, toCsv, quantityUnits, quantityString, formatQuantity, validQuantity, parseSerials, newRequestKey, movementValues, movementDescription, movementSerials } = runInNewContext(
    `'use strict';\n${script}\n;({ paginate, shipmentLimit, defaultSerialSelection, postingConfirmation, validateShipment, toCsv, quantityUnits, quantityString, formatQuantity, validQuantity, parseSerials, newRequestKey, movementValues, movementDescription, movementSerials });`,
    { document: { getElementById: () => null }, crypto: { getRandomValues: (bytes) => webcrypto.getRandomValues(bytes) } },
    { filename: 'inventory/partials/scripts.blade.php' },
);

const stock = { warehouse_id: 1, qty: '14.000', serials: [] };
const item = { id: 100, product_id: 10, sku: 'TEST-SKU', ordered: '16.000', delivered: '10.000', track_serial: false, stocks: [stock] };
const order = { id: 1, items: [item] };
const selection = { so_item_id: '100', warehouse_id: '1', quantity: '3.000', serial_ids: [] };

test('confirmation keys are valid unique UUIDs even on the local insecure HTTP origin', () => {
    const keys = Array.from({ length: 100 }, () => newRequestKey());
    assert.equal(new Set(keys).size, 100);
    for (const key of keys) assert.match(key, /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/);
});

test('the page script remains self-contained and has no production fixtures or HTML-string rendering', () => {
    assert.doesNotMatch(script, /^\s*import\s/m);
    assert.doesNotMatch(source, /<script[^>]+\btype="module"/);
    assert.doesNotMatch(source, /<script[^>]+\bsrc=|Static inputs|PRD-001|SO-2026-031|innerHTML\s*=/);
    assert.match(script, /JSON\.parse\(app\.querySelector\('#inventory-bootstrap'\)/);
});

test('fixed-point quantities preserve all three decimals and the full database input range', () => {
    assert.equal(quantityUnits('0.001'), 1n);
    assert.equal(quantityUnits('999999999999999.999'), 999999999999999999n);
    assert.equal(quantityUnits('0.1') + quantityUnits('0.2'), quantityUnits('0.3'));
    assert.equal(quantityString(999999999999999999n), '999999999999999.999');
    assert.equal(quantityString(-1n), '-0.001');
    assert.equal(formatQuantity('999999999999999.999'), '999.999.999.999.999,999');
    assert.equal(formatQuantity('0.001'), '0,001');
    assert.equal(formatQuantity('12.500'), '12,5');
    for (const value of ['0', '-1', '0.0001', '1000000000000000', '1e2', 'NaN', '', Infinity]) assert.equal(validQuantity(value), false);
    assert.equal(validQuantity('999999999999999.999'), true);
});

test('local modal pagination retains drafts, clamps pages and supports consistent row sizes', () => {
    const rows = Array.from({ length: 12 }, (_, i) => ({ id: i }));
    assert.equal(paginate(rows, 2, 5).rows.length, 5);
    assert.equal(paginate(rows, 3, 5).start, 11);
    assert.equal(paginate(rows, 999, 5).page, 3);
    assert.equal(paginate([], 999, 5).pages, 1);
    assert.equal(paginate(rows, 1, -5).rows.length, 10);
});

test('dispatch default respects stock, remaining quantity, serial availability and zero balances', () => {
    assert.equal(shipmentLimit(item, stock), '6');
    assert.equal(shipmentLimit(item, { ...stock, qty: '0.001' }), '0.001');
    assert.equal(shipmentLimit(item, undefined), '0');
    assert.equal(shipmentLimit({ ...item, delivered: '16.000' }, stock), '0');
    assert.equal(shipmentLimit({ ...item, track_serial: true }, { ...stock, serials: [{ id: 1 }, { id: 2 }] }), '2');
    assert.equal(shipmentLimit({ ...item, track_serial: true }, stock), '0');
    assert.equal(shipmentLimit({ ...item, ordered: '1.500', delivered: '0', track_serial: true }, { ...stock, serials: [{ id: 1 }, { id: 2 }] }), '1');
});

test('partial dispatch validation rejects missing, foreign, duplicate and excessive lines without mutating input', () => {
    const before = JSON.stringify(order);
    assert.equal(validateShipment(order, [selection]), '');
    assert.notEqual(validateShipment(order, []), '');
    assert.notEqual(validateShipment(order, [{ ...selection, warehouse_id: 999 }]), '');
    assert.notEqual(validateShipment(order, [{ ...selection, so_item_id: 999 }]), '');
    assert.notEqual(validateShipment(order, [selection, selection]), '');
    for (const quantity of ['0', '-1', '7', '0.0001', '1e2']) assert.notEqual(validateShipment(order, [{ ...selection, quantity }]), '');
    assert.equal(JSON.stringify(order), before);
});

test('two SO lines for the same product share a warehouse balance without floating-point rounding', () => {
    const shared = { warehouse_id: 1, qty: '0.300', serials: [] };
    const first = { ...item, ordered: '1.000', delivered: '0.000', stocks: [shared] };
    const second = { ...first, id: 101 };
    const current = { items: [first, second] };
    assert.equal(validateShipment(current, [{ ...selection, quantity: '0.100' }, { ...selection, so_item_id: 101, quantity: '0.200' }]), '');
    assert.notEqual(validateShipment(current, [{ ...selection, quantity: '0.101' }, { ...selection, so_item_id: 101, quantity: '0.200' }]), '');
});

test('serial validation requires the selected product/warehouse serials once per whole unit', () => {
    const tracked = { ...item, track_serial: true, stocks: [{ ...stock, serials: [{ id: 1 }, { id: 2 }] }] };
    const current = { items: [tracked] };
    assert.equal(validateShipment(current, [{ ...selection, quantity: '2', serial_ids: ['1', '2'] }]), '');
    for (const serial_ids of [[], ['1'], ['1', '1'], ['1', '99']]) {
        assert.notEqual(validateShipment(current, [{ ...selection, quantity: '2', serial_ids }]), '');
    }
    assert.notEqual(validateShipment(current, [{ ...selection, quantity: '1.5', serial_ids: ['1', '2'] }]), '');
    assert.notEqual(validateShipment(order, [{ ...selection, quantity: '1', serial_ids: ['1'] }]), '');
});

test('receipt serial parsing accepts comma and line separated values without changing serial text', () => {
    assert.equal(parseSerials(' ONE, TWO\r\nTHREE\n FOUR, ').join('|'), 'ONE|TWO|THREE|FOUR');
    assert.equal(parseSerials('').length, 0);
});

test('grouped movement descriptions retain full quantity and CSV/print include every serial without expanding transactions', () => {
    const serial_numbers = Array.from({ length: 18 }, (_, i) => `SN-${i + 1}`);
    const row = { time: '19 Nov 2026, 12:00', reference: 'TEST-GROUPED', action: 'GOODS_IN', sku: 'TEST-SKU', name: 'Test projector', quantity: '18.000', unit: 'Unit', warehouse: 'Gudang Utama', serial_numbers };
    assert.equal(movementDescription(row), 'TEST-SKU — Test projector: 18 Unit · Gudang Utama');
    assert.equal(movementSerials(row).length, 18);
    const exported = movementValues(row);
    assert.equal(exported.length, 4);
    assert.equal(exported[1], 'TEST-GROUPED');
    for (const number of serial_numbers) assert.ok(exported[3].includes(number));
    assert.equal(movementSerials({ serial_number: '0' })[0], '0');
    assert.equal(movementSerials({ serial_number: null }).length, 0);
    assert.equal(movementDescription({ ...row, quantity: '0.125' }).includes('0,125 Unit'), true);
    assert.ok(toCsv([exported]).includes('SN-18'));
});

test('serialized dispatch is ready by default and retains valid manual choices on quantity or warehouse changes', () => {
    const serials = [1, 2, 3, 4].map((id) => ({ id }));
    assert.equal(defaultSerialSelection(serials, '2').join(','), '1,2');
    assert.equal(defaultSerialSelection(serials, '1', ['3', '4']).join(','), '3');
    assert.equal(defaultSerialSelection(serials, '3', ['3']).join(','), '3,1,2');
    assert.equal(defaultSerialSelection(serials, '2', ['99']).join(','), '1,2');
    assert.equal(defaultSerialSelection(serials, '2', ['1'], ['1', '2']).join(','), '3,4');
    assert.equal(defaultSerialSelection(serials, '1.5').length, 0);
    assert.equal(defaultSerialSelection(serials, '0').length, 0);
});

test('receipt document and serial fields remain optional and retain a hidden retry key', () => {
    const page = readFileSync(new URL('../../resources/views/inventory/index.blade.php', import.meta.url), 'utf8');
    assert.doesNotMatch(page.match(/<x-ui.input id="receipt-reference"[^>]*>/)[0], /\brequired\b/);
    assert.match(script, /find\('receipt-serial'\)\.required = false/);
    assert.doesNotMatch(script, /Nomor dokumen wajib diisi|Nomor serial \*/);
    assert.match(script, /pendingReceipt = postingConfirmation\(pendingReceipt, body\)/);
});

test('equivalent decimal formatting or serial ordering cannot create a second confirmation after response loss', () => {
    const body = { product_id: '1', warehouse_id: '1', reference: '', quantity: '2', serial_numbers: ['B', 'A'], notes: '' };
    const first = postingConfirmation(null, body);
    const same = postingConfirmation(first, { ...body, quantity: '2.000', serial_numbers: ['A', 'B'] });
    assert.equal(first.body.request_key, same.body.request_key);
    assert.notEqual(first.body.request_key, postingConfirmation(first, { ...body, quantity: '3' }).body.request_key);
    const issue = postingConfirmation(null, { sales_order_id: 1, items: [{ ...selection, quantity: '3.0', serial_ids: ['2', '1'] }] });
    const retry = postingConfirmation(issue, { sales_order_id: 1, items: [{ ...selection, quantity: '3.000', serial_ids: ['1', '2'] }] });
    assert.equal(issue.body.request_key, retry.body.request_key);
});

test('CSV retains Excel encoding, quoting and formula protections', () => {
    assert.equal(toCsv([['SKU', 'Nama'], ['TEST', 'A, "B"\nC']]), '\uFEFF"SKU","Nama"\r\n"TEST","A, ""B""\nC"');
    assert.equal(toCsv([['=1+1', '+CMD', '-CMD', '@SUM(A1)', '\t=1', '  =1', -2, null]]),
        '\uFEFF"\'=1+1","\'+CMD","\'-CMD","\'@SUM(A1)","\'\t=1","\'  =1","-2",""');
});

test('posting uses CSRF, keeps a logical retry key and blocks closing while a request is pending', () => {
    assert.match(script, /'X-CSRF-TOKEN'/);
    assert.match(script, /pendingIssue = postingConfirmation\(pendingIssue, body\)/);
    assert.match(script, /previous\?\.fingerprint === fingerprint \? previous\.body\.request_key/);
    assert.match(script, /dialog\.addEventListener\('cancel'/);
    assert.match(script, /event\.preventDefault\(\)/);
    assert.match(script, /dataset\.posting === 'true'/);
});
