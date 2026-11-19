@extends('admin.layouts.layout')

@section('title', 'Buat Invoice')
@section('page_title', 'Buat Invoice')
@section('page_breadcrumb', 'Sales / Invoice / Buat Invoice')

@section('content')
    <div class="mb-6 flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center">
        <div>
            <div class="mb-[7px] text-[12px] font-bold text-primary-500">Finance workspace</div>
            <h1 class="m-0 font-heading text-[27px] font-semibold leading-tight tracking-[-0.8px] text-neutral-900 sm:text-[31px]">Buat Invoice</h1>
            <p class="mt-[6px] max-w-2xl text-[13px] leading-relaxed text-neutral-500">
                Buat tagihan berdasarkan Sales Order. Pilih item dan kuantitas yang belum pernah ditagihkan.
            </p>
        </div>
        <a href="{{ route('invoices.index') }}"
            class="inline-flex shrink-0 items-center justify-center gap-2 rounded-lg border border-neutral-200 bg-white px-[14px] py-[10px] text-[12px] font-bold text-neutral-600 transition hover:bg-neutral-50">
            <span aria-hidden="true">←</span> Kembali ke Invoice
        </a>
    </div>

    @if ($salesOrders->isEmpty())
        <section class="rounded-xl border border-neutral-200 bg-white p-8 text-center shadow-[0_1px_2px_rgba(20,27,55,0.02)]">
            <div class="mx-auto grid h-12 w-12 place-items-center rounded-full bg-primary-50 text-primary-500">
                <svg class="h-6 w-6 fill-none stroke-current" aria-hidden="true"><use href="#i-invoice"></use></svg>
            </div>
            <h2 class="mt-4 text-[15px] font-bold text-neutral-800">Belum ada Sales Order yang dapat ditagihkan</h2>
            <p class="mx-auto mt-2 max-w-lg text-[12px] leading-relaxed text-neutral-500">
                Sales Order yang dibatalkan atau seluruh kuantitasnya sudah ditagihkan tidak ditampilkan di sini. Setelah tersedia SO dengan sisa kuantitas, kamu dapat membuat invoice dari halaman ini.
            </p>
            <a href="{{ route('invoices.index') }}" class="mt-5 inline-flex items-center rounded-lg bg-primary-500 px-4 py-2.5 text-[11px] font-bold text-white hover:bg-primary-700">Kembali ke daftar invoice</a>
        </section>
    @else
        <form id="invoice-create-form" method="POST" action="{{ route('invoices.store') }}" class="space-y-4">
            @csrf

            <section class="overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-[0_1px_2px_rgba(20,27,55,0.02)]">
                <div class="border-b border-neutral-100 px-5 py-4">
                    <h2 class="text-[13px] font-bold text-neutral-800">Informasi Invoice</h2>
                    <p class="mt-1 text-[11px] text-neutral-500">Pilih Sales Order sebagai sumber data customer dan item tagihan.</p>
                </div>
                <div class="grid grid-cols-1 gap-4 p-5 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <label for="sales_order_id" class="mb-1.5 block text-[11px] font-bold text-neutral-600">Sales Order <span class="text-error-500">*</span></label>
                        <select id="sales_order_id" name="sales_order_id" required
                            class="h-10 w-full rounded-lg border border-neutral-200 bg-white px-3 text-[12px] text-neutral-700 outline-none focus:border-primary-300 focus:ring-2 focus:ring-primary-50">
                            <option value="">Pilih Sales Order</option>
                            @foreach ($salesOrders as $order)
                                <option value="{{ $order['sales_order_id'] }}" @selected((string) old('sales_order_id') === (string) $order['sales_order_id'])>
                                    {{ $order['so_number'] }} — {{ $order['customer_name'] }}
                                </option>
                            @endforeach
                        </select>
                        @error('sales_order_id')<p class="mt-1 text-[10px] text-error-500">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="invoice_type" class="mb-1.5 block text-[11px] font-bold text-neutral-600">Tipe Invoice <span class="text-error-500">*</span></label>
                        <select id="invoice_type" name="invoice_type" required
                            class="h-10 w-full rounded-lg border border-neutral-200 bg-white px-3 text-[12px] text-neutral-700 outline-none focus:border-primary-300 focus:ring-2 focus:ring-primary-50">
                            <option value="Barang keluar" @selected(old('invoice_type', 'Barang keluar') === 'Barang keluar')>Barang keluar</option>
                            <option value="Termin" @selected(old('invoice_type') === 'Termin')>Termin</option>
                        </select>
                        <p class="mt-1 text-[10px] leading-relaxed text-neutral-400">Barang keluar mengikuti jumlah yang sudah dikirim; Termin mengikuti jumlah pesanan.</p>
                        @error('invoice_type')<p class="mt-1 text-[10px] text-error-500">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="invoice_date" class="mb-1.5 block text-[11px] font-bold text-neutral-600">Tanggal Invoice <span class="text-error-500">*</span></label>
                        <input id="invoice_date" type="date" name="invoice_date" required value="{{ old('invoice_date', now()->toDateString()) }}"
                            class="h-10 w-full rounded-lg border border-neutral-200 bg-white px-3 text-[12px] text-neutral-700 outline-none focus:border-primary-300 focus:ring-2 focus:ring-primary-50">
                        @error('invoice_date')<p class="mt-1 text-[10px] text-error-500">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="due_date" class="mb-1.5 block text-[11px] font-bold text-neutral-600">Jatuh Tempo <span class="text-error-500">*</span></label>
                        <input id="due_date" type="date" name="due_date" required min="{{ old('invoice_date', now()->toDateString()) }}" value="{{ old('due_date') }}"
                            class="h-10 w-full rounded-lg border border-neutral-200 bg-white px-3 text-[12px] text-neutral-700 outline-none focus:border-primary-300 focus:ring-2 focus:ring-primary-50">
                        @error('due_date')<p class="mt-1 text-[10px] text-error-500">{{ $message }}</p>@enderror
                    </div>

                    <div class="md:col-span-2">
                        <label for="notes" class="mb-1.5 block text-[11px] font-bold text-neutral-600">Catatan <span class="font-normal text-neutral-400">(opsional)</span></label>
                        <textarea id="notes" name="notes" rows="2" maxlength="5000" placeholder="Tambahkan catatan invoice bila diperlukan…"
                            class="w-full rounded-lg border border-neutral-200 bg-white px-3 py-2.5 text-[12px] text-neutral-700 outline-none focus:border-primary-300 focus:ring-2 focus:ring-primary-50">{{ old('notes') }}</textarea>
                        @error('notes')<p class="mt-1 text-[10px] text-error-500">{{ $message }}</p>@enderror
                    </div>
                </div>
            </section>

            <section id="sales-order-information" class="hidden overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-[0_1px_2px_rgba(20,27,55,0.02)]">
                <div class="border-b border-neutral-100 px-5 py-4">
                    <h2 class="text-[13px] font-bold text-neutral-800">Informasi Sales Order</h2>
                    <p class="mt-1 text-[11px] text-neutral-500">Informasi berikut diambil dari snapshot Sales Order.</p>
                </div>
                <div class="grid grid-cols-1 gap-4 p-5 sm:grid-cols-2 lg:grid-cols-4">
                    <div><div class="text-[10px] font-semibold text-neutral-400">Customer</div><div id="so-customer" class="mt-1 text-[12px] font-bold text-neutral-800">—</div></div>
                    <div><div class="text-[10px] font-semibold text-neutral-400">No. Referensi</div><div id="so-reference" class="mt-1 text-[12px] font-semibold text-neutral-700">—</div></div>
                    <div><div class="text-[10px] font-semibold text-neutral-400">Tanggal SO</div><div id="so-date" class="mt-1 text-[12px] font-semibold text-neutral-700">—</div></div>
                    <div><div class="text-[10px] font-semibold text-neutral-400">Payment Terms</div><div id="so-terms" class="mt-1 text-[12px] font-semibold text-neutral-700">—</div></div>
                    <div class="sm:col-span-2"><div class="text-[10px] font-semibold text-neutral-400">Bill To</div><div id="so-bill-to" class="mt-1 whitespace-pre-line text-[12px] text-neutral-700">—</div></div>
                    <div class="sm:col-span-2"><div class="text-[10px] font-semibold text-neutral-400">Ship To</div><div id="so-ship-to" class="mt-1 whitespace-pre-line text-[12px] text-neutral-700">—</div></div>
                </div>
            </section>

            <section id="invoice-items-section" class="overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-[0_1px_2px_rgba(20,27,55,0.02)]">
                <div class="border-b border-neutral-100 px-5 py-4">
                    <h2 class="text-[13px] font-bold text-neutral-800">Item yang Ditagihkan</h2>
                    <p class="mt-1 text-[11px] text-neutral-500">Pilih kuantitas setiap item. Item dengan kuantitas nol tidak akan dimasukkan ke invoice.</p>
                </div>
                <div id="invoice-items-empty" class="px-5 py-12 text-center">
                    <p class="text-[12px] font-semibold text-neutral-500">Pilih Sales Order terlebih dahulu.</p>
                    <p class="mt-1 text-[10px] text-neutral-400">Daftar produk dan sisa kuantitas akan ditampilkan setelah SO dipilih.</p>
                </div>
                <div id="invoice-items-content" class="hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[900px] border-collapse text-left">
                            <thead><tr class="bg-neutral-50">
                                <th class="border-b border-neutral-200 px-4 py-3 text-[10px] font-bold text-neutral-500">Produk</th>
                                <th class="border-b border-neutral-200 px-4 py-3 text-right text-[10px] font-bold text-neutral-500">Qty SO</th>
                                <th class="border-b border-neutral-200 px-4 py-3 text-right text-[10px] font-bold text-neutral-500">Terkirim</th>
                                <th class="border-b border-neutral-200 px-4 py-3 text-right text-[10px] font-bold text-neutral-500">Sudah Ditagih</th>
                                <th class="border-b border-neutral-200 px-4 py-3 text-right text-[10px] font-bold text-neutral-500">Sisa Dapat Ditagih</th>
                                <th class="border-b border-neutral-200 px-4 py-3 text-right text-[10px] font-bold text-neutral-500">Harga Satuan</th>
                                <th class="border-b border-neutral-200 px-4 py-3 text-right text-[10px] font-bold text-neutral-500">Qty Ditagihkan</th>
                                <th class="border-b border-neutral-200 px-4 py-3 text-right text-[10px] font-bold text-neutral-500">Subtotal</th>
                            </tr></thead>
                            <tbody id="invoice-items-body"></tbody>
                        </table>
                    </div>
                    <div id="invoice-no-available-items" class="hidden px-5 py-8 text-center text-[11px] text-neutral-500">Tidak ada item yang tersisa untuk tipe invoice ini.</div>
                    <div class="grid grid-cols-1 gap-4 border-t border-neutral-100 p-5 lg:grid-cols-[minmax(0,1fr)_320px]">
                        <div class="rounded-lg border border-primary-100 bg-primary-50/60 p-4">
                            <p class="text-[11px] font-bold text-primary-700">Informasi penagihan</p>
                            <p class="mt-1 text-[10px] leading-relaxed text-neutral-600">Kuantitas maksimal Barang keluar mengikuti jumlah yang telah dikirim. Untuk Termin, kuantitas maksimal mengikuti jumlah pesanan. Sistem akan memeriksa ulang sisa kuantitas di server saat invoice diterbitkan.</p>
                        </div>
                        <div class="rounded-lg bg-neutral-50 p-4">
                            <div class="flex justify-between gap-3 py-1.5 text-[11px] text-neutral-600"><span>Subtotal item</span><strong id="summary-subtotal" class="font-semibold text-neutral-800">Rp 0,00</strong></div>
                            <div class="flex justify-between gap-3 py-1.5 text-[11px] text-neutral-600"><span>Alokasi diskon SO</span><strong id="summary-discount" class="font-semibold text-neutral-800">Rp 0,00</strong></div>
                            <div class="flex justify-between gap-3 py-1.5 text-[11px] text-neutral-600"><span>Pajak</span><strong id="summary-tax" class="font-semibold text-neutral-800">Rp 0,00</strong></div>
                            <div class="flex justify-between gap-3 py-1.5 text-[11px] text-neutral-600"><span>Biaya lain</span><strong id="summary-other" class="font-semibold text-neutral-800">Rp 0,00</strong></div>
                            <div class="mt-2 flex justify-between gap-3 border-t border-neutral-200 pt-3 text-[12px] font-bold text-neutral-900"><span>Estimasi Total</span><strong id="summary-total" class="text-[15px] text-primary-500">Rp 0,00</strong></div>
                            <p class="mt-2 text-[9px] leading-relaxed text-neutral-400">Estimasi di layar. Perhitungan final dilakukan kembali oleh server.</p>
                        </div>
                    </div>
                </div>
            </section>

            <div id="invoice-items-payload"></div>

            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <a href="{{ route('invoices.index') }}" class="inline-flex items-center justify-center rounded-lg border border-neutral-200 bg-white px-5 py-3 text-[11px] font-bold text-neutral-600 hover:bg-neutral-50">Batal</a>
                <button id="invoice-submit-button" type="submit" disabled class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary-500 px-5 py-3 text-[11px] font-bold text-white transition hover:bg-primary-700 disabled:cursor-not-allowed disabled:opacity-40">
                    <svg class="h-4 w-4 fill-none stroke-current" aria-hidden="true"><use href="#i-invoice"></use></svg>
                    Terbitkan Invoice
                </button>
            </div>
        </form>

        <dialog id="invoice-confirm-dialog" class="w-[min(440px,calc(100%-2rem))] rounded-xl border border-neutral-200 p-0 shadow-2xl backdrop:bg-neutral-900/40">
            <div class="p-5">
                <div class="grid h-10 w-10 place-items-center rounded-full bg-warning-50 text-warning-500">
                    <svg class="h-5 w-5 fill-none stroke-current" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 8v5m0 4h.01M10.3 3.9 2.8 17a2 2 0 0 0 1.7 3h15a2 2 0 0 0 1.7-3l-7.5-13.1a2 2 0 0 0-3.4 0Z" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </div>
                <h2 class="mt-4 text-[14px] font-bold text-neutral-900">Terbitkan invoice?</h2>
                <p class="mt-1 text-[11px] leading-relaxed text-neutral-500">Pastikan Sales Order, tipe invoice, kuantitas, dan tanggal sudah benar. Setelah diterbitkan, invoice akan tercatat sebagai dokumen transaksi.</p>
                <div class="mt-5 flex justify-end gap-2">
                    <button id="invoice-confirm-cancel" type="button" class="rounded-lg border border-neutral-200 px-4 py-2.5 text-[11px] font-bold text-neutral-600 hover:bg-neutral-50">Periksa kembali</button>
                    <button id="invoice-confirm-submit" type="button" class="rounded-lg bg-primary-500 px-4 py-2.5 text-[11px] font-bold text-white hover:bg-primary-700">Ya, terbitkan</button>
                </div>
            </div>
        </dialog>
    @endif
