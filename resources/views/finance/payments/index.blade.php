@extends('admin.layouts.layout')

@section('title', 'Payment')
@section('page_title', 'Payment')
@section('page_breadcrumb', 'Finance / Payment')

@section('content')
    @php
        $isFinanceRole = auth()->user()?->roles?->contains('role_name', 'Finance') ?? false;
        $authorizationLabel = $isFinanceRole ? 'Validasi' : 'Otorisasi';
    @endphp

    <div class="mb-6 flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-start">
        <div>
            <div class="mb-[7px] text-[12px] font-bold text-[#0d21a1]">Finance workspace</div>
            <h1 class="m-0 font-heading text-[28px] font-semibold leading-tight tracking-[-0.8px] text-[#171717] sm:text-[31px]">Payment</h1>
            <p class="mt-[6px] max-w-2xl text-[13px] leading-relaxed text-[#737373]">Catat DP, cicilan, dan pembayaran parsial. Pembayaran baru menjadi penerimaan final setelah tervalidasi.</p>
        </div>
        @can('payments.create')
            <a href="{{ route('payments.create') }}"
                class="inline-flex shrink-0 items-center justify-center gap-2 rounded-lg bg-[#0d21a1] px-[14px] py-[10px] text-[12px] font-bold text-white transition hover:bg-[#091772]">
                <svg class="h-4 w-4 fill-none stroke-current" aria-hidden="true"><use href="#i-plus"></use></svg>
                Catat pembayaran
            </a>
        @endcan
    </div>

    <div class="mb-5 grid grid-cols-1 gap-3 sm:grid-cols-3 sm:gap-4">
        <div class="rounded-xl border border-neutral-200 bg-white p-[18px] shadow-[0_1px_2px_rgba(20,27,55,0.02)]">
            <div class="text-[11px] font-semibold text-neutral-500">Pembayaran bulan ini</div>
            <div class="mt-3 text-[28px] font-semibold tracking-[-0.7px] text-success-500">Rp {{ number_format($summary['validated_this_month'], 0, ',', '.') }}</div>
            <div class="mt-1 text-[10px] text-neutral-500">Sudah tervalidasi</div>
        </div>
        <div class="rounded-xl border border-neutral-200 bg-white p-[18px] shadow-[0_1px_2px_rgba(20,27,55,0.02)]">
            <div class="text-[11px] font-semibold text-neutral-500">Menunggu validasi</div>
            <div class="mt-3 text-[28px] font-semibold tracking-[-0.7px] text-[#92721a]">{{ number_format($summary['pending_count']) }}</div>
            <div class="mt-1 text-[10px] text-neutral-500">Belum masuk Cash In final</div>
        </div>
        <div class="rounded-xl border border-neutral-200 bg-white p-[18px] shadow-[0_1px_2px_rgba(20,27,55,0.02)]">
            <div class="text-[11px] font-semibold text-neutral-500">Pembayaran ditolak</div>
            <div class="mt-3 text-[28px] font-semibold tracking-[-0.7px] text-error-500">{{ number_format($summary['rejected_count']) }}</div>
            <div class="mt-1 text-[10px] text-neutral-500">Perlu perbaikan data</div>
        </div>
    </div>

    <section class="overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-[0_1px_2px_rgba(20,27,55,0.02)]">
        <form id="payment-filter-form" method="GET" action="{{ route('payments.index') }}"
            class="flex flex-col gap-2 border-b border-neutral-100 p-[14px] sm:flex-row sm:flex-wrap sm:items-center sm:p-[16px]">
            <label class="relative block w-full sm:w-[270px]">
                <span class="sr-only">Cari Payment atau Invoice</span>
                <svg class="absolute left-[11px] top-1/2 h-4 w-4 -translate-y-1/2 fill-none stroke-current text-neutral-400" aria-hidden="true"><use href="#i-search"></use></svg>
                <input id="payment-search" type="search" name="search" value="{{ $filters['search'] }}"
                    placeholder="Cari no. payment / invoice / customer…"
                    class="h-[36px] w-full rounded-lg border border-neutral-200 bg-white pl-[34px] pr-3 text-[12px] outline-none focus:border-primary-300 focus:ring-2 focus:ring-primary-50">
            </label>

            <select id="payment-status-filter" name="status" aria-label="Filter status"
                class="h-[36px] w-full rounded-lg border border-neutral-200 bg-white px-3 text-[12px] text-neutral-600 sm:w-[180px]">
                <option value="">Semua status</option>
                <option value="Pending Validation" @selected($filters['status'] === 'Pending Validation')>Pending Validation</option>
                <option value="Validated" @selected($filters['status'] === 'Validated')>Validated</option>
                <option value="Rejected" @selected($filters['status'] === 'Rejected')>Rejected</option>
            </select>

            <a href="{{ route('payments.index') }}"
                class="inline-flex h-[36px] items-center justify-center rounded-lg border border-neutral-200 bg-white px-3 text-[11px] font-semibold text-neutral-600 hover:bg-neutral-50">Reset</a>

            @can('payments.export')
                <div class="ml-auto flex gap-2">
                    <a href="{{ route('payments.export', request()->query()) }}"
                        class="inline-flex items-center gap-2 rounded-lg border border-neutral-200 bg-white px-3 py-[8px] text-[11px] font-semibold text-neutral-600 transition hover:bg-neutral-50">
                        <svg class="h-4 w-4 fill-none stroke-current" aria-hidden="true"><use href="#i-download"></use></svg>
                        Ekspor Excel / CSV
                    </a>
                </div>
            @endcan
        </form>

        <!-- @can('payments.export')
            <div class="flex justify-end px-4 pt-3">
                <a href="{{ route('payments.export', request()->query()) }}"
                    class="inline-flex items-center gap-2 rounded-lg border border-neutral-200 bg-white px-3 py-[8px] text-[11px] font-semibold text-neutral-600 transition hover:bg-neutral-50">
                    <svg class="h-4 w-4 fill-none stroke-current" aria-hidden="true"><use href="#i-download"></use></svg>
                    Ekspor Excel/CSV
                </a>
            </div>
        @endcan -->

        <div class="overflow-x-auto">
            <table class="w-full min-w-[920px] border-collapse text-left">
                <thead>
                    <tr class="bg-neutral-50">
                        <th class="border-b border-neutral-200 px-4 py-3 text-[11px] font-bold text-neutral-500">No. Payment</th>
                        <th class="border-b border-neutral-200 px-4 py-3 text-[11px] font-bold text-neutral-500">Invoice</th>
                        <th class="border-b border-neutral-200 px-4 py-3 text-[11px] font-bold text-neutral-500">Tanggal</th>
                        <th class="border-b border-neutral-200 px-4 py-3 text-[11px] font-bold text-neutral-500">Metode</th>
                        <th class="border-b border-neutral-200 px-4 py-3 text-[11px] font-bold text-neutral-500">Nominal</th>
                        <th class="border-b border-neutral-200 px-4 py-3 text-[11px] font-bold text-neutral-500">Dicatat oleh</th>
                        <th class="border-b border-neutral-200 px-4 py-3 text-[11px] font-bold text-neutral-500">Status</th>
                        <th class="border-b border-neutral-200 px-4 py-3 text-[11px] font-bold text-neutral-500">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($payments as $payment)
                        @php
                            $paymentStatus = $payment->status instanceof \App\Enums\PaymentStatus
                                ? $payment->status->value
                                : (string) $payment->status;
                            $statusClass = match ($paymentStatus) {
                                'Validated' => 'bg-success-50 text-success-500',
                                'Pending Validation' => 'bg-warning-50 text-[#92721a]',
                                'Rejected' => 'bg-error-50 text-error-500',
                                default => 'bg-neutral-50 text-neutral-500',
                            };
                            $invoiceNumbers = $payment->allocations
                                ->map(fn ($allocation) => $allocation->invoice?->invoice_number)
                                ->filter()->unique()->implode(', ');
                            $customerNames = $payment->allocations
                                ->map(fn ($allocation) => $allocation->invoice?->bill_to_snapshot)
                                ->filter()->unique()->implode(', ');
                        @endphp
                        <tr class="transition hover:bg-[#fcfcfe]">
                            <td class="border-b border-neutral-100 px-4 py-[13px] font-mono text-[12px] font-bold text-primary-500">{{ $payment->payment_number }}</td>
                            <td class="border-b border-neutral-100 px-4 py-[13px] text-[12px]">
                                <div class="font-semibold text-neutral-700">{{ $invoiceNumbers ?: '—' }}</div>
                                <div class="mt-1 max-w-[200px] truncate text-[10px] text-neutral-400">{{ $customerNames }}</div>
                            </td>
                            <td class="border-b border-neutral-100 px-4 py-[13px] text-[12px] text-neutral-600">{{ $payment->payment_date?->format('d M Y') ?? '—' }}</td>
                            <td class="border-b border-neutral-100 px-4 py-[13px] text-[12px] text-neutral-600">{{ $payment->payment_method }}</td>
                            <td class="border-b border-neutral-100 px-4 py-[13px] font-mono text-[12px] font-bold text-neutral-700">Rp {{ number_format((float) $payment->amount, 2, ',', '.') }}</td>
                            <td class="border-b border-neutral-100 px-4 py-[13px] text-[12px] text-neutral-600">{{ $payment->recordedBy?->name ?? '—' }}</td>
                            <td class="border-b border-neutral-100 px-4 py-[13px] text-[12px]">
                                <span class="inline-flex items-center gap-[5px] rounded-[5px] px-2 py-1 text-[10px] font-extrabold {{ $statusClass }}"><span class="h-[5px] w-[5px] rounded-full bg-current"></span>{{ $paymentStatus }}</span>
                            </td>
                            <td class="border-b border-neutral-100 px-4 py-[13px]">
                                <div class="flex items-center gap-2">
                                    <a href="{{ route('payments.show', $payment) }}" class="text-[11px] font-bold text-primary-500 hover:underline">Detail</a>
                                    @if ($paymentStatus === 'Pending Validation')
                                        @can('payments.approve')
                                            <button type="button"
                                                class="text-[11px] font-bold text-[#92721a] hover:underline"
                                                data-open-payment-authorization
                                                data-payment-number="{{ $payment->payment_number }}"
                                                data-invoice="{{ $invoiceNumbers ?: '—' }}"
                                                data-customer="{{ $customerNames ?: '—' }}"
                                                data-amount="{{ number_format((float) $payment->amount, 2, ',', '.') }}"
                                                data-method="{{ $payment->payment_method }}"
                                                data-date="{{ $payment->payment_date?->format('d M Y') ?? '—' }}"
                                                data-account="{{ $payment->bankAccount ? $payment->bankAccount->bank_name . ' - ' . $payment->bankAccount->account_code : '—' }}"
                                                data-reference="{{ $payment->reference_number }}"
                                                data-recorded-by="{{ $payment->recordedBy?->name ?? '—' }}"
                                                data-validate-url="{{ route('payments.validate', $payment) }}">{{ $authorizationLabel }}</button>
                                        @endcan
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-12 text-center">
                                <p class="text-[12px] font-bold text-neutral-500">Tidak ada data pembayaran.</p>
                                <p class="mt-1 text-[11px] text-neutral-400">Ubah kata kunci atau filter untuk melihat data lain.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="flex flex-col gap-3 border-t border-neutral-100 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-wrap items-center gap-4 text-[10px] text-neutral-500">
                <span>Menampilkan {{ $payments->firstItem() ?? 0 }}–{{ $payments->lastItem() ?? 0 }} dari {{ $payments->total() }}</span>
                <label class="flex items-center gap-2">Tampilkan
                    <select id="payment-per-page" name="per_page" form="payment-filter-form"
                        class="h-8 rounded-md border border-neutral-200 bg-white px-2 text-[10px] text-neutral-600">
                        <option value="5" @selected($filters['per_page'] === 5)>5</option>
                        <option value="10" @selected($filters['per_page'] === 10)>10</option>
                        <option value="25" @selected($filters['per_page'] === 25)>25</option>
                        <option value="50" @selected($filters['per_page'] === 50)>50</option>
                    </select>
                </label>
            </div>
            <div class="flex items-center justify-end gap-2 text-[11px]">
                <a href="{{ $payments->previousPageUrl() ?? '#' }}" @if (!$payments->previousPageUrl()) aria-disabled="true" tabindex="-1" @endif
                    class="rounded-md border border-neutral-200 px-3 py-2 font-semibold {{ $payments->onFirstPage() ? 'pointer-events-none opacity-40' : 'text-neutral-600 hover:bg-neutral-50' }}">Sebelumnya</a>
                <span class="px-1 text-neutral-500">Halaman {{ $payments->currentPage() }}/{{ max(1, $payments->lastPage()) }}</span>
                <a href="{{ $payments->nextPageUrl() ?? '#' }}" @if (!$payments->hasMorePages()) aria-disabled="true" tabindex="-1" @endif
                    class="rounded-md border border-neutral-200 px-3 py-2 font-semibold {{ $payments->hasMorePages() ? 'text-neutral-600 hover:bg-neutral-50' : 'pointer-events-none opacity-40' }}">Berikutnya</a>
            </div>
        </div>
    </section>

    {{-- Modal otorisasi sengaja berada langsung di index.blade.php, bukan partial. --}}
    <div id="payment-authorization-modal" class="fixed inset-0 z-[70] hidden items-center justify-center bg-[#111827]/50 p-4" aria-hidden="true">
        <div class="absolute inset-0" data-close-payment-authorization></div>
        <section class="relative z-[1] max-h-[90vh] w-full max-w-[560px] overflow-y-auto rounded-xl border border-neutral-200 bg-white shadow-2xl" role="dialog" aria-modal="true" aria-labelledby="payment-authorization-title">
            <div class="flex items-start justify-between border-b border-neutral-100 px-5 py-4 sm:px-6">
                <div>
                    <h2 id="payment-authorization-title" class="font-heading text-[17px] font-semibold text-neutral-900">{{ $authorizationLabel }} pembayaran</h2>
                    <p class="mt-1 text-[11px] text-neutral-500">Periksa informasi pembayaran sebelum melakukan validasi.</p>
                </div>
                <button type="button" data-close-payment-authorization class="grid h-8 w-8 place-items-center rounded-lg bg-neutral-100 text-neutral-600 hover:bg-neutral-200" aria-label="Tutup modal">×</button>
            </div>
            <div class="space-y-4 px-5 py-5 sm:px-6">
                <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4">
                    <div class="text-[10px] font-semibold uppercase tracking-wide text-neutral-500">No. Payment</div>
                    <div id="payment-modal-number" class="mt-1 font-mono text-[13px] font-bold text-primary-500">—</div>
                </div>
                <dl class="grid grid-cols-1 gap-3 text-[12px] sm:grid-cols-2">
                    <div><dt class="text-[10px] text-neutral-500">Invoice</dt><dd id="payment-modal-invoice" class="mt-1 font-semibold text-neutral-800">—</dd></div>
                    <div><dt class="text-[10px] text-neutral-500">Customer</dt><dd id="payment-modal-customer" class="mt-1 font-semibold text-neutral-800">—</dd></div>
                    <div><dt class="text-[10px] text-neutral-500">Nominal</dt><dd id="payment-modal-amount" class="mt-1 font-semibold text-neutral-800">—</dd></div>
                    <div><dt class="text-[10px] text-neutral-500">Metode</dt><dd id="payment-modal-method" class="mt-1 font-semibold text-neutral-800">—</dd></div>
                    <div><dt class="text-[10px] text-neutral-500">Tanggal Pembayaran</dt><dd id="payment-modal-date" class="mt-1 font-semibold text-neutral-800">—</dd></div>
                    <div><dt class="text-[10px] text-neutral-500">Rekening Tujuan</dt><dd id="payment-modal-account" class="mt-1 font-semibold text-neutral-800">—</dd></div>
                    <div><dt class="text-[10px] text-neutral-500">Nomor Referensi</dt><dd id="payment-modal-reference" class="mt-1 break-all font-semibold text-neutral-800">—</dd></div>
                    <div><dt class="text-[10px] text-neutral-500">Dicatat Oleh</dt><dd id="payment-modal-recorded-by" class="mt-1 font-semibold text-neutral-800">—</dd></div>
                </dl>
                <div class="rounded-lg border border-[#f7e5a9] bg-[#fdf9ec] px-4 py-3 text-[11px] leading-relaxed text-[#836d25]">
                    Setelah pembayaran divalidasi, sistem akan memperbarui status Invoice dan mencatat Cash In secara otomatis. Jangan mencatat Cash In yang sama secara manual.
                </div>
            </div>
            <form id="payment-authorization-form" method="POST" action="" class="flex flex-col-reverse gap-2 border-t border-neutral-100 px-5 py-4 sm:flex-row sm:justify-end sm:px-6">
                @csrf
                <input type="hidden" name="confirmation" value="1">
                <button type="button" data-close-payment-authorization class="rounded-lg border border-neutral-200 bg-white px-4 py-2.5 text-[11px] font-semibold text-neutral-600 hover:bg-neutral-50">Batal</button>
                <button type="submit" class="rounded-lg bg-[#0d21a1] px-4 py-2.5 text-[11px] font-bold text-white hover:bg-[#091772]">{{ $authorizationLabel }} &amp; catat Cash In</button>
            </form>
        </section>
    </div>
