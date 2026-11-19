@extends('admin.layouts.layout')

@section('title', 'Catat Pembayaran')
@section('page_title', 'Catat Pembayaran')
@section('page_breadcrumb', 'Finance / Payment / Catat Pembayaran')

@section('content')
    <div class="mb-6 flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-start">
        <div>
            <div class="mb-[7px] text-[12px] font-bold text-[#0d21a1]">Finance workspace</div>
            <h1 class="m-0 font-heading text-[28px] font-semibold leading-tight tracking-[-0.8px] text-[#171717] sm:text-[31px]">Catat pembayaran</h1>
            <p class="mt-[6px] max-w-2xl text-[13px] leading-relaxed text-[#737373]">Catat DP, cicilan, atau pembayaran parsial berdasarkan Invoice yang masih memiliki sisa tagihan.</p>
        </div>
        <a href="{{ route('payments.index') }}" class="inline-flex shrink-0 items-center justify-center rounded-lg border border-neutral-200 bg-white px-4 py-[10px] text-[11px] font-semibold text-neutral-600 hover:bg-neutral-50">Kembali ke Payment</a>
    </div>

    @if ($invoices->isEmpty())
        <div class="rounded-xl border border-[#f7e5a9] bg-[#fdf9ec] p-4 text-[12px] leading-relaxed text-[#836d25]">
            Saat ini tidak ada Invoice dengan sisa tagihan yang tersedia untuk menerima pembayaran.
            <a href="{{ route('payments.index') }}" class="ml-1 font-bold underline">Kembali ke daftar Payment</a>.
        </div>
    @else
        <form id="payment-create-form" method="POST" action="{{ route('payments.store') }}" enctype="multipart/form-data" class="space-y-5">
            @csrf
            <section class="overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-[0_1px_2px_rgba(20,27,55,0.02)]">
                <div class="border-b border-neutral-100 px-5 py-4 sm:px-6">
                    <h2 class="font-heading text-[15px] font-semibold text-neutral-900">Informasi Invoice</h2>
                    <p class="mt-1 text-[11px] text-neutral-500">Pilih Invoice yang akan menerima pembayaran.</p>
                </div>
                <div class="grid grid-cols-1 gap-4 p-5 sm:grid-cols-2 sm:p-6">
                    <div class="sm:col-span-2">
                        <label for="invoice_id" class="mb-1.5 block text-[11px] font-bold text-neutral-600">Invoice <span class="text-error-500">*</span></label>
                        <select id="invoice_id" name="invoice_id" required class="h-[40px] w-full rounded-lg border border-neutral-200 bg-white px-3 text-[12px] text-neutral-700 outline-none focus:border-primary-300 focus:ring-2 focus:ring-primary-50">
                            <option value="">Pilih Invoice</option>
                            @foreach ($invoices as $invoice)
                                <option value="{{ $invoice->invoice_id }}"
                                    data-number="{{ $invoice->invoice_number }}"
                                    data-customer="{{ $invoice->bill_to_snapshot }}"
                                    data-total="{{ number_format((float) $invoice->grand_total, 2, '.', '') }}"
                                    data-paid="{{ number_format((float) $invoice->paid_amount, 2, '.', '') }}"
                                    data-remaining="{{ number_format((float) $invoice->remaining_amount, 2, '.', '') }}"
                                    @selected((string) old('invoice_id') === (string) $invoice->invoice_id)>
                                    {{ $invoice->invoice_number }} — {{ $invoice->bill_to_snapshot }}
                                </option>
                            @endforeach
                        </select>
                        @error('invoice_id')<p class="mt-1 text-[10px] text-error-500">{{ $message }}</p>@enderror
                    </div>

                    <div id="invoice-preview" class="hidden rounded-lg border border-primary-100 bg-primary-50 p-4 sm:col-span-2">
                        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <div class="text-[10px] font-semibold uppercase tracking-wide text-primary-500">Invoice terpilih</div>
                                <div id="invoice-preview-number" class="mt-1 text-[13px] font-bold text-neutral-900">—</div>
                                <div id="invoice-preview-customer" class="mt-0.5 text-[11px] text-neutral-600">—</div>
                            </div>
                            <span class="rounded-md bg-white px-2.5 py-1 text-[10px] font-bold text-primary-500">Sisa tagihan</span>
                        </div>
                        <div class="grid grid-cols-1 gap-3 text-[11px] sm:grid-cols-3">
                            <div><div class="text-neutral-500">Total Invoice</div><div id="invoice-preview-total" class="mt-1 font-semibold text-neutral-800">Rp 0</div></div>
                            <div><div class="text-neutral-500">Sudah tervalidasi</div><div id="invoice-preview-paid" class="mt-1 font-semibold text-neutral-800">Rp 0</div></div>
                            <div><div class="text-neutral-500">Sisa dapat dibayarkan</div><div id="invoice-preview-remaining" class="mt-1 font-bold text-primary-500">Rp 0</div></div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-[0_1px_2px_rgba(20,27,55,0.02)]">
                <div class="border-b border-neutral-100 px-5 py-4 sm:px-6">
                    <h2 class="font-heading text-[15px] font-semibold text-neutral-900">Detail pembayaran</h2>
                    <p class="mt-1 text-[11px] text-neutral-500">Lengkapi informasi pembayaran sesuai bukti transaksi.</p>
                </div>
                <div class="grid grid-cols-1 gap-4 p-5 sm:grid-cols-2 sm:p-6">
                    <div>
                        <label for="payment_date" class="mb-1.5 block text-[11px] font-bold text-neutral-600">Tanggal pembayaran <span class="text-error-500">*</span></label>
                        <input id="payment_date" type="date" name="payment_date" value="{{ old('payment_date', now()->toDateString()) }}" required class="h-[40px] w-full rounded-lg border border-neutral-200 bg-white px-3 text-[12px] text-neutral-700 outline-none focus:border-primary-300 focus:ring-2 focus:ring-primary-50">
                        @error('payment_date')<p class="mt-1 text-[10px] text-error-500">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="payment_method" class="mb-1.5 block text-[11px] font-bold text-neutral-600">Metode pembayaran <span class="text-error-500">*</span></label>
                        <select id="payment_method" name="payment_method" required class="h-[40px] w-full rounded-lg border border-neutral-200 bg-white px-3 text-[12px] text-neutral-700 outline-none focus:border-primary-300 focus:ring-2 focus:ring-primary-50">
                            <option value="">Pilih metode pembayaran</option>
                            @foreach ($paymentMethods as $method)
                                <option value="{{ $method }}" @selected(old('payment_method') === $method)>{{ $method }}</option>
                            @endforeach
                        </select>
                        @error('payment_method')<p class="mt-1 text-[10px] text-error-500">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="amount" class="mb-1.5 block text-[11px] font-bold text-neutral-600">Nominal pembayaran (Rp) <span class="text-error-500">*</span></label>
                        <input id="amount" type="number" name="amount" min="0.01" step="0.01" value="{{ old('amount') }}" required placeholder="Masukkan nominal pembayaran" class="h-[40px] w-full rounded-lg border border-neutral-200 bg-white px-3 text-[12px] text-neutral-700 outline-none focus:border-primary-300 focus:ring-2 focus:ring-primary-50">
                        <p id="amount-helper" class="mt-1 text-[10px] text-neutral-400">Nominal tidak boleh melebihi sisa tagihan Invoice.</p>
                        @error('amount')<p class="mt-1 text-[10px] text-error-500">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="bank_account_id" class="mb-1.5 block text-[11px] font-bold text-neutral-600">Rekening tujuan <span id="bank-account-required" class="text-error-500">*</span></label>
                        <select id="bank_account_id" name="bank_account_id" class="h-[40px] w-full rounded-lg border border-neutral-200 bg-white px-3 text-[12px] text-neutral-700 outline-none focus:border-primary-300 focus:ring-2 focus:ring-primary-50">
                            <option value="">Pilih rekening tujuan</option>
                            @foreach ($bankAccounts as $account)
                                <option value="{{ $account->bank_account_id }}" @selected((string) old('bank_account_id') === (string) $account->bank_account_id)>
                                    {{ $account->bank_name }} — {{ $account->account_code }} (•••• {{ substr($account->account_number, -4) }})
                                </option>
                            @endforeach
                        </select>
                        <p id="bank-account-helper" class="mt-1 text-[10px] text-neutral-400">Rekening tujuan wajib dipilih untuk transfer, virtual account, dan giro.</p>
                        @error('bank_account_id')<p class="mt-1 text-[10px] text-error-500">{{ $message }}</p>@enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label for="reference_number" class="mb-1.5 block text-[11px] font-bold text-neutral-600">Nomor referensi <span class="text-error-500">*</span></label>
                        <input id="reference_number" type="text" name="reference_number" maxlength="120" value="{{ old('reference_number') }}" required placeholder="Nomor transfer / referensi pembayaran" class="h-[40px] w-full rounded-lg border border-neutral-200 bg-white px-3 text-[12px] text-neutral-700 outline-none focus:border-primary-300 focus:ring-2 focus:ring-primary-50">
                        @error('reference_number')<p class="mt-1 text-[10px] text-error-500">{{ $message }}</p>@enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label for="proof" class="mb-1.5 block text-[11px] font-bold text-neutral-600">Bukti pembayaran</label>
                        <input id="proof" type="file" name="proof" accept=".jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf" class="block w-full rounded-lg border border-neutral-200 bg-white px-3 py-2 text-[11px] text-neutral-600 file:mr-3 file:rounded-md file:border-0 file:bg-primary-50 file:px-3 file:py-1.5 file:text-[10px] file:font-bold file:text-primary-500">
                        <p class="mt-1 text-[10px] text-neutral-400">Format JPG, PNG, WebP, atau PDF. Ukuran maksimal 5 MB. File disimpan secara privat.</p>
                        <p id="proof-file-name" class="mt-1 text-[10px] font-medium text-neutral-600"></p>
                        @error('proof')<p class="mt-1 text-[10px] text-error-500">{{ $message }}</p>@enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label for="notes" class="mb-1.5 block text-[11px] font-bold text-neutral-600">Catatan</label>
                        <textarea id="notes" name="notes" rows="3" maxlength="2000" placeholder="Catatan tambahan (opsional)" class="w-full rounded-lg border border-neutral-200 bg-white px-3 py-2.5 text-[12px] text-neutral-700 outline-none focus:border-primary-300 focus:ring-2 focus:ring-primary-50">{{ old('notes') }}</textarea>
                        @error('notes')<p class="mt-1 text-[10px] text-error-500">{{ $message }}</p>@enderror
                    </div>
                </div>
            </section>

            <div class="rounded-lg border border-[#e7e9f6] bg-[#f5f6fb] px-4 py-3 text-[11px] leading-relaxed text-neutral-600">
                Payment yang baru dicatat akan berstatus <strong class="text-[#92721a]">Pending Validation</strong>. Invoice dan Cash In baru diperbarui setelah pembayaran divalidasi oleh pengguna yang memiliki izin.
            </div>

            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <a href="{{ route('payments.index') }}" class="inline-flex items-center justify-center rounded-lg border border-neutral-200 bg-white px-5 py-3 text-[11px] font-semibold text-neutral-600 hover:bg-neutral-50">Batal</a>
                <button type="submit" class="inline-flex items-center justify-center rounded-lg bg-[#0d21a1] px-5 py-3 text-[11px] font-bold text-white transition hover:bg-[#091772]">Simpan pembayaran</button>
            </div>
        </form>
    @endif
