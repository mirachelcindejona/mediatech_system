// Destructive only to disposable BROWSER-* fixtures on port 8001. Never run against development inventory.
(async () => {
    const root = document.getElementById('inventory-app');
    const initial = root && JSON.parse(document.getElementById('inventory-bootstrap').textContent);
    if (location.hostname !== 'app.meditech.local' || location.port !== '8001' || initial?.summary.units !== '130.000'
        || initial.products.length !== 12 || initial.products.some((p) => !p.sku.startsWith('BROWSER-'))) {
        throw new Error('Posting checks require the fresh, isolated BROWSER-* fixture database on port 8001.');
    }
    const find = (id) => root.querySelector(`#${id}`);
    const checks = [];
    const assert = (name, condition) => { if (!condition) throw new Error(name); checks.push(name); };
    const waitFor = (predicate, name) => new Promise((resolve, reject) => {
        const deadline = performance.now() + 15000;
        const check = () => predicate() ? resolve() : performance.now() > deadline ? reject(new Error(`Timed out: ${name}`)) : requestAnimationFrame(check);
        check();
    });
    const change = (node, value) => {
        node.value = value;
        node.dispatchEvent(new Event(node.matches('select') ? 'change' : 'input', { bubbles: true }));
    };
    const click = (selector) => root.querySelector(selector).click();
    const refreshed = () => root.dataset.stockLoading !== 'true' && root.dataset.orderLoading !== 'true' && root.dataset.refreshing !== 'true';
    const originalFetch = window.fetch;
    const records = [];
    let mode = 'validation';
    let release;
    const read = async (url) => (await originalFetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })).json();
    window.fetch = async function (url, init) {
        if (init?.method !== 'POST') return originalFetch.apply(this, arguments);
        const record = { url: String(url), body: JSON.parse(init.body), status: null, response: null };
        records.push(record);
        if (mode === 'validation') {
            mode = 'normal';
            return new Response(JSON.stringify({ message: 'Validation failed', errors: { quantity: ['Server validation check.'] } }), { status: 422, headers: { 'Content-Type': 'application/json' } });
        }
        const selectedMode = mode;
        if (mode === 'lost') mode = 'normal';
        const response = await originalFetch.apply(this, arguments);
        record.status = response.status;
        record.response = await response.clone().json();
        if (selectedMode === 'hold') await new Promise((resolve) => { release = resolve; });
        if (selectedMode === 'lost') throw new Error('Simulated response loss after the server committed.');
        return response;
    };
    function selectOnly(row) {
        for (const candidate of find('issue-lines').querySelectorAll('[data-issue-row]')) {
            const checkbox = candidate.querySelector('[type="checkbox"]');
            checkbox.checked = candidate === row;
            checkbox.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }
    try {
        await waitFor(() => root.dataset.ready === 'true' && refreshed(), 'ready');
        const initialMovements = (await read(initial.urls.movements)).total;
        const product = initial.products.find((p) => p.sku === 'BROWSER-002');
        const tracked = initial.products.find((p) => p.sku === 'BROWSER-001');
        const secondWarehouse = initial.warehouses.find((w) => w.warehouse_name === 'Gudang Jakarta');
        const firstWarehouse = initial.warehouses.find((w) => w.warehouse_name === 'Gudang Utama');
        click('[data-open-dialog="goods-in-dialog"]');
        change(find('receipt-product'), String(product.product_id));
        change(find('receipt-warehouse'), String(secondWarehouse.warehouse_id));
        change(find('receipt-reference'), 'BROWSER-GR-FRACTIONAL');
        change(find('receipt-quantity'), '0.125');
        assert('nonserialized receipt supports three decimals and disables serial entry', find('receipt-quantity').step === '0.001' && find('receipt-serial').disabled);
        find('goods-in-form').requestSubmit();
        await waitFor(() => !find('receipt-error').hidden, 'receipt validation error');
        assert('server validation is shown without closing or clearing the receipt', find('receipt-error').textContent.includes('Server validation check') && find('goods-in-dialog').open && find('receipt-reference').value === 'BROWSER-GR-FRACTIONAL');
        mode = 'hold';
        find('goods-in-form').requestSubmit();
        await waitFor(() => release, 'held receipt response');
        assert('posting disables confirmation and close controls', [...find('goods-in-dialog').querySelectorAll('button')].every((b) => b.disabled));
        const cancel = new Event('cancel', { cancelable: true });
        find('goods-in-dialog').dispatchEvent(cancel);
        assert('pending posting prevents dialog cancellation', cancel.defaultPrevented && find('goods-in-dialog').open);
        find('goods-in-form').dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
        assert('repeated receipt submission while pending sends no duplicate request', records.length === 2);
        mode = 'normal';
        release();
        await waitFor(() => !find('goods-in-dialog').open && refreshed(), 'receipt success refresh');
        assert('receipt posts through the real endpoint and refreshes exact stock', records[1].status === 201 && find('inventory-stock-total').textContent === '130,125');
        assert('successful receipt resets its document reference', find('receipt-reference').value === '');

        click('[data-open-dialog="goods-in-dialog"]');
        change(find('receipt-product'), String(tracked.product_id));
        change(find('receipt-warehouse'), String(firstWarehouse.warehouse_id));
        change(find('receipt-reference'), 'BROWSER-GR-SERIALS');
        change(find('receipt-quantity'), '2');
        change(find('receipt-serial'), 'BROWSER-NEW-A,\nBROWSER-NEW-B');
        assert('serialized receipt requires whole units but keeps manual serial entry optional', find('receipt-quantity').step === '1' && !find('receipt-serial').required && !find('receipt-serial').disabled);
        find('goods-in-form').requestSubmit();
        await waitFor(() => !find('goods-in-dialog').open && refreshed(), 'serial receipt');
        assert('serial receipt posts and refreshes the physical ledger', records[2].status === 201 && find('inventory-stock-total').textContent === '132,125');
        const beforeDispatch = await read(initial.urls.order.replace('__ID__', initial.orders[0].id));
        const trackedItem = beforeDispatch.items.find((i) => i.product_id === tracked.product_id);
        assert('received serials are available alongside preserved legacy serials', trackedItem.stocks[0].serials.length === 4 && trackedItem.stocks[0].serials.some((s) => s.number === 'BROWSER-NEW-A'));

        click('[data-open-dialog="goods-out-dialog"]');
        let row = [...find('issue-lines').querySelectorAll('[data-issue-row]')].find((r) => r.querySelector('[data-field="sku"]').textContent === product.sku);
        selectOnly(row);
        change(row.querySelector('[type="number"]'), '3');
        change(find('issue-notes'), '<script>window.inventoryPostingInjected=true</script>');
        find('goods-out-form').requestSubmit();
        assert('partial dispatch review opens with literal notes', find('goods-out-confirm-dialog').open && find('issue-confirm-notes').textContent.includes('<script>') && !window.inventoryPostingInjected);
        mode = 'lost';
        click('#goods-out-confirm-dialog [type="submit"]');
        await waitFor(() => !find('issue-confirm-error').hidden && find('goods-out-confirm-dialog').dataset.posting !== 'true', 'lost-response recovery');
        const firstDispatch = records[3];
        assert('response loss preserves final review and reports uncertainty', firstDispatch.status === 201 && find('goods-out-confirm-dialog').open && find('issue-confirm-error').textContent.includes('konfirmasi yang sama'));
        assert('server committed the first dispatch exactly once before the lost response', (await read(initial.urls.stocks)).summary.units === '129.125');
        click('[data-confirm-back]');
        assert('back after a failed response preserves entered quantity', row.querySelector('[type="number"]').value === '3' && find('goods-out-dialog').open);
        find('goods-out-form').requestSubmit();
        click('#goods-out-confirm-dialog [type="submit"]');
        await waitFor(() => !find('goods-out-confirm-dialog').open && refreshed(), 'idempotent retry');
        assert('retry after back/review keeps its original key and document ID', records[4].status === 200 && records[4].body.request_key === firstDispatch.body.request_key && records[4].response.id === firstDispatch.response.id);
        assert('idempotent retry refreshes stock without a second deduction', find('inventory-stock-total').textContent === '129,125');
        const afterDispatch = await read(initial.urls.order.replace('__ID__', initial.orders[0].id));
        assert('SO item delivery contract reflects the partial dispatch', afterDispatch.items.find((i) => i.product_id === product.product_id).delivered === '13.000');

        click('[data-open-dialog="goods-out-dialog"]');
        row = [...find('issue-lines').querySelectorAll('[data-issue-row]')].find((r) => r.querySelector('[data-field="sku"]').textContent === tracked.sku);
        selectOnly(row);
        const serialSelect = row.querySelector('[data-serial-select]');
        const option = [...serialSelect.options].find((o) => o.textContent === 'BROWSER-NEW-A');
        assert('conditional serial controls stay within the six-column table', !row.querySelector('[data-serial-control]').hidden && find('issue-lines').closest('table').tHead.rows[0].cells.length === 6);
        assert('default serialized limit excludes legacy units with missing serials', row.querySelector('[type="number"]').max === '4');
        change(row.querySelector('[type="number"]'), '1');
        for (const entry of serialSelect.options) entry.selected = entry === option;
        serialSelect.dispatchEvent(new Event('change', { bubbles: true }));
        find('goods-out-form').requestSubmit();
        assert('selected serial appears in final confirmation', find('goods-out-confirm-dialog').open && find('issue-confirm-rows').textContent.includes('BROWSER-NEW-A'));
        click('#goods-out-confirm-dialog [type="submit"]');
        await waitFor(() => !find('goods-out-confirm-dialog').open && refreshed(), 'serial dispatch');
        assert('serialized dispatch posts and updates physical stock once', records[5].status === 201 && find('inventory-stock-total').textContent === '128,125');
        const afterSerial = await read(initial.urls.order.replace('__ID__', initial.orders[0].id));
        const remaining = afterSerial.items.find((i) => i.product_id === tracked.product_id);
        assert('issued serial is removed from available choices and delivery is incremented', remaining.delivered === '1.000' && remaining.stocks[0].qty === '11.000' && !remaining.stocks[0].serials.some((s) => s.number === 'BROWSER-NEW-A'));

        const noCsrf = await originalFetch(initial.urls.receive, { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify({ ...records[1].body, reference: 'BROWSER-CSRF-MUST-NOT-POST' }) });
        assert('real local-environment CSRF middleware rejects a missing token', noCsrf.status === 419);
        assert('rejected CSRF request makes no stock change', (await read(initial.urls.stocks)).summary.units === '128.125');
        const movements = await read(initial.urls.movements);
        assert('movement history adds two receipt items and two issue items, not one row per serial', movements.total === initialMovements + 4);
        assert('manual receipt reference is identical in movement history', (await read(initial.urls.movements + '?search=BROWSER-GR-SERIALS')).data.every((m) => m.reference === records[2].response.number));
        assert('Goods Out uses the HTML document prefix and its movements reuse that exact number', /^GO-\d{4}-\d{4,}$/.test(records[3].response.number)
            && (await read(initial.urls.movements + '?search=' + records[3].response.number)).data.every((m) => m.reference === records[3].response.number));

        click('[data-open-dialog="goods-in-dialog"]');
        change(find('receipt-product'), String(tracked.product_id));
        change(find('receipt-warehouse'), String(firstWarehouse.warehouse_id));
        change(find('receipt-reference'), '  ');
        change(find('receipt-serial'), '');
        change(find('receipt-quantity'), '2');
        assert('blank receipt reference and serials are valid without user-generated values', find('goods-in-form').checkValidity() && !find('receipt-reference').required && !find('receipt-serial').required);
        mode = 'lost';
        find('goods-in-form').requestSubmit();
        await waitFor(() => !find('receipt-error').hidden && find('goods-in-dialog').dataset.posting !== 'true', 'auto receipt committed with response loss');
        const autoReceipt = records[6];
        assert('blank fields generate a real receipt and add stock exactly once', autoReceipt.status === 201 && /^GR-\d{4}-\d{3,}$/.test(autoReceipt.response.number)
            && autoReceipt.body.reference === '' && autoReceipt.body.serial_numbers.length === 0 && (await read(initial.urls.stocks)).summary.units === '130.125');
        assert('response loss leaves the blank form available for an unchanged retry', find('goods-in-dialog').open && find('receipt-reference').value.trim() === '' && find('receipt-serial').value === '');
        change(find('receipt-quantity'), '2.000');
        find('goods-in-form').requestSubmit();
        await waitFor(() => !find('goods-in-dialog').open && refreshed(), 'auto receipt idempotent retry');
        assert('automatic receipt retry retains the same key, document number and stock', records[7].status === 200 && records[7].body.request_key === autoReceipt.body.request_key
            && records[7].response.number === autoReceipt.response.number && records[7].response.id === autoReceipt.response.id && (await read(initial.urls.stocks)).summary.units === '130.125');
        const generatedMovements = await read(initial.urls.movements + '?search=' + autoReceipt.response.number);
        assert('generated receipt is one grouped movement retaining both serials and its document reference', generatedMovements.total === 1
            && generatedMovements.data[0].reference === autoReceipt.response.number && generatedMovements.data[0].quantity === '2.000'
            && generatedMovements.data[0].serial_numbers.every((number) => number.startsWith('SN-' + autoReceipt.response.number + '-'))
            && new Set(generatedMovements.data[0].serial_numbers).size === 2);
        click('[data-open-dialog="movements-dialog"]');
        await waitFor(() => root.dataset.movementLoading !== 'true', 'generated receipt movement history');
        change(find('movement-search'), autoReceipt.response.number);
        await waitFor(() => root.dataset.movementLoading !== 'true', 'generated receipt reference filter');
        assert('visible Stock Movement reference exactly matches the generated receipt number', find('movement-rows').rows.length === 1
            && [...find('movement-rows').rows].every((r) => r.querySelector('[data-field="reference"]').textContent === autoReceipt.response.number));
        click('#movements-dialog [data-close-dialog]');

        click('[data-open-dialog="goods-in-dialog"]');
        change(find('receipt-product'), String(tracked.product_id));
        change(find('receipt-warehouse'), String(firstWarehouse.warehouse_id));
        change(find('receipt-quantity'), '4');
        find('goods-in-form').requestSubmit();
        await waitFor(() => !find('goods-in-dialog').open && refreshed(), 'additional legitimate serialized receipt');
        assert('a new blank-field confirmation generates a different document and new serials', records[8].status === 201 && records[8].response.number !== autoReceipt.response.number
            && records[8].body.request_key !== autoReceipt.body.request_key && find('inventory-stock-total').textContent === '134,125');
        click('[data-open-dialog="goods-out-dialog"]');
        const allRows = [...find('issue-lines').querySelectorAll('[data-issue-row]')];
        const warehouseRow = allRows.find((r) => r.querySelector('[data-field="sku"]').textContent === 'BROWSER-003');
        const warehouseSelect = warehouseRow.querySelector('[data-warehouse]');
        assert('per-row warehouses list both actual stock positions', warehouseSelect.options.length === 2);
        const include = warehouseRow.querySelector('[type="checkbox"]');
        include.checked = false;
        include.dispatchEvent(new Event('change', { bubbles: true }));
        change(warehouseSelect, String(secondWarehouse.warehouse_id));
        assert('changing warehouse reselects the row and clamps its quantity like the HTML', include.checked
            && !warehouseRow.querySelector('[type="number"]').disabled && warehouseRow.querySelector('[type="number"]').value === '5');
        const trackedRow = allRows.find((r) => r.querySelector('[data-field="sku"]').textContent === tracked.sku);
        assert('serialized dispatch defaults are ready without manually selecting serials', trackedRow.querySelector('[type="number"]').value === '9'
            && trackedRow.querySelector('[data-serial-select]').selectedOptions.length === 9);
        find('goods-out-form').requestSubmit();
        assert('multi-line review includes every selected line across modal pagination', find('goods-out-confirm-dialog').open && find('issue-confirm-rows').rows.length === 12);
        click('#goods-out-confirm-dialog [type="submit"]');
        await waitFor(() => !find('goods-out-confirm-dialog').open && refreshed(), 'multi-line partial warehouse dispatch');
        assert('multi-line dispatch posts once and preserves stock in the other warehouse', records[9].status === 201 && find('inventory-stock-total').textContent === '27,125');
        const lastOrder = await read(initial.urls.order.replace('__ID__', initial.orders[0].id));
        assert('only the outstanding line remains after partial warehouse delivery', lastOrder.items.length === 1 && lastOrder.items[0].sku === 'BROWSER-003'
            && lastOrder.items[0].delivered === '5.000' && lastOrder.items[0].stocks.length === 1);
        click('[data-open-dialog="goods-out-dialog"]');
        find('goods-out-form').requestSubmit();
        click('#goods-out-confirm-dialog [type="submit"]');
        await waitFor(() => !find('goods-out-confirm-dialog').open && refreshed(), 'complete order dispatch');
        assert('final remaining delivery completes the SO and refreshes eligible choices', records[10].status === 201
            && (await read(initial.urls.orders)).length === 0 && find('inventory-stock-total').textContent === '22,125');
        click('[data-open-dialog="goods-out-dialog"]');
        assert('no eligible SO shows feedback instead of an empty Goods Out modal', !find('goods-out-dialog').open
            && find('inventory-feedback').textContent.includes('Tidak ada SO aktif'));
        return { passed: checks.length, checks, posts: records.map((r) => ({ status: r.status, number: r.response?.number })), finalUnits: '22.125' };
    } finally {
        window.fetch = originalFetch;
        release?.();
        root.querySelectorAll('dialog[open]').forEach((d) => d.close());
    }
})();
