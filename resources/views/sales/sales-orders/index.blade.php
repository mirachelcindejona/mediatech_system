@extends('admin.layouts.layout')

@section('title', 'Sales Order')
@section('page_title', 'Sales Order')
@section('page_breadcrumb', 'Sales & Finance / Sales Order')

@section('content')
    <div class="mb-6">
        <div class="mb-[7px] text-[12px] font-bold text-[#0d21a1]">Sales workspace</div>
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
            <h1 class="m-0 text-[31px] font-semibold leading-tight tracking-[-0.8px] text-[#171717] sm:text-[31px]">Sales Order</h1>
            <a href="{{ \Illuminate\Support\Facades\Route::has('quotations.index') ? route('quotations.index') : url('/quotations') }}"
                class="inline-flex shrink-0 items-center justify-center gap-2 rounded-lg border border-neutral-200 bg-white px-[14px] py-[10px] text-[12px] font-bold text-neutral-600 transition hover:bg-neutral-50"><svg
                    class="h-4 w-4 fill-none stroke-current" aria-hidden="true">
                    <use href="#i-quotation"></use>
                </svg>Lihat quotation</a>
        </div>
        <p class="mt-[6px] max-w-2xl text-[14px] leading-relaxed text-[#737373]">Pesanan dari quotation yang disetujui. Produk tersimpan sebagai snapshot; Goods Out dapat memproses beberapa produk sekaligus dan SO dapat dibatalkan dalam 30 menit jika belum diproses.</p>
    </div>

    <section class="overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-[0_1px_2px_rgba(20,27,55,0.02)]">
        <div class="flex flex-col gap-2 border-b border-neutral-100 p-[14px] sm:flex-row sm:flex-wrap sm:items-center sm:p-[16px]">
            <label class="relative block w-full sm:w-[230px]"><span class="sr-only">Cari Sales Order</span><svg
                    class="absolute left-[11px] top-1/2 h-4 w-4 -translate-y-1/2 fill-none stroke-current text-neutral-400"
                    aria-hidden="true">
                    <use href="#i-search"></use>
                </svg><input id="so-search" type="search" placeholder="Cari SO / customer…"
                    class="h-[36px] w-full rounded-lg border border-neutral-200 bg-white pl-[34px] pr-3 text-[12px] outline-none focus:border-primary-300 focus:ring-2 focus:ring-primary-50"></label>

            <select id="so-status-filter" aria-label="Filter status"
                class="h-[36px] w-full rounded-lg border border-neutral-200 bg-white px-3 text-[12px] text-neutral-600 sm:w-[155px]">
                <option value="">Semua status</option>
                <option value="Open">Open</option>
                <option value="Processing">Processing</option>
                <option value="Completed">Completed</option>
                <option value="Cancelled">Cancelled</option>
            </select>

            <a href="{{ route('sales-orders.index') }}"
                class="inline-flex h-[36px] items-center justify-center rounded-lg border border-neutral-200 bg-white px-3 text-[11px] font-semibold text-neutral-600 hover:bg-neutral-50">Reset</a>

            <span class="ml-auto flex gap-2">
                <button type="button" id="btn-export-csv"
                    class="inline-flex h-[36px] items-center gap-2 rounded-lg border border-neutral-200 bg-white px-3 text-[11px] font-semibold text-neutral-600 transition hover:bg-neutral-50"><svg
                        class="h-4 w-4 fill-none stroke-current" aria-hidden="true" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v11m0 0 4-4m-4 4-4-4M5 20h14"/></svg>Ekspor Excel / CSV</button>
                <button type="button" onclick="window.print()"
                    class="inline-flex h-[36px] items-center gap-2 rounded-lg border border-neutral-200 bg-white px-3 text-[11px] font-semibold text-neutral-600 transition hover:bg-neutral-50"><svg
                        class="h-4 w-4 fill-none stroke-current" aria-hidden="true" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>Cetak / Simpan PDF</button>
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[960px] border-collapse text-left">
                <thead>
                    <tr class="bg-neutral-50">
                        <th class="border-b border-neutral-200 px-4 py-3 text-[11px] font-bold text-neutral-500">No. Sales Order</th>
                        <th class="border-b border-neutral-200 px-4 py-3 text-[11px] font-bold text-neutral-500">Customer</th>
                        <th class="border-b border-neutral-200 px-4 py-3 text-[11px] font-bold text-neutral-500">Basis quotation</th>
                        <th class="border-b border-neutral-200 px-4 py-3 text-[11px] font-bold text-neutral-500">Produk</th>
                        <th class="border-b border-neutral-200 px-4 py-3 text-[11px] font-bold text-neutral-500">Tanggal</th>
                        <th class="border-b border-neutral-200 px-4 py-3 text-[11px] font-bold text-neutral-500">Total</th>
                        <th class="border-b border-neutral-200 px-4 py-3 text-[11px] font-bold text-neutral-500">Pengiriman</th>
                        <th class="border-b border-neutral-200 px-4 py-3 text-[11px] font-bold text-neutral-500">Status</th>
                        <th class="border-b border-neutral-200 px-4 py-3 text-[11px] font-bold text-neutral-500">Aksi</th>
                    </tr>
                </thead>
                <tbody id="so-table"></tbody>
            </table>
        </div>

        <div class="flex flex-col gap-3 border-t border-neutral-100 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-wrap items-center gap-4 text-[10px] text-neutral-500"><span id="so-pg-count">Menampilkan 1–3 dari 3</span><label
                    class="flex items-center gap-2">Tampilkan <select id="so-per-page"
                        class="h-8 rounded-md border border-neutral-200 bg-white px-2 text-[10px] text-neutral-600">
                        <option value="5">5</option>
                        <option value="10" selected>10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                    </select></label></div>
            <div class="flex items-center justify-end gap-2 text-[11px]">
                <a href="#" aria-disabled="true" tabindex="-1"
                    class="pointer-events-none rounded-md border border-neutral-200 px-3 py-2 font-semibold opacity-40">Sebelumnya</a>
                <span class="px-1 text-neutral-500">Halaman 1/1</span>
                <a href="#" aria-disabled="true" tabindex="-1"
                    class="pointer-events-none rounded-md border border-neutral-200 px-3 py-2 font-semibold opacity-40">Berikutnya</a>
            </div>
        </div>
    </section>

    {{-- Modal 1: Detail Sales Order --}}
    <div id="modal-so-detail"
        class="fixed inset-0 z-50 hidden items-center justify-center bg-[#111827]/45 p-4"
        role="dialog" aria-modal="true" aria-labelledby="detail-modal-title">
        <div class="relative max-h-[90vh] w-full max-w-[640px] overflow-y-auto rounded-2xl border border-neutral-200 bg-white p-6 shadow-xl">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 id="detail-modal-title" class="m-0 text-[20px] font-semibold text-[#171717]">Detail SO-2026-030</h2>
                    <p class="mt-1 text-[12px] text-neutral-500">Rincian Sales Order, daftar produk, pengiriman, dan estimasi profit.</p>
                </div>
                <button type="button" data-close-modal="modal-so-detail"
                    class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-neutral-100 text-[13px] font-semibold text-neutral-500 transition hover:bg-neutral-200 hover:text-neutral-800"
                    aria-label="Tutup">✕</button>
            </div>

            <div class="mt-5 grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2">
                <div>
                    <div class="text-[12px] text-neutral-500">Customer</div>
                    <div id="detail-customer" class="mt-0.5 text-[14px] font-semibold text-[#171717]">-</div>
                </div>
                <div>
                    <div class="text-[12px] text-neutral-500">Quotation number</div>
                    <div id="detail-quotation" class="mt-0.5 text-[14px] font-semibold text-[#171717]">-</div>
                </div>
                <div>
                    <div class="text-[12px] text-neutral-500">Tanggal dibuat</div>
                    <div id="detail-date" class="mt-0.5 text-[14px] font-semibold text-[#171717]">-</div>
                </div>
                <div>
                    <div class="text-[12px] text-neutral-500">Dibuat oleh</div>
                    <div id="detail-created-by" class="mt-0.5 text-[14px] font-semibold text-[#171717]">-</div>
                </div>
                <div>
                    <div class="text-[12px] text-neutral-500">Status</div>
                    <div id="detail-status" class="mt-1"></div>
                </div>
                <div>
                    <div class="text-[12px] text-neutral-500">Nilai penjualan</div>
                    <div id="detail-total-value" class="mt-0.5 text-[14px] font-semibold text-[#171717]">-</div>
                </div>
                <div>
                    <div class="text-[12px] text-neutral-500">Harga modal snapshot</div>
                    <div id="detail-total-cost" class="mt-0.5 text-[14px] font-semibold text-[#171717]">-</div>
                </div>
                <div>
                    <div class="text-[12px] text-neutral-500">Estimasi profit kotor</div>
                    <div id="detail-profit" class="mt-0.5 text-[14px] font-semibold text-success-500">-</div>
                </div>
            </div>

            <div class="mt-6">
                <div class="mb-2.5 text-[13px] font-semibold text-[#171717]">Produk dalam Sales Order</div>
                <div class="overflow-x-auto rounded-lg border border-neutral-200">
                    <table class="w-full min-w-[540px] border-collapse text-left">
                        <thead>
                            <tr class="bg-neutral-50">
                                <th class="border-b border-neutral-200 px-3.5 py-2.5 text-[11px] font-bold text-neutral-500">SKU</th>
                                <th class="border-b border-neutral-200 px-3.5 py-2.5 text-[11px] font-bold text-neutral-500">Produk</th>
                                <th class="border-b border-neutral-200 px-3.5 py-2.5 text-[11px] font-bold text-neutral-500">Pesanan</th>
                                <th class="border-b border-neutral-200 px-3.5 py-2.5 text-[11px] font-bold text-neutral-500">Terkirim</th>
                                <th class="border-b border-neutral-200 px-3.5 py-2.5 text-[11px] font-bold text-neutral-500">Sisa</th>
                                <th class="border-b border-neutral-200 px-3.5 py-2.5 text-[11px] font-bold text-neutral-500">Harga jual</th>
                                <th class="border-b border-neutral-200 px-3.5 py-2.5 text-right text-[11px] font-bold text-neutral-500">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody id="detail-items-body"></tbody>
                    </table>
                </div>
            </div>

            <div id="detail-policy-notice"
                class="mt-4 rounded-xl border border-neutral-200 bg-[#f7f8fb] px-4 py-3 text-[12px] text-neutral-600">
                SO memiliki proses lanjutan. Pembatalan biasa dinonaktifkan; gunakan alur koreksi/pembalikan yang sesuai.
            </div>

            <div class="mt-6 flex items-center justify-between gap-2">
                <button type="button" id="btn-cancel-so"
                    class="hidden rounded-lg border border-error-500/30 bg-error-50 px-[14px] py-[10px] text-[12px] font-bold text-error-500 transition hover:bg-error-500 hover:text-white">
                    Batalkan SO
                </button>
                <div class="ml-auto flex items-center gap-2">
                    <button type="button" id="btn-detail-print"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-neutral-200 bg-white px-[14px] py-[10px] text-[12px] font-bold text-neutral-700 transition hover:bg-neutral-50">
                        Print SO
                    </button>
                    <button type="button" data-close-modal="modal-so-detail"
                        class="rounded-lg bg-[#0d21a1] px-[18px] py-[10px] text-[12px] font-bold text-white transition hover:bg-[#091772]">
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal 2: Goods Out dari Sales Order --}}
    <div id="modal-goods-out"
        class="fixed inset-0 z-50 hidden items-center justify-center bg-[#111827]/45 p-4"
        role="dialog" aria-modal="true" aria-labelledby="go-modal-title">
        <div class="relative max-h-[90vh] w-full max-w-[660px] overflow-y-auto rounded-2xl border border-neutral-200 bg-white p-6 shadow-xl">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 id="go-modal-title" class="m-0 text-[20px] font-semibold text-[#171717]">Goods Out dari Sales Order</h2>
                    <p class="mt-1 text-[12px] text-neutral-500">Beberapa produk dapat dikeluarkan sekaligus. Stok baru berubah setelah konfirmasi akhir.</p>
                </div>
                <button type="button" data-close-modal="modal-goods-out"
                    class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-neutral-100 text-[13px] font-semibold text-neutral-500 transition hover:bg-neutral-200 hover:text-neutral-800"
                    aria-label="Tutup">✕</button>
            </div>

            <div class="mt-5 space-y-4">
                <div>
                    <label for="go-so-select" class="block text-[12px] font-semibold text-neutral-700">Sales Order <span class="text-red-500">*</span></label>
                    <select id="go-so-select"
                        class="mt-1.5 h-[40px] w-full rounded-lg border border-neutral-200 bg-white px-3 text-[13px] text-neutral-800 outline-none focus:border-primary-300 focus:ring-2 focus:ring-primary-50"></select>
                </div>

                <div class="rounded-xl border border-neutral-200 bg-[#f7f8fb] px-4 py-3 text-[12px] leading-relaxed text-neutral-600">
                    Daftar barang diambil otomatis dari SO. Pilih satu atau beberapa produk untuk dikirim sekaligus. Kuantitas default adalah nilai terkecil antara sisa pesanan dan stok tersedia; pengiriman parsial diperbolehkan.
                </div>

                <div class="overflow-x-auto rounded-xl border border-neutral-200">
                    <table class="w-full min-w-[580px] border-collapse text-left">
                        <thead>
                            <tr class="bg-neutral-50">
                                <th class="border-b border-neutral-200 px-3 py-2.5 text-[11px] font-bold text-neutral-500">Pilih</th>
                                <th class="border-b border-neutral-200 px-3 py-2.5 text-[11px] font-bold text-neutral-500">SKU</th>
                                <th class="border-b border-neutral-200 px-3 py-2.5 text-[11px] font-bold text-neutral-500">Produk</th>
                                <th class="border-b border-neutral-200 px-3 py-2.5 text-[11px] font-bold text-neutral-500">Stok tersedia</th>
                                <th class="border-b border-neutral-200 px-3 py-2.5 text-[11px] font-bold text-neutral-500">Qty keluar</th>
                                <th class="border-b border-neutral-200 px-3 py-2.5 text-[11px] font-bold text-neutral-500">Gudang</th>
                            </tr>
                        </thead>
                        <tbody id="go-items-body"></tbody>
                    </table>
                </div>

                <div>
                    <label for="go-note" class="block text-[12px] font-semibold text-neutral-700">Catatan pengiriman</label>
                    <textarea id="go-note" rows="3" placeholder="Nomor surat jalan / catatan"
                        class="mt-1.5 block w-full resize-y rounded-lg border border-neutral-200 bg-white px-3 py-2.5 text-[12px] text-neutral-800 outline-none focus:border-primary-300 focus:ring-2 focus:ring-primary-50"></textarea>
                </div>
            </div>

            <div class="mt-6 flex items-center justify-end gap-2.5 border-t border-neutral-100 pt-4">
                <button type="button" data-close-modal="modal-goods-out"
                    class="rounded-lg border border-neutral-200 bg-white px-[16px] py-[10px] text-[12px] font-bold text-neutral-600 transition hover:bg-neutral-50">
                    Batal
                </button>
                <button type="button" id="btn-go-review"
                    class="rounded-lg bg-[#0d21a1] px-[16px] py-[10px] text-[12px] font-bold text-white transition hover:bg-[#091772]">
                    Periksa &amp; konfirmasi
                </button>
            </div>
        </div>
    </div>

    {{-- Modal 3: Konfirmasi Akhir Goods Out --}}
    <div id="modal-goods-out-confirm"
        class="fixed inset-0 z-50 hidden items-center justify-center bg-[#111827]/45 p-4"
        role="dialog" aria-modal="true" aria-labelledby="confirm-go-title">
        <div class="relative max-h-[90vh] w-full max-w-[600px] overflow-y-auto rounded-2xl border border-neutral-200 bg-white p-6 shadow-xl">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 id="confirm-go-title" class="m-0 text-[20px] font-semibold text-[#171717]">Konfirmasi akhir Goods Out</h2>
                    <p class="mt-1 text-[12px] text-neutral-500">Setelah dikonfirmasi, stok gudang berkurang dan histori pengiriman SO diperbarui.</p>
                </div>
                <button type="button" data-close-modal="modal-goods-out-confirm"
                    class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-neutral-100 text-[13px] font-semibold text-neutral-500 transition hover:bg-neutral-200 hover:text-neutral-800"
                    aria-label="Tutup">✕</button>
            </div>

            <div class="mt-5 rounded-xl border border-neutral-200 bg-[#f7f8fb] px-4 py-3 text-[12px]">
                <div>
                    <span class="text-neutral-600">SO </span>
                    <span id="confirm-go-so" class="font-bold text-[#0d21a1]">SO-2026-031</span>
                    <span id="confirm-go-customer" class="text-neutral-700">· PT Cipta Distribusi Nusantara</span>
                </div>
                <div id="confirm-go-note" class="mt-1 text-neutral-600">Catatan: —</div>
            </div>

            <div class="mt-4 overflow-x-auto rounded-xl border border-neutral-200">
                <table class="w-full border-collapse text-left">
                    <thead>
                        <tr class="bg-neutral-50">
                            <th class="border-b border-neutral-200 px-3.5 py-2.5 text-[11px] font-bold text-neutral-500">SKU</th>
                            <th class="border-b border-neutral-200 px-3.5 py-2.5 text-[11px] font-bold text-neutral-500">Produk</th>
                            <th class="border-b border-neutral-200 px-3.5 py-2.5 text-[11px] font-bold text-neutral-500">Qty</th>
                            <th class="border-b border-neutral-200 px-3.5 py-2.5 text-[11px] font-bold text-neutral-500">Gudang</th>
                        </tr>
                    </thead>
                    <tbody id="confirm-go-items"></tbody>
                </table>
            </div>

            <div class="mt-6 flex items-center justify-end gap-2.5">
                <button type="button" data-close-modal="modal-goods-out-confirm"
                    class="rounded-lg border border-neutral-200 bg-white px-[16px] py-[10px] text-[12px] font-bold text-neutral-600 transition hover:bg-neutral-50">
                    Batal
                </button>
                <button type="button" id="btn-go-submit"
                    class="rounded-lg bg-[#0d21a1] px-[16px] py-[10px] text-[12px] font-bold text-white transition hover:bg-[#091772]">
                    Konfirmasi &amp; keluarkan stok
                </button>
            </div>
        </div>
    </div>
@endsection


