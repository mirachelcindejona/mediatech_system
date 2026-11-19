@extends('admin.layouts.layout')

@section('title', 'Invoice')
@section('page_title', 'Invoice')
@section('page_breadcrumb', 'Sales / Invoice')

@section('content')
    <div class="mb-6 flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-start">
        <div>
            <div class="mb-[7px] text-[12px] font-bold text-primary-500">Sales workspace</div>
            <h1 class="m-0 font-heading text-[27px] font-semibold leading-tight tracking-[-0.8px] text-neutral-900 sm:text-[31px]">Invoice</h1>
            <p class="mt-[6px] max-w-2xl text-[13px] leading-relaxed text-neutral-500">
                Tagihan berdasarkan barang keluar atau termin yang disepakati. Hindari penagihan ganda.
            </p>
        </div>
        @can('invoices.create')
            <a href="{{ route('invoices.create') }}"
                class="inline-flex shrink-0 items-center justify-center gap-2 rounded-lg bg-primary-500 px-[14px] py-[10px] text-[12px] font-bold text-white transition hover:bg-primary-700">
                <svg class="h-4 w-4 fill-none stroke-current" aria-hidden="true"><use href="#i-plus"></use></svg>
                Buat invoice
            </a>
        @endcan
    </div>

    <div class="mb-5 grid grid-cols-1 gap-3 sm:grid-cols-3 sm:gap-4">
        <div class="rounded-xl border border-neutral-200 bg-white p-[18px] shadow-[0_1px_2px_rgba(20,27,55,0.02)]">
            <div class="text-[11px] font-semibold text-neutral-500">Total tagihan aktif</div>
            <div class="mt-3 text-[25px] font-semibold tracking-[-0.7px] text-neutral-900 sm:text-[29px]">
                Rp {{ number_format((float) ($summary['outstanding_total'] ?? 0), 2, ',', '.') }}
            </div>
            <div class="mt-1 text-[10px] text-neutral-500">Akumulasi sisa tagihan yang belum lunas</div>
        </div>
        <div class="rounded-xl border border-neutral-200 bg-white p-[18px] shadow-[0_1px_2px_rgba(20,27,55,0.02)]">
            <div class="text-[11px] font-semibold text-neutral-500">Sudah dibayar</div>
            <div class="mt-3 text-[25px] font-semibold tracking-[-0.7px] text-success-500 sm:text-[29px]">
                Rp {{ number_format((float) ($summary['paid_total'] ?? 0), 2, ',', '.') }}
            </div>
            <div class="mt-1 text-[10px] text-neutral-500">Berdasarkan pembayaran tervalidasi</div>
        </div>
        <div class="rounded-xl border border-neutral-200 bg-white p-[18px] shadow-[0_1px_2px_rgba(20,27,55,0.02)]">
            <div class="text-[11px] font-semibold text-neutral-500">Lewat jatuh tempo</div>
            <div class="mt-3 text-[25px] font-semibold tracking-[-0.7px] text-error-500 sm:text-[29px]">
                {{ number_format((int) ($summary['overdue_count'] ?? 0), 0, ',', '.') }} invoice
            </div>
            <div class="mt-1 text-[10px] text-error-500">Perlu tindak lanjut</div>
        </div>
    </div>

    <section class="overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-[0_1px_2px_rgba(20,27,55,0.02)]">
        <form id="invoice-filter-form" method="GET" action="{{ route('invoices.index') }}"
            class="flex flex-col gap-2 border-b border-neutral-100 p-[14px] sm:flex-row sm:flex-wrap sm:items-center sm:p-[16px]">
            <label class="relative block w-full sm:w-[260px]">
                <span class="sr-only">Cari invoice atau customer</span>
                <svg class="absolute left-[11px] top-1/2 h-4 w-4 -translate-y-1/2 fill-none stroke-current text-neutral-400" aria-hidden="true"><use href="#i-search"></use></svg>
                <input id="invoice-search" type="search" name="search" value="{{ $filters['search'] ?? '' }}"
                    placeholder="Cari invoice / customer / SO…"
                    class="h-[36px] w-full rounded-lg border border-neutral-200 bg-white pl-[34px] pr-3 text-[12px] outline-none focus:border-primary-300 focus:ring-2 focus:ring-primary-50">
            </label>

            <select id="invoice-status-filter" name="status" aria-label="Filter status"
                class="h-[36px] w-full rounded-lg border border-neutral-200 bg-white px-3 text-[12px] text-neutral-600 sm:w-[180px]">
                <option value="">Semua status</option>
                <option value="Outstanding" @selected(($filters['status'] ?? '') === 'Outstanding')>Outstanding</option>
                <option value="Partially Paid" @selected(($filters['status'] ?? '') === 'Partially Paid')>Partially Paid</option>
                <option value="Paid" @selected(($filters['status'] ?? '') === 'Paid')>Paid</option>
                <option value="Overdue" @selected(($filters['status'] ?? '') === 'Overdue')>Overdue</option>
            </select>

            <a href="{{ route('invoices.index') }}"
                class="inline-flex h-[36px] items-center justify-center rounded-lg border border-neutral-200 bg-white px-3 text-[11px] font-semibold text-neutral-600 hover:bg-neutral-50">Reset</a>
            @can('invoices.export')
                <span class="ml-auto flex gap-2">
                    <a href="{{ route('invoices.export', request()->query()) }}"
                        class="inline-flex items-center gap-2 rounded-lg border border-neutral-200 bg-white px-3 h-[36px] text-[11px] font-semibold text-neutral-600 transition hover:bg-neutral-50">
                        <svg class="h-4 w-4 fill-none stroke-current" aria-hidden="true">
                            <use href="#i-download"></use>
                        </svg>
                        Ekspor Excel / CSV
                    </a>
                </span>
            @endcan
        </form>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[1050px] border-collapse text-left">
                <thead>
                    <tr class="bg-neutral-50">
                        <th class="border-b border-neutral-200 px-4 py-3 text-[11px] font-bold text-neutral-500">No. Invoice</th>
                        <th class="border-b border-neutral-200 px-4 py-3 text-[11px] font-bold text-neutral-500">Customer</th>
                        <th class="border-b border-neutral-200 px-4 py-3 text-[11px] font-bold text-neutral-500">Tanggal</th>
                        <th class="border-b border-neutral-200 px-4 py-3 text-[11px] font-bold text-neutral-500">Jatuh tempo</th>
                        <th class="border-b border-neutral-200 px-4 py-3 text-right text-[11px] font-bold text-neutral-500">Total</th>
                        <th class="border-b border-neutral-200 px-4 py-3 text-right text-[11px] font-bold text-neutral-500">Terbayar</th>
                        <th class="border-b border-neutral-200 px-4 py-3 text-right text-[11px] font-bold text-neutral-500">Sisa tagihan</th>
                        <th class="border-b border-neutral-200 px-4 py-3 text-[11px] font-bold text-neutral-500">Status</th>
                        <th class="border-b border-neutral-200 px-4 py-3 text-[11px] font-bold text-neutral-500">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($invoices as $invoice)
                        @php
                            $statusValue = $invoice->status?->value ?? $invoice->status ?? 'Outstanding';
                            $paidAmount = (float) ($invoice->paid_amount ?? 0);
                            $remainingAmount = max(0, (float) $invoice->grand_total - $paidAmount);
                            $statusClass = match ($statusValue) {
                                'Paid' => 'bg-success-50 text-success-500',
                                'Partially Paid' => 'bg-warning-50 text-warning-500',
                                'Overdue' => 'bg-error-50 text-error-500',
                                default => 'bg-neutral-100 text-neutral-700',
                            };
                        @endphp
                        <tr class="transition hover:bg-[#fcfcfe]">
                            <td class="border-b border-neutral-100 px-4 py-[13px]">
                                <a href="{{ route('invoices.show', $invoice) }}" class="font-mono text-[12px] font-bold text-primary-500 hover:underline">{{ $invoice->invoice_number }}</a>
                                <div class="mt-1 text-[10px] text-neutral-400">{{ $invoice->salesOrder?->so_number ?? 'SO tidak tersedia' }}</div>
                            </td>
                            <td class="border-b border-neutral-100 px-4 py-[13px] text-[12px] font-semibold text-neutral-700">{{ $invoice->bill_to_snapshot }}</td>
                            <td class="border-b border-neutral-100 px-4 py-[13px] text-[12px] text-neutral-600">{{ $invoice->invoice_date?->format('d M Y') ?? '—' }}</td>
                            <td class="border-b border-neutral-100 px-4 py-[13px] text-[12px] text-neutral-600">{{ $invoice->due_date?->format('d M Y') ?? '—' }}</td>
                            <td class="border-b border-neutral-100 px-4 py-[13px] text-right text-[12px] font-semibold text-neutral-700">Rp {{ number_format((float) $invoice->grand_total, 2, ',', '.') }}</td>
                            <td class="border-b border-neutral-100 px-4 py-[13px] text-right text-[12px] text-neutral-600">Rp {{ number_format($paidAmount, 2, ',', '.') }}</td>
                            <td class="border-b border-neutral-100 px-4 py-[13px] text-right text-[12px] font-semibold {{ $remainingAmount > 0 ? 'text-warning-500' : 'text-success-500' }}">Rp {{ number_format($remainingAmount, 2, ',', '.') }}</td>
                            <td class="border-b border-neutral-100 px-4 py-[13px]">
                                <span class="inline-flex items-center gap-[5px] rounded-[5px] px-2 py-1 text-[10px] font-extrabold {{ $statusClass }}"><span class="h-[5px] w-[5px] rounded-full bg-current"></span>{{ $statusValue }}</span>
                            </td>
                            <td class="border-b border-neutral-100 px-4 py-[13px]">
                                <div class="flex items-center gap-3 text-[11px] font-bold">
                                    <a href="{{ route('invoices.show', $invoice) }}" class="text-primary-500 hover:underline">Detail</a>
                                    <a href="{{ route('invoices.print', $invoice) }}" target="_blank" rel="noopener" class="text-neutral-500 hover:text-primary-500">Print</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-12 text-center">
                                <p class="text-[12px] font-bold text-neutral-500">Tidak ada invoice.</p>
                                <p class="mt-1 text-[11px] text-neutral-400">Ubah kata kunci atau filter untuk melihat data lain.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="flex flex-col gap-3 border-t border-neutral-100 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-wrap items-center gap-4 text-[10px] text-neutral-500">
                <span>Menampilkan {{ $invoices->firstItem() ?? 0 }}–{{ $invoices->lastItem() ?? 0 }} dari {{ $invoices->total() }}</span>
                <label class="flex items-center gap-2">Tampilkan
                    <select id="invoice-per-page" name="per_page" form="invoice-filter-form" class="h-8 rounded-md border border-neutral-200 bg-white px-2 text-[10px] text-neutral-600">
                        <option value="5" @selected((int) ($filters['per_page'] ?? 10) === 5)>5</option>
                        <option value="10" @selected((int) ($filters['per_page'] ?? 10) === 10)>10</option>
                        <option value="25" @selected((int) ($filters['per_page'] ?? 10) === 25)>25</option>
                        <option value="50" @selected((int) ($filters['per_page'] ?? 10) === 50)>50</option>
                    </select>
                </label>
            </div>
            <div class="flex items-center justify-end gap-2 text-[11px]">
                <a href="{{ $invoices->previousPageUrl() ?? '#' }}" @if ($invoices->onFirstPage()) aria-disabled="true" tabindex="-1" @endif
                    class="rounded-md border border-neutral-200 px-3 py-2 font-semibold {{ $invoices->onFirstPage() ? 'pointer-events-none opacity-40' : 'text-neutral-600 hover:bg-neutral-50' }}">Sebelumnya</a>
                <span class="px-1 text-neutral-500">Halaman {{ $invoices->currentPage() }}/{{ max(1, $invoices->lastPage()) }}</span>
                <a href="{{ $invoices->nextPageUrl() ?? '#' }}" @if (!$invoices->hasMorePages()) aria-disabled="true" tabindex="-1" @endif
                    class="rounded-md border border-neutral-200 px-3 py-2 font-semibold {{ $invoices->hasMorePages() ? 'text-neutral-600 hover:bg-neutral-50' : 'pointer-events-none opacity-40' }}">Berikutnya</a>
            </div>
        </div>
    </section>
@endsection

@push('scripts')
    <script>
        (() => {
            const form = document.getElementById('invoice-filter-form');
            if (!form) return;

            const search = document.getElementById('invoice-search');
            const status = document.getElementById('invoice-status-filter');
            const perPage = document.getElementById('invoice-per-page');
            let searchTimer;

            [status, perPage].forEach((field) => field?.addEventListener('change', () => form.requestSubmit()));
            search?.addEventListener('input', () => {
                window.clearTimeout(searchTimer);
                searchTimer = window.setTimeout(() => form.requestSubmit(), 350);
            });
        })();
    </script>
@endpush
