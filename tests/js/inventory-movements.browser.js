// Posts only to the completed disposable BROWSER-* scenario after inventory-posting.browser.js.
(async () => {
    const root = document.getElementById('inventory-app');
    const initial = root && JSON.parse(document.getElementById('inventory-bootstrap').textContent);
    if (location.hostname !== 'app.meditech.local' || location.port !== '8001'
        || initial?.products.length !== 12 || initial.products.some((p) => !p.sku.startsWith('BROWSER-'))) {
        throw new Error('Grouped movement checks require isolated BROWSER-* fixtures on port 8001.');
    }
    const originalFetch = window.fetch;
    const read = async (url) => {
        const response = await originalFetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        if (!response.ok) throw new Error(`Read failed: ${response.status}`);
        return response.json();
    };
    if ((await read(initial.urls.stocks)).summary.units !== '22.125' || (await read(initial.urls.orders)).length !== 0) {
        throw new Error('Complete the disposable posting suite first. Do not rerun against modified fixtures.');
    }
    const checks = [];
    const assert = (name, condition) => { if (!condition) throw new Error(name); checks.push(name); };
    const find = (id) => root.querySelector(`#${id}`);
    const click = (selector) => root.querySelector(selector).click();
    const change = (id, value) => {
        const node = find(id);
        node.value = value;
        node.dispatchEvent(new Event(node.matches('select') ? 'change' : 'input', { bubbles: true }));
    };
    const waitFor = (predicate, name) => new Promise((resolve, reject) => {
        const deadline = performance.now() + 15000;
        const check = () => predicate() ? resolve() : performance.now() > deadline ? reject(new Error(`Timed out: ${name}`)) : requestAnimationFrame(check);
        check();
    });
    const posts = [];
    const csvs = [];
    const originalCreateUrl = URL.createObjectURL;
    const originalAnchorClick = HTMLAnchorElement.prototype.click;
    let printFrame;
    window.fetch = async function (url, options) {
        const response = await originalFetch.apply(this, arguments);
        if (options?.method === 'POST') posts.push({ status: response.status, body: await response.clone().json() });
        return response;
    };
    URL.createObjectURL = (blob) => { csvs.push(blob); return 'blob:inventory-grouped-check'; };
    HTMLAnchorElement.prototype.click = function () { if (!this.download) return originalAnchorClick.apply(this, arguments); };
    try {
        const tracked = initial.products.find((p) => p.sku === 'BROWSER-001');
        click('[data-open-dialog="goods-in-dialog"]');
        change('receipt-product', String(tracked.product_id));
        change('receipt-warehouse', String(initial.warehouses[0].warehouse_id));
        change('receipt-reference', '');
        change('receipt-serial', '');
        change('receipt-quantity', '18');
        find('goods-in-form').requestSubmit();
        await waitFor(() => !find('goods-in-dialog').open && root.dataset.refreshing !== 'true', '18-unit receipt');
        assert('one 18-unit confirmation creates one receipt', posts.length === 1 && posts[0].status === 201);
        const reference = posts[0].body.number;
        const grouped = await read(initial.urls.movements + '?search=' + encodeURIComponent(reference));
        assert('18 serial-unit movements display as one 18-unit transaction', grouped.total === 1 && grouped.data.length === 1 && grouped.data[0].quantity === '18.000');
        const serials = grouped.data[0].serial_numbers;
        assert('all 18 distinct serials remain linked to that transaction', serials.length === 18 && new Set(serials).size === 18);
        assert('the receipt adds 18 units, not 18 duplicate receipts worth of stock', (await read(initial.urls.stocks)).summary.units === '40.125');
        const box = root.getBoundingClientRect();
        const width = document.documentElement.clientWidth;
        click('[data-open-dialog="movements-dialog"]');
        change('movement-search', reference);
        await waitFor(() => root.dataset.movementLoading !== 'true', 'grouped movement rendering');
        const row = find('movement-rows').rows[0];
        assert('history renders one row with its full received quantity', find('movement-rows').rows.length === 1 && row.querySelector('[data-field="detail"]').textContent.includes(': 18 pcs'));
        const details = row.querySelector('details');
        assert('serials are available without flooding the transaction list', details && !details.open && details.querySelectorAll('li').length === 18 && details.querySelector('summary').textContent === '18 nomor serial');
        details.querySelector('summary').click();
        assert('expanding details reveals the complete unchanged serial list', details.open && [...details.querySelectorAll('li')].every((item, index) => item.textContent === serials[index]));
        const after = root.getBoundingClientRect();
        assert('opening grouped serial details does not shift the surrounding page', document.documentElement.clientWidth === width && Math.abs(after.width - box.width) < 0.05 && Math.abs(after.left - box.left) < 0.05);
        change('movement-search', serials[17]);
        await waitFor(() => root.dataset.movementLoading !== 'true', 'serial search');
        assert('searching one serial retains the whole transaction quantity and all serials', find('movement-rows').rows.length === 1 && find('movement-rows').textContent.includes(': 18 pcs') && find('movement-rows').querySelectorAll('li').length === 18);
        click('[data-export-table="movement-rows"]');
        await waitFor(() => csvs.length === 1, 'grouped CSV');
        const csv = await csvs[0].text();
        assert('filtered CSV contains one transaction row with all 18 serials', csv.split('\r\n').length === 2 && csv.includes(reference) && csv.includes(': 18 pcs') && serials.every((serial) => csv.includes(serial)));
        click('[data-print-table="movement-rows"]');
        await waitFor(() => [...document.querySelectorAll('iframe')].some((frame) => frame.title.startsWith('Cetak ')), 'grouped print frame');
        printFrame = [...document.querySelectorAll('iframe')].find((frame) => frame.title.startsWith('Cetak '));
        let printed = false;
        printFrame.contentWindow.print = () => { printed = true; };
        assert('print contains one transaction plus header and the complete serial list', printFrame.contentDocument.querySelectorAll('tr').length === 2 && serials.every((serial) => printFrame.contentDocument.body.textContent.includes(serial)));
        await waitFor(() => printed, 'grouped browser print');
        printFrame.remove();
        click('#movements-dialog [data-close-dialog]');
        click('[data-open-dialog="goods-in-dialog"]');
        change('receipt-reference', 'BROWSER-LITERAL-SERIAL');
        change('receipt-quantity', '1');
        const literal = '<img src=x onerror=alert(1)>';
        change('receipt-serial', literal);
        find('goods-in-form').requestSubmit();
        await waitFor(() => !find('goods-in-dialog').open && root.dataset.refreshing !== 'true', 'literal serial receipt');
        assert('a separate receipt remains a separate transaction', posts.length === 2 && posts[1].status === 201 && posts[1].body.number !== reference);
        click('[data-open-dialog="movements-dialog"]');
        change('movement-search', 'BROWSER-LITERAL-SERIAL');
        await waitFor(() => root.dataset.movementLoading !== 'true', 'literal serial rendering');
        assert('manual serial markup is rendered literally, never interpreted as HTML', find('movement-rows').rows.length === 1 && find('movement-rows').querySelector('li').textContent === literal && !find('movement-rows').querySelector('img'));
        return { passed: checks.length, checks, finalUnits: (await read(initial.urls.stocks)).summary.units };
    } finally {
        window.fetch = originalFetch;
        URL.createObjectURL = originalCreateUrl;
        HTMLAnchorElement.prototype.click = originalAnchorClick;
        printFrame?.remove();
        root.querySelectorAll('dialog[open]').forEach((dialog) => dialog.close());
    }
})();
