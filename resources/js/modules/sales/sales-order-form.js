document.addEventListener('DOMContentLoaded', () => {
    const tbody = document.getElementById('so-table');
    if (!tbody) return;

    const salesOrders = [
        {
            id: 'SO-2026-031',
            customer: 'PT Cipta Distribusi Nusantara',
            customerAddress: 'Jl. Industri Raya Blok C-12, Cikarang, Bekasi',
            quotation: 'QT-2026-017',
            date: '17 Okt 2026',
            createdBy: 'Rafli Ahmad',
            totalValue: 22650000,
            totalCost: 14500000,
            status: 'Processing',
            canCancelWithin30Min: false,
            items: [
                {
                    sku: 'PRD-011',
                    name: 'Kamera CCTV Hikvision 2MP',
                    qty: 16,
                    sent: 10,
                    unit: 'Unit',
                    stock: 14,
                    price: 800000,
                    subtotal: 12800000,
                    warehouse: 'Gudang Utama'
                },
                {
                    sku: 'PRD-012',
                    name: 'NVR Hikvision 16 Channel',
                    qty: 1,
                    sent: 0,
                    unit: 'Unit',
                    stock: 11,
                    price: 3450000,
                    subtotal: 3450000,
                    warehouse: 'Gudang Utama'
                },
                {
                    sku: 'SRV-004',
                    name: 'Instalasi jaringan networking (CCTV, access door)',
                    qty: 16,
                    sent: 0,
                    unit: 'Titik',
                    stock: 99,
                    price: 400000,
                    subtotal: 6400000,
                    warehouse: 'Gudang Utama'
                }
            ]
        },
        {
            id: 'SO-2026-030',
            customer: 'PT Energi Samudra Migas',
            customerAddress: 'Gedung Graha Energi Lt. 8, Jakarta Selatan',
            quotation: 'QT-2026-011',
            date: '12 Okt 2026',
            createdBy: 'Rafli Ahmad',
            totalValue: 48500000,
            totalCost: 0,
            status: 'Completed',
            canCancelWithin30Min: false,
            items: []
        },
        {
            id: 'SO-2026-029',
            customer: 'PT Maju Retail Indonesia',
            customerAddress: 'Jl. Soekarno Hatta No. 412, Bandung',
            quotation: 'QT-2026-009',
            date: '08 Okt 2026',
            createdBy: 'Rafli Ahmad',
            totalValue: 71900000,
            totalCost: 49200000,
            status: 'Open',
            canCancelWithin30Min: true,
            items: [
                {
                    sku: 'PRD-019',
                    name: 'Paket Server Rack & Switch Managed 24-Port',
                    qty: 2,
                    sent: 0,
                    unit: 'Set',
                    stock: 5,
                    price: 21950000,
                    subtotal: 43900000,
                    warehouse: 'Gudang Utama'
                },
                {
                    sku: 'PRD-022',
                    name: 'Kamera CCTV Dome Indoor 4MP',
                    qty: 14,
                    sent: 0,
                    unit: 'Unit',
                    stock: 20,
                    price: 2000000,
                    subtotal: 28000000,
                    warehouse: 'Gudang Utama'
                }
            ]
        }
    ];

    const searchInput = document.getElementById('so-search');
    const statusSelect = document.getElementById('so-status-filter');
    const countLabel = document.getElementById('so-pg-count');
    let pendingGoodsOut = null;
    let activeDetailSo = null;

    function formatRupiah(amount) {
        return 'Rp ' + Number(amount || 0).toLocaleString('id-ID');
    }

    function formatDecimal(amount) {
        return Number(amount || 0).toLocaleString('id-ID', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function statusBadge(status) {
        const styles = {
            Open: 'bg-primary-50 text-primary-500',
            Processing: 'bg-warning-50 text-warning-500',
            Completed: 'bg-success-50 text-success-500',
            Cancelled: 'bg-error-50 text-error-500'
        };
        const cls = styles[status] || 'bg-neutral-50 text-neutral-500';
        return `<span class="inline-flex items-center gap-[5px] rounded-[5px] px-2 py-1 text-[10px] font-semibold ${cls}"><span class="h-[5px] w-[5px] rounded-full bg-current"></span>${status}</span>`;
    }

    function deliverySummary(so) {
        if (so.id === 'SO-2026-030') return '4 / 4 titik';
        if (!so.items.length) return '0 / 0 item';
        const totalQty = so.items.reduce((sum, item) => sum + item.qty, 0);
        const sentQty = so.items.reduce((sum, item) => sum + item.sent, 0);
        return `${sentQty} / ${totalQty} item`;
    }

    function productSummary(so) {
        if (!so.items.length) return 'Item belum tersedia';
        return so.items.map((item) => `${item.name} (${item.qty} ${item.unit})`).join(', ');
    }

    function openModal(id) {
        const modal = document.getElementById(id);
        if (!modal) return;
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    function closeModal(id) {
        const modal = document.getElementById(id);
        if (!modal) return;
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    function printSalesOrderDocument(so) {
        const printWindow = window.open('', '_blank', 'width=900,height=700');
        if (!printWindow) {
            alert('Izinkan pop-up browser untuk mencetak dokumen Sales Order.');
            return;
        }

        const itemRows = so.items.length
            ? so.items.map((item, index) => `
                <tr>
                    <td style="padding:8px 10px;border:1px solid #d4d4d8;text-align:center;">${index + 1}</td>
                    <td style="padding:8px 10px;border:1px solid #d4d4d8;">${item.sku}</td>
                    <td style="padding:8px 10px;border:1px solid #d4d4d8;">${item.name}</td>
                    <td style="padding:8px 10px;border:1px solid #d4d4d8;text-align:center;">${item.qty} ${item.unit}</td>
                    <td style="padding:8px 10px;border:1px solid #d4d4d8;text-align:right;">${formatRupiah(item.price)}</td>
                    <td style="padding:8px 10px;border:1px solid #d4d4d8;text-align:right;font-weight:600;">${formatRupiah(item.subtotal)}</td>
                </tr>
            `).join('')
            : `
                <tr>
                    <td colspan="6" style="padding:12px;border:1px solid #d4d4d8;text-align:center;color:#71717a;">
                        Snapshot item tidak tersedia untuk dokumen ini.
                    </td>
                </tr>
            `;

        printWindow.document.write(`
            <!DOCTYPE html>
            <html lang="id">
            <head>
                <meta charset="UTF-8">
                <title>Cetak ${so.id}</title>
                <style>
                    @page { size: A4; margin: 18mm; }
                    body { font-family: Arial, Helvetica, sans-serif; color: #18181b; font-size: 12px; line-height: 1.5; margin: 0; }
                    .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #0d21a1; padding-bottom: 12px; margin-bottom: 20px; }
                    .brand { font-size: 20px; font-weight: 700; color: #0d21a1; letter-spacing: 0.5px; }
                    .doc-title { text-align: right; }
                    .doc-title h1 { margin: 0; font-size: 18px; letter-spacing: 0.5px; }
                    .meta { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px; }
                    .box { border: 1px solid #e4e4e7; border-radius: 6px; padding: 10px 12px; }
                    .label { font-size: 10px; color: #71717a; text-transform: uppercase; font-weight: 700; }
                    .val { font-size: 12px; font-weight: 600; margin-top: 2px; }
                    table { width: 100%; border-collapse: collapse; margin-top: 8px; }
                    th { background: #f4f4f5; border: 1px solid #d4d4d8; padding: 8px 10px; font-size: 11px; text-align: left; }
                    .total-box { margin-top: 14px; display: flex; justify-content: flex-end; }
                    .total-table { width: 280px; }
                    .total-table td { padding: 6px 10px; border: 1px solid #d4d4d8; }
                    .signatures { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-top: 48px; text-align: center; }
                    .sig-line { margin-top: 64px; border-top: 1px solid #71717a; padding-top: 4px; font-size: 11px; }
                </style>
            </head>
            <body>
                <div class="header">
                    <div>
                        <div class="brand">MEDITECH</div>
                        <div style="color:#52525b;font-size:11px;">Solusi Peralatan Medis &amp; Sistem Integrasi</div>
                    </div>
                    <div class="doc-title">
                        <h1>SALES ORDER</h1>
                        <div style="font-weight:700;color:#0d21a1;">${so.id}</div>
                        <div style="font-size:11px;color:#52525b;">Status: ${so.status}</div>
                    </div>
                </div>

                <div class="meta">
                    <div class="box">
                        <div class="label">Customer</div>
                        <div class="val">${so.customer}</div>
                        <div style="font-size:11px;color:#52525b;margin-top:4px;">${so.customerAddress || '-'}</div>
                    </div>
                    <div class="box">
                        <div class="label">Informasi Dokumen</div>
                        <div style="margin-top:4px;">Basis Quotation: <strong>${so.quotation}</strong></div>
                        <div>Tanggal SO: <strong>${so.date}</strong></div>
                        <div>Dibuat oleh: <strong>${so.createdBy}</strong></div>
                    </div>
                </div>

                <table>
                    <thead>
                        <tr>
                            <th style="width:36px;text-align:center;">No</th>
                            <th style="width:90px;">SKU</th>
                            <th>Deskripsi Produk / Jasa (Snapshot)</th>
                            <th style="width:90px;text-align:center;">Qty</th>
                            <th style="width:120px;text-align:right;">Harga Satuan</th>
                            <th style="width:130px;text-align:right;">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${itemRows}
                    </tbody>
                </table>

                <div class="total-box">
                    <table class="total-table">
                        <tr>
                            <td style="background:#f4f4f5;font-weight:700;">Grand Total</td>
                            <td style="text-align:right;font-weight:700;font-size:13px;">${formatRupiah(so.totalValue)}</td>
                        </tr>
                    </table>
                </div>

                <div style="margin-top:16px;font-size:10px;color:#71717a;">
                    * Dokumen ini dicetak berdasarkan snapshot transaksi pada saat konversi Quotation menjadi Sales Order.
                </div>

                <div class="signatures">
                    <div>
                        <div>Dibuat Oleh,</div>
                        <div class="sig-line">${so.createdBy} (Sales Admin)</div>
                    </div>
                    <div>
                        <div>Disetujui Oleh,</div>
                        <div class="sig-line">Manager Operasional</div>
                    </div>
                </div>

                <script>
                    window.onload = function () {
                        window.print();
                    };
                </script>
            </body>
            </html>
        `);
        printWindow.document.close();
    }

    document.querySelectorAll('[data-close-modal]').forEach((btn) => {
        btn.addEventListener('click', () => closeModal(btn.dataset.closeModal));
    });

    ['modal-so-detail', 'modal-goods-out', 'modal-goods-out-confirm'].forEach((id) => {
        const el = document.getElementById(id);
        el?.addEventListener('click', (e) => {
            if (e.target === el) closeModal(id);
        });
    });

    function renderTable() {
        const query = (searchInput?.value || '').toLowerCase().trim();
        const status = statusSelect?.value || '';

        const filtered = salesOrders.filter((so) => {
            const matchStatus = !status || so.status === status;
            const searchHaystack = `${so.id} ${so.customer} ${so.quotation} ${productSummary(so)}`.toLowerCase();
            return matchStatus && searchHaystack.includes(query);
        });

        if (countLabel) {
            countLabel.textContent = filtered.length
                ? `Menampilkan 1–${filtered.length} dari ${filtered.length}`
                : 'Menampilkan 0–0 dari 0';
        }

        if (!filtered.length) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="9" class="px-4 py-12 text-center">
                        <p class="text-[12px] font-bold text-neutral-500">Tidak ada data sales order.</p>
                        <p class="mt-1 text-[11px] text-neutral-400">Ubah kata kunci atau filter untuk melihat data lain.</p>
                    </td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = filtered.map((so) => {
            const canGoodsOut = so.status === 'Open' || so.status === 'Processing';
            return `
                <tr class="transition hover:bg-[#fcfcfe]">
                    <td class="border-b border-neutral-100 px-4 py-[13px] text-[12px] font-sans font-bold text-primary-500">${so.id}</td>
                    <td class="border-b border-neutral-100 px-4 py-[13px] text-[12px] text-neutral-700">${so.customer}</td>
                    <td class="border-b border-neutral-100 px-4 py-[13px] text-[12px] text-neutral-600">${so.quotation}</td>
                    <td class="border-b border-neutral-100 px-4 py-[13px] text-[12px] text-neutral-600 max-w-[260px] truncate" title="${productSummary(so)}">${productSummary(so)}</td>
                    <td class="border-b border-neutral-100 px-4 py-[13px] text-[12px] text-neutral-600 whitespace-nowrap">${so.date}</td>
                    <td class="border-b border-neutral-100 px-4 py-[13px] text-[12px] font-sans text-neutral-700 whitespace-nowrap">${formatDecimal(so.totalValue)}</td>
                    <td class="border-b border-neutral-100 px-4 py-[13px] text-[12px] text-neutral-600 whitespace-nowrap">${deliverySummary(so)}</td>
                    <td class="border-b border-neutral-100 px-4 py-[13px] text-[12px]">${statusBadge(so.status)}</td>
                    <td class="border-b border-neutral-100 px-4 py-[13px] text-[12px] whitespace-nowrap">
                        <div class="flex items-center gap-3">
                            <button type="button" data-action="detail" data-id="${so.id}"
                                class="text-[11px] font-semibold text-[#0d21a1] hover:underline">Detail</button>
                            <button type="button" data-action="print" data-id="${so.id}"
                                class="text-[11px] font-semibold text-[#0d21a1] hover:underline">Print</button>
                            ${canGoodsOut ? `
                                <button type="button" data-action="goodsout" data-id="${so.id}"
                                    class="text-[11px] font-semibold text-[#0d21a1] hover:underline">Goods Out</button>
                            ` : ''}
                        </div>
                    </td>
                </tr>
            `;
        }).join('');
    }

    function openDetailModal(so) {
        activeDetailSo = so;
        const profit = so.totalValue - so.totalCost;
        document.getElementById('detail-modal-title').textContent = `Detail ${so.id}`;
        document.getElementById('detail-customer').textContent = so.customer;
        document.getElementById('detail-quotation').textContent = so.quotation;
        document.getElementById('detail-date').textContent = so.date;
        document.getElementById('detail-created-by').textContent = so.createdBy;
        document.getElementById('detail-status').innerHTML = statusBadge(so.status);
        document.getElementById('detail-total-value').textContent = formatRupiah(so.totalValue);
        document.getElementById('detail-total-cost').textContent = formatRupiah(so.totalCost);
        document.getElementById('detail-profit').textContent = formatRupiah(profit);

        const itemsBody = document.getElementById('detail-items-body');
        if (!so.items.length) {
            itemsBody.innerHTML = `
                <tr>
                    <td colspan="7" class="px-4 py-5 text-left text-[12px] text-neutral-500">Tidak ada detail produk.</td>
                </tr>
            `;
        } else {
            itemsBody.innerHTML = so.items.map((item) => {
                const remaining = Math.max(0, item.qty - item.sent);
                return `
                    <tr>
                        <td class="border-b border-neutral-100 px-3.5 py-2.5 text-[12px] font-semibold text-neutral-700">${item.sku}</td>
                        <td class="border-b border-neutral-100 px-3.5 py-2.5 text-[12px] text-neutral-700">${item.name}</td>
                        <td class="border-b border-neutral-100 px-3.5 py-2.5 text-[12px] text-neutral-600">${item.qty} ${item.unit}</td>
                        <td class="border-b border-neutral-100 px-3.5 py-2.5 text-[12px] text-primary-500">${item.sent} ${item.unit}</td>
                        <td class="border-b border-neutral-100 px-3.5 py-2.5 text-[12px] text-neutral-600">${remaining} ${item.unit}</td>
                        <td class="border-b border-neutral-100 px-3.5 py-2.5 text-[12px] text-neutral-600">${formatRupiah(item.price)}</td>
                        <td class="border-b border-neutral-100 px-3.5 py-2.5 text-right text-[12px] font-semibold text-neutral-800">${formatRupiah(item.subtotal)}</td>
                    </tr>
                `;
            }).join('');
        }

        const policyNotice = document.getElementById('detail-policy-notice');
        const cancelBtn = document.getElementById('btn-cancel-so');
        if (so.status === 'Open' && so.canCancelWithin30Min) {
            policyNotice.textContent = 'SO masih dalam batas waktu 30 menit dan belum memiliki Goods Out. Pembatalan biasa diizinkan.';
            cancelBtn.classList.remove('hidden');
            cancelBtn.onclick = () => {
                so.status = 'Cancelled';
                closeModal('modal-so-detail');
                renderTable();
            };
        } else {
            policyNotice.textContent = 'SO memiliki proses lanjutan. Pembatalan biasa dinonaktifkan; gunakan alur koreksi/pembalikan yang sesuai.';
            cancelBtn.classList.add('hidden');
        }

        openModal('modal-so-detail');
    }

    document.getElementById('btn-detail-print')?.addEventListener('click', () => {
        if (activeDetailSo) {
            printSalesOrderDocument(activeDetailSo);
        }
    });

    function openGoodsOutModal(so) {
        const soSelect = document.getElementById('go-so-select');
        soSelect.innerHTML = salesOrders
            .filter((item) => item.status === 'Open' || item.status === 'Processing')
            .map((item) => `<option value="${item.id}" ${item.id === so.id ? 'selected' : ''}>${item.id} — ${item.customer}</option>`)
            .join('');

        renderGoodsOutItems(so);
        document.getElementById('go-note').value = '';
        openModal('modal-goods-out');
    }

    function renderGoodsOutItems(so) {
        const goItemsBody = document.getElementById('go-items-body');
        goItemsBody.innerHTML = so.items.map((item, idx) => {
            const remaining = Math.max(0, item.qty - item.sent);
            const defaultQty = Math.min(remaining, item.stock);
            const checked = remaining > 0 && idx === 0 ? 'checked' : '';
            return `
                <tr class="border-b border-neutral-100 align-top">
                    <td class="px-3 py-3.5">
                        <input type="checkbox" data-go-check="${idx}" ${checked} ${remaining === 0 ? 'disabled' : ''}
                            class="h-4 w-4 rounded border-neutral-300 text-[#0d21a1] focus:ring-primary-300">
                    </td>
                    <td class="px-3 py-3.5 text-[12px] text-neutral-700 whitespace-nowrap">${item.sku}</td>
                    <td class="px-3 py-3.5 text-[12px]">
                        <div class="font-medium text-neutral-800">${item.name}</div>
                        <div class="mt-0.5 text-[11px] text-neutral-400">Sisa pesanan: ${remaining} ${item.unit}</div>
                    </td>
                    <td class="px-3 py-3.5 text-[12px] text-neutral-700 whitespace-nowrap">${item.stock} ${item.unit.toLowerCase()}</td>
                    <td class="px-3 py-3.5">
                        <input type="number" min="1" max="${defaultQty}" value="${defaultQty}" data-go-qty="${idx}"
                            ${remaining === 0 ? 'disabled' : ''}
                            class="h-[34px] w-[76px] rounded-lg border border-neutral-200 bg-white px-2.5 text-[12px] text-neutral-800 outline-none focus:border-primary-300">
                    </td>
                    <td class="px-3 py-3.5">
                        <select data-go-warehouse="${idx}"
                            class="h-[34px] rounded-lg border border-neutral-200 bg-white px-2.5 text-[12px] text-neutral-700 outline-none focus:border-primary-300">
                            <option value="Gudang Utama" selected>Gudang Utama</option>
                            <option value="Gudang Cabang">Gudang Cabang</option>
                        </select>
                    </td>
                </tr>
            `;
        }).join('');
    }

    document.getElementById('go-so-select')?.addEventListener('change', (e) => {
        const selected = salesOrders.find((item) => item.id === e.target.value);
        if (selected) renderGoodsOutItems(selected);
    });

    document.getElementById('btn-go-review')?.addEventListener('click', () => {
        const soId = document.getElementById('go-so-select').value;
        const so = salesOrders.find((item) => item.id === soId);
        if (!so) return;

        const chosenItems = [];
        document.querySelectorAll('#go-items-body input[data-go-check]').forEach((checkbox) => {
            if (!checkbox.checked) return;
            const idx = Number(checkbox.dataset.goCheck);
            const item = so.items[idx];
            const qtyInput = document.querySelector(`#go-items-body input[data-go-qty="${idx}"]`);
            const whSelect = document.querySelector(`#go-items-body select[data-go-warehouse="${idx}"]`);
            const qty = Math.max(0, Number(qtyInput?.value) || 0);
            if (qty > 0) {
                chosenItems.push({
                    idx,
                    sku: item.sku,
                    name: item.name,
                    qty,
                    unit: item.unit,
                    warehouse: whSelect?.value || 'Gudang Utama'
                });
            }
        });

        if (!chosenItems.length) {
            alert('Pilih minimal satu produk dengan kuantitas keluar lebih dari 0.');
            return;
        }

        const note = document.getElementById('go-note').value.trim();
        pendingGoodsOut = { soId: so.id, customer: so.customer, note, items: chosenItems };

        document.getElementById('confirm-go-so').textContent = so.id;
        document.getElementById('confirm-go-customer').textContent = `· ${so.customer}`;
        document.getElementById('confirm-go-note').textContent = `Catatan: ${note || '—'}`;
        document.getElementById('confirm-go-items').innerHTML = chosenItems.map((it) => `
            <tr class="border-b border-neutral-100">
                <td class="px-3.5 py-2.5 text-[12px] text-neutral-700">${it.sku}</td>
                <td class="px-3.5 py-2.5 text-[12px] text-neutral-800">${it.name}</td>
                <td class="px-3.5 py-2.5 text-[12px] text-neutral-700">${it.qty} ${it.unit}</td>
                <td class="px-3.5 py-2.5 text-[12px] text-neutral-700">${it.warehouse}</td>
            </tr>
        `).join('');

        closeModal('modal-goods-out');
        openModal('modal-goods-out-confirm');
    });

    document.getElementById('btn-go-submit')?.addEventListener('click', () => {
        if (!pendingGoodsOut) return;
        const so = salesOrders.find((item) => item.id === pendingGoodsOut.soId);
        if (!so) return;

        pendingGoodsOut.items.forEach((chosen) => {
            const target = so.items[chosen.idx];
            target.sent = Math.min(target.qty, target.sent + chosen.qty);
            target.stock = Math.max(0, target.stock - chosen.qty);
        });

        const allCompleted = so.items.every((item) => item.sent >= item.qty);
        const anySent = so.items.some((item) => item.sent > 0);
        if (allCompleted) so.status = 'Completed';
        else if (anySent) so.status = 'Processing';

        pendingGoodsOut = null;
        closeModal('modal-goods-out-confirm');
        renderTable();
    });

    tbody.addEventListener('click', (e) => {
        const btn = e.target.closest('button[data-action]');
        if (!btn) return;
        const so = salesOrders.find((item) => item.id === btn.dataset.id);
        if (!so) return;

        if (btn.dataset.action === 'detail') openDetailModal(so);
        if (btn.dataset.action === 'print') printSalesOrderDocument(so);
        if (btn.dataset.action === 'goodsout') openGoodsOutModal(so);
    });

    document.getElementById('btn-export-csv')?.addEventListener('click', () => {
        const rows = [['No. Sales Order', 'Customer', 'Basis quotation', 'Tanggal', 'Total', 'Pengiriman', 'Status']];
        salesOrders.forEach((so) => {
            rows.push([so.id, so.customer, so.quotation, so.date, formatDecimal(so.totalValue), deliverySummary(so), so.status]);
        });
        const csv = rows.map((line) => line.map((v) => `"${v}"`).join(',')).join('\n');
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'sales-orders.csv';
        link.click();
    });

    searchInput?.addEventListener('input', renderTable);
    statusSelect?.addEventListener('change', renderTable);
    renderTable();
});
