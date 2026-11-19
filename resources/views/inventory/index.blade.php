@extends('admin.layouts.layout')

@section('title', 'Inventory')
@section('page_title', 'Inventory')
@section('page_breadcrumb', 'Operations / Inventory')

@section('content')
    <div id="inventory-app">
        <script type="application/json" id="inventory-bootstrap">@json($inventory)</script>
        <svg class="absolute h-0 w-0 overflow-hidden" aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg">
            <defs>
                <symbol id="inventory-icon-out" viewBox="0 0 24 24"><path d="M4 15v4h16v-4M12 15V4m0 0-4 4m4-4 4 4"/></symbol>
                <symbol id="inventory-icon-print" viewBox="0 0 24 24"><path d="M17 17h2a2 2 0 0 0 2-2v-4a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v4a2 2 0 0 0 2 2h2m2 4h6a2 2 0 0 0 2-2v-4a2 2 0 0 0-2-2H9a2 2 0 0 0-2 2v4a2 2 0 0 0 2 2Zm8-12V5a2 2 0 0 0-2-2H9a2 2 0 0 0-2 2v4h10Z"/></symbol>
            </defs>
        </svg>

        <div class="mb-6 flex flex-col justify-between gap-5 sm:flex-row sm:items-start">
            <div>
                <p class="mb-[7px] text-[12px] font-bold text-primary-500">Operations</p>
                <h1 class="ui-page-title">Inventory</h1>
                <p class="mt-[6px] max-w-2xl text-[12px] leading-relaxed text-neutral-600">Pantau stok, penerimaan barang, pengeluaran aktual, dan histori stock movement.</p>
            </div>
            <div class="flex shrink-0 flex-wrap gap-2">
                @if(auth()->user()->can('inventory.update') && auth()->user()->can('inventory.post'))
                    <x-ui.button data-open-dialog="goods-out-dialog"><svg class="ui-icon" aria-hidden="true"><use href="#inventory-icon-out"/></svg>Goods out</x-ui.button>
                @endif
                @if(auth()->user()->can('inventory.create') && auth()->user()->can('inventory.post'))
                    <x-ui.button variant="primary" data-open-dialog="goods-in-dialog"><svg class="ui-icon" aria-hidden="true"><use href="#i-plus"/></svg>Goods in</x-ui.button>
                @endif
            </div>
        </div>

        <noscript><p class="mb-5 border-l-2 border-error-500 pl-3">Aktifkan JavaScript untuk menggunakan pencarian dan form Inventory.</p></noscript>
        <p id="inventory-feedback" role="status" class="mb-5 inventory-notice" hidden></p>

        <div class="mb-5 grid grid-cols-1 gap-3 sm:grid-cols-3 sm:gap-4">
            <section class="inventory-panel inventory-stat" aria-label="SKU aktif">
                <p class="inventory-stat-label">SKU aktif</p>
                <p id="inventory-sku-count" class="inventory-stat-value">—</p>
                <p id="inventory-warehouse-count" class="inventory-stat-foot">—</p>
            </section>
            <section class="inventory-panel inventory-stat" aria-label="Total unit tersedia">
                <p class="inventory-stat-label">Total unit tersedia</p>
                <p id="inventory-stock-total" class="inventory-stat-value">—</p>
                <p class="inventory-stat-foot">Berdasarkan stock movement</p>
            </section>
            <section class="inventory-panel inventory-stat" aria-label="Stok rendah">
                <p class="inventory-stat-label">Stok rendah</p>
                <p id="inventory-low-count" class="inventory-stat-value">—</p>
                <p class="inventory-stat-foot flex items-center gap-[5px]"><span class="h-[5px] w-[5px] rounded-full bg-error-500" aria-hidden="true"></span>Perlu restock</p>
            </section>
        </div>

        <section class="inventory-panel" aria-labelledby="inventory-stock-heading">
            <div class="flex flex-col justify-between gap-3 border-b border-neutral-100 px-[19px] py-[17px] sm:flex-row sm:items-center">
                <div>
                    <h2 id="inventory-stock-heading" class="ui-section-title">Posisi stok</h2>
                    <p class="mt-1 text-[11px] text-neutral-500">Stok diperbarui hanya setelah Goods In / Goods Out dikonfirmasi.</p>
                </div>
                <x-ui.button size="sm" data-open-dialog="movements-dialog">Lihat stock movement</x-ui.button>
            </div>
            <x-ui.filter-bar class="inventory-filters">
                <div class="w-full sm:w-[220px]">
                    <label for="inventory-search" class="sr-only">Cari SKU atau nama barang</label>
                    <x-ui.input type="search" id="inventory-search" class="ui-filter-input" placeholder="Cari SKU / nama barang…"/>
                </div>
                <div class="w-full sm:w-[165px]">
                    <label for="inventory-warehouse" class="sr-only">Filter gudang</label>
                    <x-ui.select id="inventory-warehouse" class="ui-filter-input">
                        <option value="">Semua gudang</option>
                    </x-ui.select>
                </div>
            </x-ui.filter-bar>
            @can('inventory.export')
                <x-ui.table-tools target="inventory-stock-rows"/>
            @endcan
            <x-ui.table caption="Posisi stok per SKU dan gudang">
                <thead><tr>
                    <th scope="col">SKU</th><th scope="col">Nama barang</th><th scope="col">Kategori</th><th scope="col">Gudang</th>
                    <th scope="col">Stok tersedia</th><th scope="col">Min. stok</th><th scope="col">Status</th>
                </tr></thead>
                <tbody id="inventory-stock-rows"><tr><td colspan="7"><x-ui.empty-state>Memuat posisi stok…</x-ui.empty-state></td></tr></tbody>
            </x-ui.table>
            <x-ui.pagination prefix="inventory-stock"/>
        </section>

        @if(auth()->user()->can('inventory.create') && auth()->user()->can('inventory.post'))
            <x-ui.modal id="goods-in-dialog" title="Catat Goods In" description="Stok bertambah setelah barang benar-benar diterima.">
                <form id="goods-in-form" novalidate>
                    <div class="ui-dialog-body grid grid-cols-1 gap-x-[13px] gap-y-[14px] sm:grid-cols-2">
                        <div class="ui-field"><label for="receipt-product">Barang</label><x-ui.select id="receipt-product" name="product_id" required/></div>
                        <div class="ui-field"><label for="receipt-warehouse">Gudang</label><x-ui.select id="receipt-warehouse" name="warehouse_id" required/></div>
                        <div class="ui-field"><label for="receipt-reference">Nomor dokumen penerimaan</label><x-ui.input id="receipt-reference" name="reference" placeholder="Kosongkan untuk nomor otomatis" maxlength="50"/></div>
                        <div class="ui-field"><label for="receipt-quantity">Jumlah diterima</label><x-ui.input type="number" id="receipt-quantity" name="quantity" min="0.001" step="0.001" placeholder="0" value="1" required/></div>
                        <div class="ui-field"><label for="receipt-serial">Nomor serial (opsional)</label><x-ui.textarea id="receipt-serial" name="serial" rows="1" class="min-h-0" placeholder="Kosongkan untuk serial otomatis; pisahkan serial manual dengan koma atau baris baru"/></div>
                        <div class="ui-field"><label for="receipt-notes">Catatan</label><x-ui.input id="receipt-notes" name="notes" placeholder="Keterangan singkat" maxlength="1000"/></div>
                        <p id="receipt-error" role="alert" class="border-l-2 border-error-500 pl-3 sm:col-span-2" hidden></p>
                    </div>
                    <div class="ui-dialog-footer"><x-ui.button data-close-dialog>Batal</x-ui.button><x-ui.button variant="primary" type="submit">Konfirmasi penerimaan</x-ui.button></div>
                </form>
            </x-ui.modal>
        @endif

        @if(auth()->user()->can('inventory.update') && auth()->user()->can('inventory.post'))
            <x-ui.modal id="goods-out-dialog" title="Goods Out dari Sales Order" description="Beberapa produk dapat dikeluarkan sekaligus. Stok baru berubah setelah konfirmasi akhir.">
                <form id="goods-out-form" novalidate>
                    <div class="ui-dialog-body space-y-[14px]">
                        <div class="ui-field"><label for="issue-order">Sales Order *</label><x-ui.select id="issue-order" name="sales_order" required/></div>
                        <p class="inventory-notice">Daftar barang diambil otomatis dari SO. Pilih satu atau beberapa produk untuk dikirim sekaligus. Kuantitas default adalah nilai terkecil antara sisa pesanan dan stok tersedia; pengiriman parsial diperbolehkan.</p>
                        @can('inventory.export')
                            <x-ui.table-tools target="issue-lines"/>
                        @endcan
                        <div>
                            <x-ui.table caption="Barang Sales Order yang akan dikeluarkan">
                                <thead><tr><th scope="col">Pilih</th><th scope="col">SKU</th><th scope="col">Produk</th><th scope="col">Stok tersedia</th><th scope="col">Qty keluar</th><th scope="col">Gudang</th></tr></thead>
                                <tbody id="issue-lines"></tbody>
                            </x-ui.table>
                            <x-ui.pagination prefix="inventory-issue"/>
                        </div>
                        <div class="ui-field"><label for="issue-notes">Catatan pengiriman</label><x-ui.textarea id="issue-notes" name="notes" rows="3" maxlength="1000" placeholder="Nomor surat jalan / catatan"/></div>
                        <p id="issue-error" role="alert" class="border-l-2 border-error-500 pl-3" hidden></p>
                    </div>
                    <div class="ui-dialog-footer"><x-ui.button data-close-dialog>Batal</x-ui.button><x-ui.button variant="primary" type="submit">Periksa &amp; konfirmasi</x-ui.button></div>
                </form>
            </x-ui.modal>

            <x-ui.confirm-modal id="goods-out-confirm-dialog" title="Konfirmasi akhir Goods Out" description="Setelah dikonfirmasi, stok gudang berkurang dan histori pengiriman SO diperbarui." :confirm-label="'Konfirmasi & keluarkan stok'">
                <div class="inventory-notice break-words">
                    SO <strong id="issue-confirm-order"></strong> · <span id="issue-confirm-customer"></span>
                    <p class="whitespace-pre-wrap">Catatan: <span id="issue-confirm-notes"></span></p>
                </div>
                @can('inventory.export')
                    <x-ui.table-tools target="issue-confirm-rows"/>
                @endcan
                <div class="mt-3">
                    <x-ui.table caption="Rincian konfirmasi Goods Out">
                        <thead><tr><th scope="col">SKU</th><th scope="col">Produk</th><th scope="col">Qty</th><th scope="col">Gudang</th></tr></thead>
                        <tbody id="issue-confirm-rows"></tbody>
                    </x-ui.table>
                    <x-ui.pagination prefix="inventory-confirm"/>
                </div>
                <p id="issue-confirm-error" role="alert" class="mt-3 border-l-2 border-error-500 pl-3" hidden></p>
            </x-ui.confirm-modal>
        @endif

        <x-ui.modal id="movements-dialog" title="Stock Movement" description="Histori penerimaan dan pengeluaran stok.">
            <div class="ui-dialog-body">
                <x-ui.filter-bar class="mb-3">
                    <label for="movement-search" class="sr-only">Cari referensi atau detail pergerakan</label>
                    <x-ui.input type="search" id="movement-search" class="ui-filter-input" placeholder="Cari referensi / detail pergerakan…"/>
                </x-ui.filter-bar>
                @can('inventory.export')
                    <x-ui.table-tools target="movement-rows"/>
                @endcan
                <x-ui.table caption="Histori pergerakan stok">
                    <thead><tr><th scope="col">Waktu</th><th scope="col">Referensi</th><th scope="col">Aksi</th><th scope="col">Detail</th></tr></thead>
                    <tbody id="movement-rows"></tbody>
                </x-ui.table>
                <x-ui.pagination prefix="inventory-movement"/>
            </div>
            <div class="ui-dialog-footer"><x-ui.button data-close-dialog>Tutup</x-ui.button></div>
        </x-ui.modal>
        @include('inventory.partials.templates')
    </div>
@endsection

@push('scripts')
    @include('inventory.partials.scripts')
@endpush
