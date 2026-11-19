<script>
(() => {
function initializeInventory(root, initial) {
    const find = (id) => root.querySelector(`#${id}`);
    const clone = (id) => find(id).content.firstElementChild.cloneNode(true);
    const setFields = (node, fields) => {
        for (const [key, value] of Object.entries(fields)) node.querySelector(`[data-field="${key}"]`).textContent = value;
        return node;
    };
    const stockState = { page: 1, size: 10, revision: 0 };
    const movementState = { page: 1, size: 10, revision: 0, loaded: false };
    const dialogTriggers = new WeakMap();
    let orders = initial.orders;
    let currentOrder = null;
    let orderRevision = 0;
    let pendingIssue = null;
    let pendingReceipt = null;
    const urls = initial.urls;

    async function fetchJson(url, body) {
        let response;
        try {
            response = await fetch(url, {
                method: body ? 'POST' : 'GET', credentials: 'same-origin',
                headers: { Accept: 'application/json', ...(body ? { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content } : {}) },
                ...(body ? { body: JSON.stringify(body) } : {}),
            });
        } catch {
            throw new Error('Tidak dapat terhubung. Periksa koneksi lalu ulangi konfirmasi yang sama.');
        }
        const json = response.headers.get('content-type')?.includes('application/json') ? await response.json() : {};
        if (!response.ok || response.redirected) {
            const error = new Error(response.status >= 500 ? 'Transaksi gagal diproses. Ulangi konfirmasi yang sama atau muat ulang untuk memeriksa hasilnya.'
                : response.status === 419 || response.redirected ? 'Sesi berakhir. Muat ulang halaman sebelum melanjutkan.'
                : Object.values(json.errors ?? {}).flat().join(' ') || json.message || 'Data tidak dapat dimuat.');
            error.errors = json.errors ?? {};
            throw error;
        }
        return json;
    }

    function queryUrl(base, filters) {
        const url = new URL(base, location.href);
        for (const [key, value] of Object.entries(filters)) if (value !== '') url.searchParams.set(key, value);
        return url;
    }
    function errorAt(id, error) {
        const node = find(id);
        node.textContent = error.message;
        node.hidden = false;
    }
    function notice(message) {
        find('inventory-feedback').textContent = message;
        find('inventory-feedback').hidden = !message;
    }
    function emptyRow(columns, message) {
        const row = setFields(clone('inventory-empty-row'), { message });
        row.firstElementChild.colSpan = columns;
        return row;
    }
    function summary(data) {
        find('inventory-sku-count').textContent = formatQuantity(data.skus);
        find('inventory-stock-total').textContent = formatQuantity(data.units);
        find('inventory-low-count').textContent = `${formatQuantity(data.low)} SKU`;
        find('inventory-warehouse-count').textContent = `Di ${formatQuantity(data.warehouses)} gudang`;
    }
    function updatePager(prefix, state, data) {
        state.page = data.current_page ?? data.page;
        const pages = data.last_page ?? data.pages;
        find(`${prefix}-count`).textContent = data.total ? `Menampilkan ${data.from ?? data.start}–${data.to ?? data.end} dari ${data.total} data` : 'Tidak ada data';
        find(`${prefix}-page`).textContent = `Halaman ${state.page}/${pages}`;
        find(`${prefix}-prev`).disabled = state.page <= 1;
        find(`${prefix}-next`).disabled = state.page >= pages;
    }
    function bindPager(prefix, state, render) {
        find(`${prefix}-size`).addEventListener('change', (event) => {
            state.size = Number(event.target.value);
            state.page = 1;
            void render();
        });
        for (const [direction, increment] of [['prev', -1], ['next', 1]]) {
            find(`${prefix}-${direction}`).addEventListener('click', () => { state.page += increment; void render(); });
        }
    }
    function bindRowPager(prefix, bodyId) {
        const state = { page: 1, size: 10 };
        const rows = () => [...find(bodyId).children].filter((row) => !row.hasAttribute('data-empty'));
        function render() {
            const all = rows();
            const data = paginate(all, state.page, state.size);
            const visible = new Set(data.rows);
            all.forEach((row) => { row.hidden = !visible.has(row); });
            updatePager(prefix, state, data);
        }
        bindPager(prefix, state, render);
        return {
            reset() { state.page = 1; render(); },
            show(row) { state.page = Math.floor(rows().indexOf(row) / state.size) + 1; render(); },
        };
    }
    function stockFilters() {
        return { search: find('inventory-search').value, warehouse_id: find('inventory-warehouse').value };
    }
    function stockValues(row) {
        return [row.sku, row.name, row.category, row.warehouse, `${formatQuantity(row.qty)} ${row.unit}`, `${formatQuantity(row.min_stock)} ${row.unit}`,
            quantityUnits(row.qty) < quantityUnits(row.min_stock) ? 'Low stock' : 'Available'];
    }
    function renderStocks(data) {
        const rows = data.data.map((stock) => {
            const values = stockValues(stock);
            const row = setFields(clone('inventory-stock-row'), {
                sku: values[0], name: values[1], category: values[2], warehouse: values[3], qty: values[4], min: values[5],
            });
            row.querySelector('[data-field="status"]').append(clone(values[6] === 'Low stock' ? 'inventory-low-badge' : 'inventory-available-badge'));
            return row;
        });
        find('inventory-stock-rows').replaceChildren(...(rows.length ? rows : [emptyRow(7, 'Tidak ada barang yang sesuai. Coba kata kunci atau filter lain.')]));
        updatePager('inventory-stock', stockState, data);
    }
    async function loadStocks() {
        const revision = ++stockState.revision;
        root.dataset.stockLoading = 'true';
        try {
            const data = await fetchJson(queryUrl(urls.stocks, { ...stockFilters(), page: stockState.page, per_page: stockState.size }));
            if (revision !== stockState.revision) return;
            if (data.stocks.current_page > data.stocks.last_page) {
                stockState.page = data.stocks.last_page;
                return await loadStocks();
            }
            renderStocks(data.stocks);
            summary(data.summary);
        } catch (error) {
            if (revision === stockState.revision) notice(error.message);
            throw error;
        } finally {
            if (revision === stockState.revision) root.dataset.stockLoading = 'false';
        }
    }
    async function loadMovements() {
        const revision = ++movementState.revision;
        root.dataset.movementLoading = 'true';
        try {
            const data = await fetchJson(queryUrl(urls.movements, { search: find('movement-search').value, page: movementState.page, per_page: movementState.size }));
            if (revision !== movementState.revision) return;
            if (data.current_page > data.last_page) {
                movementState.page = data.last_page;
                return await loadMovements();
            }
            const rows = data.data.map((row) => {
                const [time, reference, action] = movementValues(row);
                const node = setFields(clone('inventory-movement-row'), { time, reference, action, detail: movementDescription(row) });
                const serials = movementSerials(row);
                if (serials.length) {
                    const details = document.createElement('details');
                    details.className = 'mt-1 whitespace-normal';
                    const summary = document.createElement('summary');
                    summary.className = 'cursor-pointer';
                    summary.textContent = `${serials.length} nomor serial`;
                    const list = document.createElement('ul');
                    list.className = 'break-words';
                    for (const serial of serials) {
                        const item = document.createElement('li');
                        item.textContent = serial;
                        list.append(item);
                    }
                    details.append(summary, list);
                    node.querySelector('[data-field="detail"]').append(details);
                }
                return node;
            });
            find('movement-rows').replaceChildren(...(rows.length ? rows : [emptyRow(4, 'Belum ada stock movement yang sesuai.')]));
            updatePager('inventory-movement', movementState, data);
            movementState.loaded = true;
        } catch (error) {
            if (revision === movementState.revision) notice(error.message);
            throw error;
        } finally {
            if (revision === movementState.revision) root.dataset.movementLoading = 'false';
        }
    }
    const safely = (operation) => (...args) => { void operation(...args).catch(() => {}); };
    function openDialog(dialog, trigger) {
        dialogTriggers.set(dialog, trigger);
        dialog.showModal();
    }
    async function withPosting(dialog, operation) {
        if (dialog.dataset.posting === 'true') return null;
        dialog.dataset.posting = 'true';
        const controls = [...dialog.querySelectorAll('input, select, textarea, button')].map((node) => [node, node.disabled]);
        controls.forEach(([node]) => { node.disabled = true; });
        try {
            return await operation();
        } finally {
            dialog.dataset.posting = 'false';
            controls.forEach(([node, disabled]) => { node.disabled = disabled; });
        }
    }
    async function tableData(target) {
        const table = find(target).closest('table');
        const header = [...table.tHead.rows[0].cells].map((cell) => cell.textContent.trim());
        let rows;
        if (target === 'inventory-stock-rows' || target === 'movement-rows') {
            const stocks = target === 'inventory-stock-rows';
            const data = await fetchJson(queryUrl(urls.export.replace('__TABLE__', stocks ? 'stocks' : 'movements'),
                stocks ? stockFilters() : { search: find('movement-search').value }));
            rows = data.map(stocks ? stockValues : movementValues);
        } else if (target === 'issue-lines') {
            rows = [...find(target).querySelectorAll('[data-issue-row]')].map((row) => {
                const item = currentOrder.items.find((i) => String(i.id) === row.dataset.itemId);
                const serials = [...row.querySelector('[data-serial-select]').selectedOptions].map((o) => o.textContent);
                return [row.querySelector('[type="checkbox"]').checked ? 'Ya' : 'Tidak', item.sku,
                    `${item.name}${serials.length ? `\nSerial: ${serials.join(', ')}` : ''}`,
                    row.querySelector('[data-available]').textContent, row.querySelector('[type="number"]').value,
                    row.querySelector('[data-warehouse]').selectedOptions[0]?.textContent ?? ''];
            });
        } else {
            rows = [...find(target).rows].filter((row) => !row.hasAttribute('data-empty')).map((row) => [...row.cells].map((cell) => cell.textContent.trim()));
        }
        return { title: table.caption.textContent.trim(), rows: [header, ...rows] };
    }
    root.addEventListener('click', async (event) => {
        const opener = event.target.closest('[data-open-dialog]');
        if (opener) {
            if (opener.dataset.openDialog === 'goods-out-dialog' && !orders.length) {
                notice('Tidak ada SO aktif dengan produk stok yang masih perlu dikirim.');
                return;
            }
            const dialog = find(opener.dataset.openDialog);
            if (dialog) {
                openDialog(dialog, opener);
                if (dialog.id === 'movements-dialog') safely(loadMovements)();
            }
        }
        const closer = event.target.closest('[data-close-dialog]');
        if (closer && closer.closest('dialog').dataset.posting !== 'true') closer.closest('dialog').close();
        const exporter = event.target.closest('[data-export-table]');
        const printer = event.target.closest('[data-print-table]');
        if (exporter || printer) {
            try {
                const data = await tableData(exporter?.dataset.exportTable ?? printer.dataset.printTable);
                if (exporter) downloadCsv(data.title, data.rows);
                else printTable(data.title, data.rows);
            } catch (error) { notice(error.message); }
        }
    });
    root.querySelectorAll('dialog').forEach((dialog) => {
        dialog.addEventListener('cancel', (event) => { if (dialog.dataset.posting === 'true') event.preventDefault(); });
        dialog.addEventListener('click', (event) => {
            const rect = dialog.getBoundingClientRect();
            if (event.target === dialog && dialog.dataset.posting !== 'true' && (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom)) dialog.close();
        });
        dialog.addEventListener('close', () => {
            if (!root.querySelector('dialog[open]')) dialogTriggers.get(dialog)?.focus();
        });
    });
    const receiptForm = find('goods-in-form');
    function receiptMode() {
        const product = initial.products.find((p) => String(p.product_id) === find('receipt-product').value);
        const tracked = Boolean(product?.track_serial);
        find('receipt-serial').disabled = !tracked;
        find('receipt-serial').required = false;
        find('receipt-serial').setCustomValidity('');
        find('receipt-quantity').min = tracked ? '1' : '0.001';
        find('receipt-quantity').step = tracked ? '1' : '0.001';
        root.querySelector('label[for="receipt-serial"]').textContent = 'Nomor serial (opsional)';
    }
    if (receiptForm) {
        find('receipt-product').replaceChildren(...initial.products.map((p) => new Option(`${p.sku} — ${p.product_name}`, p.product_id)));
        find('receipt-warehouse').replaceChildren(...initial.warehouses.map((w) => new Option(w.warehouse_name, w.warehouse_id)));
        find('receipt-product').addEventListener('change', () => { find('receipt-serial').value = ''; receiptMode(); });
        receiptMode();
        receiptForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const data = new FormData(receiptForm);
            const product = initial.products.find((p) => String(p.product_id) === data.get('product_id'));
            const reference = String(data.get('reference') ?? '').trim();
            const quantity = String(data.get('quantity') ?? '');
            const serials = parseSerials(String(data.get('serial') ?? ''));
            find('receipt-error').hidden = true;
            find('receipt-reference').setCustomValidity('');
            find('receipt-quantity').setCustomValidity(validQuantity(quantity) ? '' : 'Jumlah harus positif dengan maksimal tiga angka desimal.');
            if (product?.track_serial) find('receipt-serial').setCustomValidity(!serials.length || quantityUnits(quantity) === BigInt(serials.length) * 1000n
                && new Set(serials.map((s) => s.toLocaleLowerCase())).size === serials.length && serials.every((s) => s.length <= 120) ? '' : 'Isi satu serial unik (maksimal 120 karakter) per unit, dipisahkan koma atau baris baru.');
            if (!product || !receiptForm.reportValidity()) return;
            const body = { product_id: data.get('product_id'), warehouse_id: data.get('warehouse_id'), reference, quantity, serial_numbers: serials.sort(), notes: String(data.get('notes') ?? '').trim() };
            pendingReceipt = postingConfirmation(pendingReceipt, body);
            try {
                const result = await withPosting(find('goods-in-dialog'), () => fetchJson(urls.receive, pendingReceipt.body));
                if (!result) return;
                pendingReceipt = null;
                find('goods-in-dialog').close();
                receiptForm.reset();
                receiptMode();
                await refreshAfterPost(result.message);
            } catch (error) { errorAt('receipt-error', error); }
        });
        for (const id of ['receipt-reference', 'receipt-quantity', 'receipt-serial']) find(id).addEventListener('input', () => find(id).setCustomValidity(''));
    }

    const issueForm = find('goods-out-form');
    let loadOrder = async () => {};
    async function refreshOrders() {
        if (!issueForm) return;
        ++orderRevision;
        root.dataset.orderLoading = 'true';
        currentOrder = null;
        issueForm.querySelector('[type="submit"]').disabled = true;
        const selected = find('issue-order').value;
        find('issue-order').disabled = true;
        try {
            orders = await fetchJson(urls.orders);
            find('issue-order').replaceChildren(...orders.map((o) => new Option(`${o.number} — ${o.customer}`, o.id)));
            if (orders.some((o) => String(o.id) === selected)) find('issue-order').value = selected;
            await loadOrder();
        } catch (error) {
            errorAt('issue-error', error);
            throw error;
        } finally {
            find('issue-order').disabled = false;
            root.dataset.orderLoading = 'false';
        }
    }
    async function refreshAfterPost(message) {
        root.dataset.refreshing = 'true';
        const triggers = [...root.querySelectorAll('[data-open-dialog]')].map((node) => [node, node.disabled]);
        triggers.forEach(([node]) => { node.disabled = true; });
        notice(message);
        try {
            const results = await Promise.allSettled([loadStocks(), refreshOrders(), ...(movementState.loaded ? [loadMovements()] : [])]);
            notice(message + (results.some((r) => r.status === 'rejected') ? ' Sebagian tampilan belum diperbarui. Muat ulang untuk melihat data terbaru.' : ''));
        } finally {
            root.dataset.refreshing = 'false';
            triggers.forEach(([node, disabled]) => { node.disabled = disabled; });
        }
    }
    if (issueForm) {
        const issuePager = bindRowPager('inventory-issue', 'issue-lines');
        const confirmPager = bindRowPager('inventory-confirm', 'issue-confirm-rows');
        const confirmDialog = find('goods-out-confirm-dialog');
        const reviewButton = issueForm.querySelector('[type="submit"]');
        find('issue-order').replaceChildren(...orders.map((o) => new Option(`${o.number} — ${o.customer}`, o.id)));
        function updateIssueRow(row, item, rebuildSerials = false, syncSerials = true) {
            const checkbox = row.querySelector('[type="checkbox"]');
            const quantity = row.querySelector('[type="number"]');
            const serialSelect = row.querySelector('[data-serial-select]');
            const warehouse = row.querySelector('[data-warehouse]');
            const stock = item.stocks.find((s) => String(s.warehouse_id) === warehouse.value);
            const limit = shipmentLimit(item, stock);
            const wasDisabled = checkbox.disabled;
            checkbox.disabled = quantityUnits(limit) === 0n;
            if (wasDisabled || rebuildSerials) checkbox.checked = !checkbox.disabled;
            if (checkbox.disabled) checkbox.checked = false;
            quantity.disabled = !checkbox.checked;
            quantity.min = item.track_serial ? '1' : '0.001';
            quantity.step = item.track_serial ? '1' : '0.001';
            quantity.max = limit;
            if (rebuildSerials) {
                const old = quantityUnits(quantity.value);
                quantity.value = old === null || old <= 0n || old > quantityUnits(limit) ? limit : quantity.value;
                serialSelect.replaceChildren(...(stock?.serials ?? []).map((s) => new Option(s.number, s.id)));
            }
            if (item.track_serial && syncSerials && checkbox.checked) {
                const reserved = row.isConnected ? [...find('issue-lines').querySelectorAll('[data-issue-row]')]
                    .filter((other) => other !== row && other.querySelector('[type="checkbox"]').checked)
                    .flatMap((other) => [...other.querySelector('[data-serial-select]').selectedOptions].map((option) => option.value)) : [];
                const selected = defaultSerialSelection(stock?.serials ?? [], quantity.value,
                    [...serialSelect.selectedOptions].map((option) => option.value), reserved);
                for (const option of serialSelect.options) option.selected = selected.includes(option.value);
            }
            row.querySelector('[data-available]').textContent = `${formatQuantity(stock?.qty ?? '0')} ${item.unit}`;
            row.querySelector('[data-serial-control]').hidden = !item.track_serial;
            row.querySelector('[data-serial-hint]').textContent = `${stock?.serials.length ?? 0} serial tersedia. Stok lama tanpa serial tidak dapat dikirim.`;
            serialSelect.disabled = !item.track_serial || !checkbox.checked;
            serialSelect.required = item.track_serial && checkbox.checked;
            serialSelect.setAttribute('aria-label', `Serial ${item.sku}`);
        }
        function renderIssueLines() {
            const rows = (currentOrder?.items ?? []).map((item, index) => {
                const row = setFields(clone('inventory-issue-row'), {
                    sku: item.sku, name: item.name, remaining: `Sisa pesanan: ${formatQuantity(quantityString(quantityUnits(item.ordered) - quantityUnits(item.delivered)))} ${item.unit}`,
                });
                row.dataset.itemId = item.id;
                const checkbox = row.querySelector('[type="checkbox"]');
                checkbox.name = `include_${index}`;
                checkbox.setAttribute('aria-label', `Pilih ${item.name}`);
                checkbox.checked = true;
                const quantity = row.querySelector('[type="number"]');
                quantity.setAttribute('aria-label', `Jumlah keluar ${item.sku}`);
                const warehouse = row.querySelector('[data-warehouse]');
                warehouse.setAttribute('aria-label', `Gudang ${item.sku}`);
                warehouse.disabled = !item.stocks.length;
                warehouse.replaceChildren(...(item.stocks.length ? item.stocks.map((s) => new Option(s.warehouse, s.warehouse_id)) : [new Option('Stok kosong', '')]));
                updateIssueRow(row, item, true);
                return row;
            });
            find('issue-lines').replaceChildren(...(rows.length ? rows : [emptyRow(6, 'Tidak ada produk yang masih perlu dikirim.')]));
            for (const row of rows) updateIssueRow(row, currentOrder.items.find((item) => String(item.id) === row.dataset.itemId));
            issuePager.reset();
        }
        loadOrder = async () => {
            const revision = ++orderRevision;
            currentOrder = null;
            reviewButton.disabled = true;
            root.dataset.orderLoading = 'true';
            find('issue-error').hidden = true;
            renderIssueLines();
            const id = find('issue-order').value;
            if (!id) { root.dataset.orderLoading = 'false'; return; }
            try {
                const order = await fetchJson(urls.order.replace('__ID__', id));
                if (revision !== orderRevision) return;
                currentOrder = order;
                renderIssueLines();
                reviewButton.disabled = false;
            } catch (error) {
                if (revision === orderRevision) errorAt('issue-error', error);
                throw error;
            } finally {
                if (revision === orderRevision) root.dataset.orderLoading = 'false';
            }
        };
        find('issue-order').addEventListener('change', safely(loadOrder));
        function issueRowChanged(event) {
            if (event.type === 'input' && !event.target.matches('[type="number"]')) return;
            const row = event.target.closest('[data-issue-row]');
            if (!row || !currentOrder) return;
            const item = currentOrder.items.find((i) => String(i.id) === row.dataset.itemId);
            updateIssueRow(row, item, event.target.matches('[data-warehouse]'), !event.target.matches('[data-serial-select]'));
            find('issue-error').hidden = true;
        }
        find('issue-lines').addEventListener('change', issueRowChanged);
        find('issue-lines').addEventListener('input', issueRowChanged);
        issueForm.addEventListener('submit', (event) => {
            event.preventDefault();
            if (!currentOrder) return;
            if (!issueForm.checkValidity()) {
                const invalid = issueForm.querySelector(':invalid');
                if (invalid.closest('[data-issue-row]')) issuePager.show(invalid.closest('tr'));
                issueForm.reportValidity();
                return;
            }
            const selections = [...find('issue-lines').querySelectorAll('[data-issue-row]')].filter((r) => r.querySelector('[type="checkbox"]').checked).map((row) => ({
                so_item_id: row.dataset.itemId, warehouse_id: row.querySelector('[data-warehouse]').value,
                quantity: row.querySelector('[type="number"]').value,
                serial_ids: [...row.querySelector('[data-serial-select]').selectedOptions].map((o) => o.value).sort(),
            }));
            const error = validateShipment(currentOrder, selections);
            find('issue-error').textContent = error;
            find('issue-error').hidden = !error;
            if (error) return;
            const body = { sales_order_id: currentOrder.id, items: selections, notes: find('issue-notes').value.trim() };
            pendingIssue = postingConfirmation(pendingIssue, body);
            find('issue-confirm-error').hidden = true;
            find('issue-confirm-order').textContent = currentOrder.number;
            find('issue-confirm-customer').textContent = currentOrder.customer;
            find('issue-confirm-notes').textContent = body.notes || '—';
            find('issue-confirm-rows').replaceChildren(...selections.map((selection) => {
                const item = currentOrder.items.find((i) => String(i.id) === selection.so_item_id);
                const stock = item.stocks.find((s) => String(s.warehouse_id) === selection.warehouse_id);
                const serials = stock.serials.filter((s) => selection.serial_ids.includes(String(s.id))).map((s) => s.number);
                return setFields(clone('inventory-confirm-row'), {
                    sku: item.sku, name: item.name + (serials.length ? `\nSerial: ${serials.join(', ')}` : ''),
                    quantity: `${formatQuantity(selection.quantity)} ${item.unit}`, warehouse: stock.warehouse,
                });
            }));
            confirmPager.reset();
            const source = find('goods-out-dialog');
            source.close();
            openDialog(confirmDialog, dialogTriggers.get(source));
        });
        confirmDialog.querySelector('[data-confirm-back]').addEventListener('click', () => {
            confirmDialog.close();
            find('goods-out-dialog').showModal();
        });
        confirmDialog.querySelector('form').addEventListener('submit', async (event) => {
            event.preventDefault();
            if (!pendingIssue) return;
            find('issue-confirm-error').hidden = true;
            try {
                const result = await withPosting(confirmDialog, () => fetchJson(urls.dispatch, pendingIssue.body));
                if (!result) return;
                pendingIssue = null;
                confirmDialog.close();
                find('issue-notes').value = '';
                await refreshAfterPost(result.message);
            } catch (error) { errorAt('issue-confirm-error', error); }
        });
        const requested = initial.sales_order_id;
        const deepLink = requested && orders.some((o) => String(o.id) === String(requested));
        if (deepLink) find('issue-order').value = requested;
        void loadOrder().then(() => {
            if (deepLink) openDialog(find('goods-out-dialog'), root.querySelector('[data-open-dialog="goods-out-dialog"]'));
            else if (requested) notice('Sales Order tidak ditemukan, sudah selesai, atau tidak memiliki sisa barang untuk dikirim.');
        }).catch(() => {});
    }
    find('inventory-warehouse').append(...initial.warehouses.map((w) => new Option(w.warehouse_name, w.warehouse_id)));
    for (const id of ['inventory-search', 'inventory-warehouse']) {
        find(id).addEventListener(id === 'inventory-search' ? 'input' : 'change', () => { stockState.page = 1; safely(loadStocks)(); });
    }
    find('movement-search').addEventListener('input', () => { movementState.page = 1; safely(loadMovements)(); });
    bindPager('inventory-stock', stockState, safely(loadStocks));
    bindPager('inventory-movement', movementState, safely(loadMovements));
    renderStocks(initial.stocks);
    summary(initial.summary);
    find('movement-rows').replaceChildren(emptyRow(4, 'Histori dimuat saat Stock Movement dibuka.'));
    updatePager('inventory-movement', movementState, { page: 1, pages: 1, total: 0 });
    root.dataset.ready = 'true';
}

