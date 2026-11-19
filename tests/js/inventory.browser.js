// Run in an authenticated Inventory page with inventory.read/create/update/post/export.
// This smoke test never sends a transaction write. Posting is tested separately on disposable fixtures.
(async () => {
    const root = document.getElementById('inventory-app');
    if (!root) throw new Error('Open Inventory before running these checks.');
    const initial = JSON.parse(document.getElementById('inventory-bootstrap').textContent);
    const find = (id) => root.querySelector(`#${id}`);
    const checks = [];
    const assert = (name, condition) => { if (!condition) throw new Error(name); checks.push(name); };
    const waitFor = (predicate, name) => new Promise((resolve, reject) => {
        const deadline = performance.now() + 15000;
        const check = () => predicate() ? resolve() : performance.now() > deadline ? reject(new Error(`Timed out: ${name}`)) : requestAnimationFrame(check);
        check();
    });
    const change = (id, value, type = 'change') => {
        find(id).value = value;
        find(id).dispatchEvent(new Event(type, { bubbles: true }));
    };
    const click = (selector) => {
        const node = root.querySelector(selector);
        if (!node) throw new Error(`Missing control: ${selector}`);
        node.click();
    };
    const near = (a, b) => Math.abs(parseFloat(a) - b) < 0.05;
    const waitStocks = () => waitFor(() => root.dataset.stockLoading !== 'true', 'stock response');
    const originalFetch = window.fetch;
    const originalSend = XMLHttpRequest.prototype.send;
    const originalCreateUrl = URL.createObjectURL;
    const originalAnchorClick = HTMLAnchorElement.prototype.click;
    const writeRequests = [];
    const csvs = [];
    window.fetch = function (input, init) {
        const method = String(init?.method ?? input?.method ?? 'GET').toUpperCase();
        if (!['GET', 'HEAD'].includes(method)) { writeRequests.push(method); throw new Error('Unexpected transaction write in smoke test'); }
        return originalFetch.apply(this, arguments);
    };
    XMLHttpRequest.prototype.send = function () { writeRequests.push('XHR'); throw new Error('Unexpected XHR'); };
    URL.createObjectURL = (blob) => { csvs.push(blob); return 'blob:inventory-check'; };
    HTMLAnchorElement.prototype.click = function () { if (!this.download) return originalAnchorClick.apply(this, arguments); };
    let printFrame;
    try {
        await waitFor(() => root.dataset.ready === 'true' && root.dataset.orderLoading !== 'true', 'Inventory initialization');
        await document.fonts.ready;
        assert('no preview banner or fixture actions', !/Preview UI|Pratinjau|data demo/i.test(root.textContent));
        const inherited = getComputedStyle(document.body).fontFamily;
        assert('title preserves 25px inherited extra-bold typography', getComputedStyle(root.querySelector('h1')).fontSize === '25px' && getComputedStyle(root.querySelector('h1')).fontWeight === '800' && getComputedStyle(root.querySelector('h1')).fontFamily === inherited);
        assert('stat values use inherited extra-bold type', [...root.querySelectorAll('.inventory-stat-value')].every((n) => getComputedStyle(n).fontFamily === inherited && getComputedStyle(n).fontWeight === '800'));
        assert('stat captions use inherited regular type', [...root.querySelectorAll('.inventory-stat-foot')].every((n) => getComputedStyle(n).fontFamily === inherited && getComputedStyle(n).fontWeight === '400'));
        assert('modal headings retain inherited extra-bold typography', [...root.querySelectorAll('.ui-dialog-title')].every((n) => getComputedStyle(n).fontFamily === inherited && getComputedStyle(n).fontWeight === '800'));
        assert('Inventory adds no page-specific font face', ![...document.querySelectorAll('style')].some((n) => /@font-face/.test(n.textContent)));
        const inButton = root.querySelector('[data-open-dialog="goods-in-dialog"]');
        const outButton = root.querySelector('[data-open-dialog="goods-out-dialog"]');
        assert('both authorized transaction actions exist', inButton && outButton);
        assert('primary and secondary buttons preserve 12px sizing', getComputedStyle(inButton).fontSize === '12px' && getComputedStyle(outButton).fontSize === '12px');
        assert('secondary button retains neutral border and text', getComputedStyle(outButton).color === 'rgb(82, 82, 82)' && getComputedStyle(outButton).borderTopColor === 'rgb(229, 229, 229)');
        for (const button of [inButton, outButton]) {
            const icon = button.querySelector('svg');
            assert('action icon resolves at 16px', document.querySelector(icon.querySelector('use').getAttribute('href')) && near(getComputedStyle(icon).width, 16) && near(getComputedStyle(icon).height, 16));
        }
        assert('filter retains 36px height', near(find('inventory-search').getBoundingClientRect().height, 36));
        assert('table body and headings retain 12px and 11px', getComputedStyle(find('inventory-stock-rows').querySelector('td')).fontSize === '12px' && getComputedStyle(find('inventory-stock-rows').closest('table').querySelector('th')).fontSize === '11px');
        assert('summary uses live database values', find('inventory-sku-count').textContent === String(initial.summary.skus) && find('inventory-stock-total').textContent !== '—');

        change('inventory-stock-size', '5');
        await waitStocks();
        assert('backend stock pagination uses selected page size', find('inventory-stock-rows').rows.length === Math.min(5, initial.stocks.total));
        if (initial.stocks.total > 5) {
            click('#inventory-stock-next');
            await waitStocks();
            assert('backend next page is reachable', find('inventory-stock-page').textContent.startsWith('Halaman 2/'));
        }
        const first = initial.stocks.data[0];
        if (!first) throw new Error('Smoke fixture needs at least one stock position.');
        change('inventory-search', first.sku, 'input');
        await waitStocks();
        assert('search resets stale page and finds the requested SKU', find('inventory-stock-page').textContent.startsWith('Halaman 1/') && find('inventory-stock-rows').textContent.includes(first.sku));
        change('inventory-warehouse', String(first.warehouse_id));
        await waitStocks();
        assert('warehouse and search combine on the backend', find('inventory-stock-rows').rows.length === 1 && !find('inventory-stock-rows').querySelector('[data-empty]'));
        change('inventory-search', '<not-a-product>', 'input');
        await waitStocks();
        assert('empty backend result renders the existing empty state', Boolean(find('inventory-stock-rows').querySelector('[data-empty]')));
        change('inventory-search', '', 'input');
        change('inventory-warehouse', '');
        change('inventory-stock-size', '10');
        await waitStocks();

        const pageBox = root.getBoundingClientRect();
        const pageWidth = document.documentElement.clientWidth;
        const pageOverflow = getComputedStyle(document.body).overflowY;
        const stable = (state) => {
            const box = root.getBoundingClientRect();
            assert(`${state} preserves scrollbar and page width`, document.documentElement.clientWidth === pageWidth && near(box.width, pageBox.width) && near(box.left, pageBox.left) && getComputedStyle(document.body).overflowY === pageOverflow);
        };
        click('[data-open-dialog="goods-in-dialog"]');
        assert('Goods In opens the six-field form', find('goods-in-dialog').open && find('goods-in-form').querySelectorAll('input:not([type="hidden"]), select, textarea').length === 6);
        stable('Goods In');
        assert('receipt quantity defaults to one', find('receipt-quantity').value === '1');
        assert('no supplier or cost input was added', !find('goods-in-form').querySelector('[name="supplier_id"], [name="unit_cost"]'));
        assert('receipt reference respects the actual 50 character column', find('receipt-reference').maxLength === 50);
        assert('modal preserves 17px heading, 600px cap and 30px close', getComputedStyle(find('goods-in-dialog-title')).fontSize === '17px' && getComputedStyle(find('goods-in-dialog')).maxWidth === '600px' && near(find('goods-in-dialog').querySelector('[data-close-dialog]').getBoundingClientRect().width, 30));
        change('receipt-reference', '', 'input');
        assert('receipt accepts a blank document number for automatic generation', !find('receipt-reference').required && find('receipt-reference').checkValidity());
        change('receipt-reference', '  ', 'input');
        assert('whitespace reference is optional rather than a validation blocker', find('receipt-reference').checkValidity());
        assert('receipt serial field stays optional', !find('receipt-serial').required && find('receipt-serial').checkValidity());
        change('receipt-reference', 'UI-CHECK-NOT-SUBMITTED', 'input');
        change('receipt-quantity', '0', 'input');
        find('goods-in-form').requestSubmit();
        assert('receipt rejects zero quantity', !find('receipt-quantity').checkValidity());
        change('receipt-quantity', '2', 'input');
        click('#goods-in-dialog [data-close-dialog]');
        stable('Receipt close');
        click('[data-open-dialog="goods-in-dialog"]');
        assert('cancel and reopen preserve receipt draft inputs', find('receipt-reference').value === 'UI-CHECK-NOT-SUBMITTED' && find('receipt-quantity').value === '2');
        click('#goods-in-dialog [data-close-dialog]');
        stable('Receipt reopen and close');

        click('[data-open-dialog="goods-out-dialog"]');
        stable('Goods Out');
        assert('Goods Out keeps six columns including SKU', find('issue-lines').closest('table').tHead.rows[0].cells.length === 6);
        const rows = [...find('issue-lines').querySelectorAll('[data-issue-row]')];
        assert('SO details load real stable item IDs', rows.length > 0 && rows.every((r) => r.dataset.itemId));
        assert('serialized rows have their default serials selected without extra user input', rows.filter((r) => !r.querySelector('[data-serial-control]').hidden && r.querySelector('[type="checkbox"]').checked)
            .every((r) => r.querySelector('[data-serial-select]').selectedOptions.length === Number(r.querySelector('[type="number"]').value)));
        const row = rows.find((r) => r.querySelector('[data-serial-control]').hidden && Number(r.querySelector('[type="number"]').max) > 0);
        if (!row) throw new Error('Smoke fixture needs a nonserialized outstanding item with stock.');
        for (const candidate of rows) {
            const checkbox = candidate.querySelector('[type="checkbox"]');
            checkbox.checked = false;
            checkbox.dispatchEvent(new Event('change', { bubbles: true }));
        }
        find('goods-out-form').requestSubmit();
        assert('empty dispatch selection is rejected before final confirmation', !find('issue-error').hidden && !find('goods-out-confirm-dialog').open);
        const checkbox = row.querySelector('[type="checkbox"]');
        checkbox.checked = true;
        checkbox.dispatchEvent(new Event('change', { bubbles: true }));
        const quantity = row.querySelector('[type="number"]');
        quantity.value = String(Number(quantity.max) + 1);
        find('goods-out-form').requestSubmit();
        assert('over-quantity remains in the form', find('goods-out-dialog').open && !quantity.checkValidity());
        const partial = String(Math.min(3, Number(quantity.max)));
        quantity.value = partial;
        const notes = '<script>window.inventoryInjected=true</script>';
        change('issue-notes', notes, 'input');
        find('goods-out-form').requestSubmit();
        assert('valid partial dispatch opens final review without posting', find('goods-out-confirm-dialog').open && !find('goods-out-dialog').open);
        stable('Dispatch confirmation');
        assert('review contains only the selected line', find('issue-confirm-rows').rows.length === 1 && find('issue-confirm-rows').textContent.includes(partial));
        assert('notes are rendered as literal text', find('issue-confirm-notes').textContent === notes && !find('issue-confirm-notes').querySelector('script') && !window.inventoryInjected);
        click('[data-confirm-back]');
        assert('back preserves quantity, selection and notes', find('goods-out-dialog').open && quantity.value === partial && checkbox.checked && find('issue-notes').value === notes);
        stable('Return to dispatch form');
        if (rows.length > 5) {
            change('inventory-issue-size', '5');
            click('#inventory-issue-next');
            assert('modal pagination preserves off-page form inputs', row.hidden && quantity.value === partial && checkbox.checked);
            change('inventory-issue-size', '10');
            assert('modal row-size changes preserve entered values', quantity.value === partial && checkbox.checked);
        }
        click('#goods-out-dialog [data-close-dialog]');
        stable('Dispatch close');

        click('[data-open-dialog="movements-dialog"]');
        await waitFor(() => root.dataset.movementLoading !== 'true', 'movement response');
        assert('movement history is database-backed and paginated', find('movements-dialog').open && find('inventory-movement-page').textContent.startsWith('Halaman 1/'));
        stable('Stock Movement');
        change('movement-search', '<no-such-movement>', 'input');
        await waitFor(() => root.dataset.movementLoading !== 'true', 'movement filter');
        assert('movement search handles no results', Boolean(find('movement-rows').querySelector('[data-empty]')));
        change('movement-search', '', 'input');
        await waitFor(() => root.dataset.movementLoading !== 'true', 'movement reset');
        click('#movements-dialog [data-close-dialog]');
        stable('Stock Movement close');

        change('inventory-stock-size', '5');
        await waitStocks();
        click('[data-export-table="inventory-stock-rows"]');
        await waitFor(() => csvs.length === 1, 'CSV response');
        assert('CSV includes all filtered backend pages', (await csvs[0].text()).split('\r\n').length === initial.stocks.total + 1);
        change('inventory-search', first.sku, 'input');
        await waitStocks();
        change('inventory-warehouse', String(first.warehouse_id));
        await waitStocks();
        click('[data-export-table="inventory-stock-rows"]');
        await waitFor(() => csvs.length === 2, 'filtered CSV');
        assert('CSV respects backend search and warehouse filters', (await csvs[1].text()).split('\r\n').length === 2);
        click('[data-print-table="inventory-stock-rows"]');
        await waitFor(() => [...document.querySelectorAll('iframe')].some((f) => f.title.startsWith('Cetak ')), 'print frame');
        printFrame = [...document.querySelectorAll('iframe')].find((f) => f.title.startsWith('Cetak '));
        let printed = false;
        printFrame.contentWindow.print = () => { printed = true; };
        assert('print remains scoped to filtered table rather than dashboard', printFrame.contentDocument.querySelectorAll('tr').length === 2 && !printFrame.contentDocument.querySelector('aside, nav, button'));
        await waitFor(() => printed, 'browser print');
        assert('print reaches browser print and retains the existing Inter face', [...printFrame.contentDocument.fonts].some((f) => f.family === 'Inter' && f.status === 'loaded'));
        printFrame.remove();
        change('inventory-search', '', 'input');
        change('inventory-warehouse', '');
        change('inventory-stock-size', '10');
        await waitStocks();
        assert('no fetch or XHR writes occurred during the smoke checks', writeRequests.length === 0);
        return { passed: checks.length, checks, viewport: { width: innerWidth, height: innerHeight } };
    } finally {
        window.fetch = originalFetch;
        XMLHttpRequest.prototype.send = originalSend;
        URL.createObjectURL = originalCreateUrl;
        HTMLAnchorElement.prototype.click = originalAnchorClick;
        printFrame?.remove();
        root.querySelectorAll('dialog[open]').forEach((d) => d.close());
    }
})();