@endsection

@push('scripts')
    <script>
        (() => {
            const filterForm = document.getElementById('payment-filter-form');
            const search = document.getElementById('payment-search');
            const status = document.getElementById('payment-status-filter');
            const perPage = document.getElementById('payment-per-page');
            let searchTimer;

            [status, perPage].forEach((field) => field?.addEventListener('change', () => filterForm?.requestSubmit()));
            search?.addEventListener('input', () => {
                window.clearTimeout(searchTimer);
                searchTimer = window.setTimeout(() => filterForm?.requestSubmit(), 350);
            });

            const modal = document.getElementById('payment-authorization-modal');
            const form = document.getElementById('payment-authorization-form');
            const openButtons = document.querySelectorAll('[data-open-payment-authorization]');
            const closeButtons = modal?.querySelectorAll('[data-close-payment-authorization]') ?? [];

            const fields = {
                number: document.getElementById('payment-modal-number'),
                invoice: document.getElementById('payment-modal-invoice'),
                customer: document.getElementById('payment-modal-customer'),
                amount: document.getElementById('payment-modal-amount'),
                method: document.getElementById('payment-modal-method'),
                date: document.getElementById('payment-modal-date'),
                account: document.getElementById('payment-modal-account'),
                reference: document.getElementById('payment-modal-reference'),
                recordedBy: document.getElementById('payment-modal-recorded-by'),
            };

            const closeModal = () => {
                modal?.classList.add('hidden');
                modal?.classList.remove('flex');
                modal?.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('overflow-hidden');
            };

            openButtons.forEach((button) => button.addEventListener('click', () => {
                fields.number.textContent = button.dataset.paymentNumber || '—';
                fields.invoice.textContent = button.dataset.invoice || '—';
                fields.customer.textContent = button.dataset.customer || '—';
                fields.amount.textContent = `Rp ${button.dataset.amount || '0,00'}`;
                fields.method.textContent = button.dataset.method || '—';
                fields.date.textContent = button.dataset.date || '—';
                fields.account.textContent = button.dataset.account || '—';
                fields.reference.textContent = button.dataset.reference || '—';
                fields.recordedBy.textContent = button.dataset.recordedBy || '—';
                form.action = button.dataset.validateUrl || '';
                modal?.classList.remove('hidden');
                modal?.classList.add('flex');
                modal?.setAttribute('aria-hidden', 'false');
                document.body.classList.add('overflow-hidden');
            }));

            closeButtons.forEach((button) => button.addEventListener('click', closeModal));
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && modal && !modal.classList.contains('hidden')) closeModal();
            });
        })();
    </script>
@endpush