@endsection

@push('scripts')
    <script>
        (() => {
            const invoiceSelect = document.getElementById('invoice_id');
            const preview = document.getElementById('invoice-preview');
            const amount = document.getElementById('amount');
            const method = document.getElementById('payment_method');
            const account = document.getElementById('bank_account_id');
            const proof = document.getElementById('proof');
            const rupiah = (value) => new Intl.NumberFormat('id-ID', {
                style: 'currency', currency: 'IDR', maximumFractionDigits: 0
            }).format(Number(value || 0));

            const updateInvoice = () => {
                const option = invoiceSelect?.selectedOptions?.[0];
                if (!option || !option.value) {
                    preview?.classList.add('hidden');
                    amount?.removeAttribute('max');
                    return;
                }

                const total = Number(option.dataset.total || 0);
                const paid = Number(option.dataset.paid || 0);
                const remaining = Number(option.dataset.remaining || 0);
                document.getElementById('invoice-preview-number').textContent = option.dataset.number || '—';
                document.getElementById('invoice-preview-customer').textContent = option.dataset.customer || '—';
                document.getElementById('invoice-preview-total').textContent = rupiah(total);
                document.getElementById('invoice-preview-paid').textContent = rupiah(paid);
                document.getElementById('invoice-preview-remaining').textContent = rupiah(remaining);
                preview?.classList.remove('hidden');
                amount?.setAttribute('max', remaining.toFixed(2));
                document.getElementById('amount-helper').textContent = `Maksimal nominal yang dapat dicatat: ${rupiah(remaining)}.`;
            };

            const updateBankRequirement = () => {
                const isCash = method?.value === 'Tunai';
                if (account) account.required = !isCash;
                document.getElementById('bank-account-required')?.classList.toggle('hidden', isCash);
                document.getElementById('bank-account-helper').textContent = isCash
                    ? 'Rekening tujuan tidak wajib untuk pembayaran tunai.'
                    : 'Rekening tujuan wajib dipilih untuk metode pembayaran ini.';
            };

            invoiceSelect?.addEventListener('change', updateInvoice);
            method?.addEventListener('change', updateBankRequirement);
            proof?.addEventListener('change', () => {
                const file = proof.files?.[0];
                document.getElementById('proof-file-name').textContent = file
                    ? `${file.name} (${(file.size / 1024).toFixed(0)} KB)`
                    : '';
            });

            updateInvoice();
            updateBankRequirement();
        })();
    </script>
@endpush