@endsection

@push('scripts')
    @if (!$salesOrders->isEmpty())
        <script>
            (() => {
                const orders = {{ \Illuminate\Support\Js::from($salesOrders->values()) }};
                const oldItems = {{ \Illuminate\Support\Js::from(old('items', [])) }};
                const form = document.getElementById('invoice-create-form');
                if (!form) return;

                const orderSelect = document.getElementById('sales_order_id');
                const typeSelect = document.getElementById('invoice_type');
                const invoiceDate = document.getElementById('invoice_date');
                const dueDate = document.getElementById('due_date');
                const infoSection = document.getElementById('sales-order-information');
                const itemsEmpty = document.getElementById('invoice-items-empty');
                const itemsContent = document.getElementById('invoice-items-content');
                const itemsBody = document.getElementById('invoice-items-body');
                const noAvailableItems = document.getElementById('invoice-no-available-items');
                const payload = document.getElementById('invoice-items-payload');
                const submitButton = document.getElementById('invoice-submit-button');
                const confirmDialog = document.getElementById('invoice-confirm-dialog');
                const confirmCancel = document.getElementById('invoice-confirm-cancel');
                const confirmSubmit = document.getElementById('invoice-confirm-submit');
                let selectedOrder = null;
                let confirmed = false;
                const quantityValues = new Map();

                (Array.isArray(oldItems) ? oldItems : []).forEach((item) => {
                    if (item && item.so_item_id) quantityValues.set(String(item.so_item_id), String(item.quantity ?? ''));
                });

                const number = (value) => Number(value || 0);
                const rupiah = (value) => new Intl.NumberFormat('id-ID', {
                    style: 'currency', currency: 'IDR', minimumFractionDigits: 2, maximumFractionDigits: 2,
                }).format(number(value));
                const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
                    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;',
                }[char]));
                const trimQuantity = (value) => Number(value).toLocaleString('id-ID', { maximumFractionDigits: 3 });

                function getAvailable(item) {
                    return typeSelect.value === 'Termin'
                        ? number(item.available_for_term)
                        : number(item.available_for_delivery);
                }

                function showOrderInformation(order) {
                    document.getElementById('so-customer').textContent = order.customer_name || '—';
                    document.getElementById('so-reference').textContent = order.reference_number || '—';
                    document.getElementById('so-date').textContent = order.order_date || '—';
                    document.getElementById('so-terms').textContent = order.payment_terms || '—';
                    document.getElementById('so-bill-to').textContent = [order.customer_name, order.customer_address].filter(Boolean).join('\n') || '—';
                    document.getElementById('so-ship-to').textContent = [order.ship_to_name, order.ship_to_address].filter(Boolean).join('\n') || '—';
                    infoSection.classList.remove('hidden');
                }

                function renderItems() {
                    itemsBody.innerHTML = '';
                    payload.innerHTML = '';
                    if (!selectedOrder) {
                        infoSection.classList.add('hidden');
                        itemsContent.classList.add('hidden');
                        itemsEmpty.classList.remove('hidden');
                        submitButton.disabled = true;
                        updateSummary(0, 0, 0, 0, 0);
                        return;
                    }

                    showOrderInformation(selectedOrder);
                    itemsEmpty.classList.add('hidden');
                    itemsContent.classList.remove('hidden');
                    const billableItems = (selectedOrder.items || []).filter((item) => getAvailable(item) > 0);
                    noAvailableItems.classList.toggle('hidden', billableItems.length > 0);

                    billableItems.forEach((item) => {
                        const maxQuantity = getAvailable(item);
                        const quantity = Math.min(number(quantityValues.get(String(item.so_item_id))), maxQuantity);
                        quantityValues.set(String(item.so_item_id), quantity > 0 ? String(quantity) : '');
                        const row = document.createElement('tr');
                        row.className = 'border-b border-neutral-100 last:border-0';
                        row.dataset.soItemId = String(item.so_item_id);
                        row.innerHTML = `
                            <td class="px-4 py-3">
                                <div class="text-[11px] font-bold text-neutral-800">${escapeHtml(item.product_name)}</div>
                                <div class="mt-1 text-[10px] text-neutral-400">${escapeHtml(item.product_code || '—')} · ${escapeHtml(item.unit || 'unit')}</div>
                            </td>
                            <td class="px-4 py-3 text-right text-[11px] text-neutral-600">${trimQuantity(item.quantity_ordered)}</td>
                            <td class="px-4 py-3 text-right text-[11px] text-neutral-600">${trimQuantity(item.quantity_delivered)}</td>
                            <td class="px-4 py-3 text-right text-[11px] text-neutral-600">${trimQuantity(item.billed_quantity)}</td>
                            <td class="px-4 py-3 text-right text-[11px] font-semibold text-primary-500">${trimQuantity(maxQuantity)}</td>
                            <td class="px-4 py-3 text-right text-[11px] text-neutral-600">${rupiah(item.unit_price)}</td>
                            <td class="px-4 py-3 text-right">
                                <input type="number" min="0" max="${maxQuantity}" step="0.001" value="${quantity || ''}" inputmode="decimal"
                                    aria-label="Kuantitas ditagihkan untuk ${escapeHtml(item.product_name)}"
                                    class="invoice-quantity h-9 w-[112px] rounded-md border border-neutral-200 px-2 text-right text-[11px] outline-none focus:border-primary-300 focus:ring-2 focus:ring-primary-50">
                                <div class="mt-1 text-[9px] text-neutral-400">Maks. ${trimQuantity(maxQuantity)}</div>
                            </td>
                            <td class="invoice-line-subtotal px-4 py-3 text-right text-[11px] font-semibold text-neutral-800">${rupiah(0)}</td>`;
                        const input = row.querySelector('.invoice-quantity');
                        input.dataset.max = String(maxQuantity);
                        input.dataset.unitPrice = String(number(item.unit_price));
                        input.dataset.itemDiscount = String(number(item.discount_amount));
                        input.dataset.orderedQuantity = String(number(item.quantity_ordered));
                        input.addEventListener('input', () => {
                            let current = number(input.value);
                            if (current < 0) current = 0;
                            if (current > maxQuantity) {
                                current = maxQuantity;
                                input.value = String(maxQuantity);
                            }
                            quantityValues.set(String(item.so_item_id), input.value);
                            updateTotals();
                        });
                        itemsBody.appendChild(row);
                    });

                    updateTotals();
                }

                function updateSummary(subtotal, discount, tax, other, total) {
                    document.getElementById('summary-subtotal').textContent = rupiah(subtotal);
                    document.getElementById('summary-discount').textContent = rupiah(discount);
                    document.getElementById('summary-tax').textContent = rupiah(tax);
                    document.getElementById('summary-other').textContent = rupiah(other);
                    document.getElementById('summary-total').textContent = rupiah(total);
                }

                function updateTotals() {
                    let subtotal = 0;
                    itemsBody.querySelectorAll('tr').forEach((row) => {
                        const input = row.querySelector('.invoice-quantity');
                        const quantity = number(input.value);
                        const orderedQuantity = number(input.dataset.orderedQuantity);
                        const lineDiscount = orderedQuantity > 0
                            ? number(input.dataset.itemDiscount) * (quantity / orderedQuantity)
                            : 0;
                        const lineSubtotal = Math.max(0, (quantity * number(input.dataset.unitPrice)) - lineDiscount);
                        row.querySelector('.invoice-line-subtotal').textContent = rupiah(lineSubtotal);
                        subtotal += lineSubtotal;
                    });

                    const ratio = selectedOrder && number(selectedOrder.subtotal) > 0
                        ? Math.min(1, subtotal / number(selectedOrder.subtotal))
                        : 0;
                    const discount = number(selectedOrder?.discount_amount) * ratio;
                    const tax = number(selectedOrder?.tax_amount) * ratio;
                    const other = number(selectedOrder?.other_charges) * ratio;
                    const total = Math.max(0, subtotal - discount + tax + other);
                    updateSummary(subtotal, discount, tax, other, total);
                    submitButton.disabled = subtotal <= 0 || total <= 0;
                }

                function buildItemPayload() {
                    payload.innerHTML = '';
                    let index = 0;
                    let hasError = false;
                    itemsBody.querySelectorAll('tr').forEach((row) => {
                        const input = row.querySelector('.invoice-quantity');
                        const quantity = number(input.value);
                        const maximum = number(input.dataset.max);
                        if (quantity <= 0) return;
                        if (quantity > maximum) {
                            hasError = true;
                            input.setCustomValidity('Kuantitas melebihi sisa yang dapat ditagihkan.');
                            input.reportValidity();
                            return;
                        }
                        input.setCustomValidity('');
                        const soItemId = row.dataset.soItemId;
                        [
                            [`items[${index}][so_item_id]`, soItemId],
                            [`items[${index}][quantity]`, quantity.toFixed(3)],
                        ].forEach(([name, value]) => {
                            const hidden = document.createElement('input');
                            hidden.type = 'hidden';
                            hidden.name = name;
                            hidden.value = value;
                            payload.appendChild(hidden);
                        });
                        index++;
                    });
                    if (index === 0 && !hasError) {
                        window.alert('Pilih kuantitas minimal satu item yang ingin ditagihkan.');
                        return false;
                    }
                    return !hasError;
                }

                orderSelect.addEventListener('change', () => {
                    selectedOrder = orders.find((order) => String(order.sales_order_id) === orderSelect.value) || null;
                    quantityValues.clear();
                    renderItems();
                });
                typeSelect.addEventListener('change', () => renderItems());
                invoiceDate.addEventListener('change', () => {
                    dueDate.min = invoiceDate.value;
                    if (dueDate.value && dueDate.value < invoiceDate.value) dueDate.value = invoiceDate.value;
                });
                confirmCancel.addEventListener('click', () => confirmDialog.close());
                confirmSubmit.addEventListener('click', () => {
                    confirmed = true;
                    confirmDialog.close();
                    form.requestSubmit();
                });
                form.addEventListener('submit', (event) => {
                    if (confirmed) return;
                    event.preventDefault();
                    if (!buildItemPayload()) return;
                    if (typeof confirmDialog.showModal === 'function') {
                        confirmDialog.showModal();
                    } else if (window.confirm('Terbitkan invoice berdasarkan data yang sudah diisi?')) {
                        confirmed = true;
                        form.requestSubmit();
                    }
                });

                if (orderSelect.value) {
                    selectedOrder = orders.find((order) => String(order.sales_order_id) === orderSelect.value) || null;
                    renderItems();
                }
            })();
        </script>
    @endif
@endpush