function newRequestKey() {
    // The local HTTP domain is not a secure context, so crypto.randomUUID is unavailable there.
    const bytes = crypto.getRandomValues(new Uint8Array(16));
    bytes[6] = (bytes[6] & 15) | 64;
    bytes[8] = (bytes[8] & 63) | 128;
    const hex = [...bytes].map((b) => b.toString(16).padStart(2, '0')).join('');
    return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
}
function quantityUnits(value) {
    const text = String(value ?? '');
    if (!/^-?\d+(?:\.\d{1,3})?$/.test(text)) return null;
    const negative = text.startsWith('-');
    const [whole, fraction = ''] = text.replace(/^-/, '').split('.');
    const units = BigInt(whole) * 1000n + BigInt(fraction.padEnd(3, '0'));
    return negative ? -units : units;
}
function quantityString(value) {
    const negative = value < 0n;
    const absolute = negative ? -value : value;
    const fraction = String(absolute % 1000n).padStart(3, '0').replace(/0+$/, '');
    return `${negative ? '-' : ''}${absolute / 1000n}${fraction ? `.${fraction}` : ''}`;
}
function formatQuantity(value) {
    const units = quantityUnits(value);
    if (units === null) return '—';
    const text = quantityString(units);
    const [whole, fraction] = text.split('.');
    return new Intl.NumberFormat('id-ID').format(BigInt(whole)) + (fraction ? `,${fraction}` : '');
}
function validQuantity(value) {
    return /^\d{1,15}(?:\.\d{1,3})?$/.test(String(value)) && quantityUnits(value) > 0n;
}
function parseSerials(value) {
    return value.split(/[,\r\n]+/).map((v) => v.trim()).filter(Boolean);
}
function movementSerials(row) {
    return row.serial_numbers ?? (row.serial_number ? [row.serial_number] : []);
}
function movementDescription(row) {
    return `${row.sku} — ${row.name}: ${formatQuantity(row.quantity)} ${row.unit} · ${row.warehouse}`;
}
function movementValues(row) {
    const serials = movementSerials(row);
    return [row.time, row.reference, row.action, `${movementDescription(row)}${serials.length ? ` · Serial: ${serials.join(', ')}` : ''}`];
}
function postingConfirmation(previous, body) {
    const normalized = { ...body };
    if (normalized.quantity !== undefined) normalized.quantity = quantityString(quantityUnits(normalized.quantity));
    if (normalized.serial_numbers) normalized.serial_numbers = [...normalized.serial_numbers].sort();
    if (normalized.items) normalized.items = normalized.items.map((item) => ({
        ...item, quantity: quantityString(quantityUnits(item.quantity)), serial_ids: [...item.serial_ids].sort(),
    }));
    const fingerprint = JSON.stringify(normalized);
    return { fingerprint, body: { ...normalized, request_key: previous?.fingerprint === fingerprint ? previous.body.request_key : newRequestKey() } };
}
function paginate(rows, page = 1, size = 10) {
    const perPage = [5, 10, 25, 50].includes(Number(size)) ? Number(size) : 10;
    const pages = Math.max(1, Math.ceil(rows.length / perPage));
    const current = Math.max(1, Math.min(pages, Math.trunc(Number(page)) || 1));
    const start = (current - 1) * perPage;
    return { rows: rows.slice(start, start + perPage), page: current, pages, total: rows.length, start: rows.length ? start + 1 : 0, end: Math.min(start + perPage, rows.length) };
}
function shipmentLimit(item, stock) {
    let limit = quantityUnits(item.ordered) - quantityUnits(item.delivered);
    const available = quantityUnits(stock?.qty ?? '0');
    if (limit > available) limit = available;
    if (item.track_serial && limit > BigInt(stock?.serials.length ?? 0) * 1000n) limit = BigInt(stock?.serials.length ?? 0) * 1000n;
    if (item.track_serial) limit = limit / 1000n * 1000n;
    return quantityString(limit > 0n ? limit : 0n);
}
function defaultSerialSelection(serials, quantity, current = [], reserved = []) {
    const units = quantityUnits(quantity);
    if (units === null || units <= 0n || units % 1000n) return [];
    const blocked = new Set(reserved.map(String));
    const available = serials.map((serial) => String(serial.id)).filter((id) => !blocked.has(id));
    const retained = current.map(String).filter((id) => available.includes(id));
    return [...new Set([...retained, ...available])].slice(0, Number(units / 1000n));
}
function validateShipment(order, selections) {
    if (!order || !selections.length) return 'Pilih minimal satu barang untuk dikeluarkan.';
    const usedStock = new Map();
    const usedItems = new Set();
    const usedSerials = new Set();
    for (const row of selections) {
        const item = order.items.find((i) => String(i.id) === String(row.so_item_id));
        const stock = item?.stocks.find((s) => String(s.warehouse_id) === String(row.warehouse_id));
        if (!item || !stock || !validQuantity(row.quantity) || usedItems.has(String(item.id))) return 'Barang, gudang, atau jumlah keluar tidak valid.';
        usedItems.add(String(item.id));
        const quantity = quantityUnits(row.quantity);
        const key = `${item.product_id}|${row.warehouse_id}`;
        const total = (usedStock.get(key) ?? 0n) + quantity;
        if (total > quantityUnits(stock.qty) || quantity > quantityUnits(item.ordered) - quantityUnits(item.delivered)) return 'Jumlah keluar melebihi stok tersedia atau sisa pesanan.';
        usedStock.set(key, total);
        if (item.track_serial && quantity !== BigInt(row.serial_ids.length) * 1000n) return 'Pilih satu serial tersedia untuk setiap unit.';
        if (!item.track_serial && row.serial_ids.length) return 'Produk ini tidak menggunakan nomor serial.';
        for (const id of row.serial_ids) {
            if (usedSerials.has(String(id)) || !stock.serials.some((s) => String(s.id) === String(id))) return 'Serial tidak tersedia untuk barang dan gudang yang dipilih.';
            usedSerials.add(String(id));
        }
    }
    return '';
}
function toCsv(rows) {
    return '\uFEFF' + rows.map((row) => row.map((value) => {
        const text = String(value ?? '');
        const safe = typeof value === 'string' && /^\s*[=+\-@\t\r]/.test(text) ? `'${text}` : text;
        return `"${safe.replaceAll('"', '""')}"`;
    }).join(',')).join('\r\n');
}
function downloadCsv(title, rows) {
    const url = URL.createObjectURL(new Blob([toCsv(rows)], { type: 'text/csv;charset=utf-8;' }));
    const link = document.createElement('a');
    link.href = url;
    link.download = `${title}.csv`;
    document.body.append(link);
    link.click();
    link.remove();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
}
function printTable(title, rows) {
    const frame = document.createElement('iframe');
    frame.title = `Cetak ${title}`;
    frame.setAttribute('aria-hidden', 'true');
    frame.tabIndex = -1;
    frame.style.cssText = 'position:fixed;width:0;height:0;border:0';
    document.body.append(frame);
    const doc = frame.contentDocument;
    doc.title = title;
    const style = doc.createElement('style');
    style.textContent = '@font-face{font-family:Inter;src:url("/fonts/inter/Inter-Variable.woff2");font-weight:100 900}@page{size:landscape;margin:15mm}body{font:12px/1.5 Inter,sans-serif;color:#262626}h1{font-size:18px;font-weight:800}table{width:100%;border-collapse:collapse}th,td{padding:8px;border:1px solid #e5e5e5;text-align:left}th{background:#fafafa}';
    doc.head.append(style);
    const heading = doc.createElement('h1');
    heading.textContent = title;
    const table = doc.createElement('table');
    rows.forEach((values, index) => {
        const row = table.insertRow();
        for (const value of values) {
            const cell = doc.createElement(index === 0 ? 'th' : 'td');
            cell.textContent = value;
            row.append(cell);
        }
    });
    doc.body.replaceChildren(heading, table);
    frame.contentWindow.addEventListener('afterprint', () => frame.remove(), { once: true });
    setTimeout(() => frame.remove(), 60000);
    doc.fonts.load('800 18px Inter').then(() => doc.fonts.ready).then(() => requestAnimationFrame(() => {
        if (!frame.isConnected) return;
        frame.contentWindow.focus();
        frame.contentWindow.print();
    })).catch(() => frame.remove());
}

const app = document.getElementById('inventory-app');
if (app) initializeInventory(app, JSON.parse(app.querySelector('#inventory-bootstrap').textContent));
})();
</script>
