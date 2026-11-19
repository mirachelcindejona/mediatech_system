@extends('admin.layouts.layout')

@section('title', 'Detail Invoice')
@section('page_title', 'Detail Invoice')
@section('page_breadcrumb', 'Sales / Invoice / Detail')

@section('content')
    @php
        $statusValue = $invoice->status?->value ?? $invoice->status ?? 'Outstanding';
        $statusClass = match ($statusValue) {
            'Paid' => 'bg-success-50 text-success-500',
            'Partially Paid' => 'bg-warning-50 text-warning-500',
            'Overdue' => 'bg-error-50 text-error-500',
            default => 'bg-neutral-100 text-neutral-700',
        };
    @endphp

    <div class="mb-6 flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center">
        <div>
            <div class="mb-[7px] text-[12px] font-bold text-primary-500">Finance workspace</div>
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="m-0 font-heading text-[27px] font-semibold leading-tight tracking-[-0.8px] text-neutral-900 sm:text-[31px]">{{ $invoice->invoice_number }}</h1>
                <span class="inline-flex items-center gap-[5px] rounded-[5px] px-2 py-1 text-[10px] font-extrabold {{ $statusClass }}"><span class="h-[5px] w-[5px] rounded-full bg-current"></span>{{ $statusValue }}</span>
            </div>
            <p class="mt-[6px] max-w-2xl text-[13px] leading-relaxed text-neutral-500">Detail tagihan, item yang ditagihkan, dan riwayat pembayaran terkait.</p>
        </div>
        <div class="flex w-full flex-wrap gap-2 sm:w-auto">
            <a href="{{ route('invoices.index') }}" class="inline-flex flex-1 items-center justify-center rounded-lg border border-neutral-200 bg-white px-4 py-2.5 text-[11px] font-bold text-neutral-600 hover:bg-neutral-50 sm:flex-none">Kembali</a>
            <a href="{{ route('invoices.print', $invoice) }}" target="_blank" rel="noopener" class="inline-flex flex-1 items-center justify-center gap-2 rounded-lg bg-primary-500 px-4 py-2.5 text-[11px] font-bold text-white hover:bg-primary-700 sm:flex-none">
                <svg class="h-4 w-4 fill-none stroke-current" aria-hidden="true"><use href="#i-download"></use></svg>Print Invoice
            </a>
        </div>
    </div>

    <div class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
        <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-[0_1px_2px_rgba(20,27,55,0.02)]">
            <div class="text-[10px] font-semibold text-neutral-500">Total tagihan</div>
            <div class="mt-2 text-[20px] font-bold tracking-tight text-neutral-900">Rp {{ number_format((float) $invoice->grand_total, 2, ',', '.') }}</div>
        </div>
        <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-[0_1px_2px_rgba(20,27,55,0.02)]">
            <div class="text-[10px] font-semibold text-neutral-500">Sudah dibayar</div>
            <div class="mt-2 text-[20px] font-bold tracking-tight text-success-500">Rp {{ number_format((float) $paidAmount, 2, ',', '.') }}</div>
            <div class="mt-1 text-[9px] text-neutral-400">Hanya pembayaran tervalidasi</div>
        </div>
        <div class="rounded-xl border border-neutral-200 bg-white p-4 shadow-[0_1px_2px_rgba(20,27,55,0.02)]">
            <div class="text-[10px] font-semibold text-neutral-500">Sisa tagihan</div>
            <div class="mt-2 text-[20px] font-bold tracking-tight {{ $remainingAmount > 0 ? 'text-warning-500' : 'text-success-500' }}">Rp {{ number_format((float) $remainingAmount, 2, ',', '.') }}</div>
        </div>
    </div>

    <section class="mb-4 overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-[0_1px_2px_rgba(20,27,55,0.02)]">
        <div class="border-b border-neutral-100 px-5 py-4"><h2 class="text-[13px] font-bold text-neutral-800">Informasi Invoice</h2></div>
        <div class="grid grid-cols-1 gap-x-8 gap-y-5 p-5 sm:grid-cols-2 lg:grid-cols-4">
            <div><div class="text-[10px] font-semibold text-neutral-400">No. Invoice</div><div class="mt-1 text-[12px] font-bold text-primary-500">{{ $invoice->invoice_number }}</div></div>
            <div><div class="text-[10px] font-semibold text-neutral-400">Sales Order</div><div class="mt-1 text-[12px] font-semibold text-neutral-800">{{ $invoice->salesOrder?->so_number ?? '—' }}</div></div>
            <div><div class="text-[10px] font-semibold text-neutral-400">Tipe Invoice</div><div class="mt-1 text-[12px] font-semibold text-neutral-800">{{ $invoice->invoice_type }}</div></div>
            <div><div class="text-[10px] font-semibold text-neutral-400">No. Referensi</div><div class="mt-1 text-[12px] font-semibold text-neutral-800">{{ $invoice->reference_number ?: '—' }}</div></div>
            <div><div class="text-[10px] font-semibold text-neutral-400">Tanggal Invoice</div><div class="mt-1 text-[12px] text-neutral-700">{{ $invoice->invoice_date?->format('d M Y') ?? '—' }}</div></div>
            <div><div class="text-[10px] font-semibold text-neutral-400">Jatuh Tempo</div><div class="mt-1 text-[12px] text-neutral-700">{{ $invoice->due_date?->format('d M Y') ?? '—' }}</div></div>
            <div><div class="text-[10px] font-semibold text-neutral-400">Dibuat Oleh</div><div class="mt-1 text-[12px] text-neutral-700">{{ $invoice->createdBy?->name ?? '—' }}</div></div>
            <div><div class="text-[10px] font-semibold text-neutral-400">Payment Terms</div><div class="mt-1 text-[12px] text-neutral-700">{{ $invoice->payment_terms_snapshot ?: '—' }}</div></div>
        </div>
    </section>

    <section class="mb-4 overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-[0_1px_2px_rgba(20,27,55,0.02)]">
        <div class="border-b border-neutral-100 px-5 py-4"><h2 class="text-[13px] font-bold text-neutral-800">Informasi Customer</h2></div>
        <div class="grid grid-cols-1 gap-5 p-5 sm:grid-cols-2">
            <div>
                <div class="text-[10px] font-semibold text-neutral-400">Bill To</div>
                <div class="mt-1 text-[12px] font-bold text-neutral-800">{{ $invoice->bill_to_snapshot }}</div>
                <p class="mt-1 whitespace-pre-line text-[11px] leading-relaxed text-neutral-600">{{ $invoice->bill_to_address_snapshot ?: 'Alamat tidak tersedia.' }}</p>
            </div>
            <div>
                <div class="text-[10px] font-semibold text-neutral-400">Ship To</div>
                <div class="mt-1 text-[12px] font-bold text-neutral-800">{{ $invoice->ship_to_snapshot }}</div>
                <p class="mt-1 whitespace-pre-line text-[11px] leading-relaxed text-neutral-600">{{ $invoice->ship_to_address_snapshot ?: 'Alamat tidak tersedia.' }}</p>
            </div>
            <div><div class="text-[10px] font-semibold text-neutral-400">Kontak</div><div class="mt-1 text-[11px] text-neutral-700">{{ $invoice->contact_phone_snapshot ?: '—' }}</div></div>
            <div><div class="text-[10px] font-semibold text-neutral-400">Attention</div><div class="mt-1 text-[11px] text-neutral-700">{{ $invoice->attention_snapshot ?: '—' }}</div></div>
        </div>
    </section>

    <section class="mb-4 overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-[0_1px_2px_rgba(20,27,55,0.02)]">
        <div class="border-b border-neutral-100 px-5 py-4">
            <h2 class="text-[13px] font-bold text-neutral-800">Rincian Item Invoice</h2>
            <p class="mt-1 text-[11px] text-neutral-500">Kuantitas dan harga yang tersimpan pada dokumen invoice.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[760px] border-collapse text-left">
                <thead><tr class="bg-neutral-50">
                    <th class="border-b border-neutral-200 px-4 py-3 text-[10px] font-bold text-neutral-500">Kode Produk</th>
                    <th class="border-b border-neutral-200 px-4 py-3 text-[10px] font-bold text-neutral-500">Nama Produk</th>
                    <th class="border-b border-neutral-200 px-4 py-3 text-right text-[10px] font-bold text-neutral-500">Kuantitas</th>
                    <th class="border-b border-neutral-200 px-4 py-3 text-right text-[10px] font-bold text-neutral-500">Harga Satuan</th>
                    <th class="border-b border-neutral-200 px-4 py-3 text-right text-[10px] font-bold text-neutral-500">Diskon</th>
                    <th class="border-b border-neutral-200 px-4 py-3 text-right text-[10px] font-bold text-neutral-500">Subtotal</th>
                </tr></thead>
                <tbody>
                    @forelse ($invoice->items as $item)
                        <tr class="border-b border-neutral-100 last:border-0 hover:bg-[#fcfcfe]">
                            <td class="px-4 py-3 font-mono text-[10px] text-neutral-500">{{ $item->product_code_snapshot ?: '—' }}</td>
                            <td class="px-4 py-3"><div class="text-[11px] font-semibold text-neutral-800">{{ $item->product_name_snapshot }}</div><div class="mt-1 text-[10px] text-neutral-400">{{ $item->unit_snapshot }}</div></td>
                            <td class="px-4 py-3 text-right text-[11px] text-neutral-700">{{ number_format((float) $item->quantity, 3, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right text-[11px] text-neutral-700">Rp {{ number_format((float) $item->unit_price, 2, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right text-[11px] text-neutral-700">Rp {{ number_format((float) $item->discount_amount, 2, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right text-[11px] font-semibold text-neutral-800">Rp {{ number_format((float) $item->subtotal, 2, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-8 text-center text-[11px] text-neutral-500">Tidak ada rincian item.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="flex justify-end border-t border-neutral-100 bg-neutral-50/70 p-5">
            <dl class="w-full max-w-sm space-y-2 text-[11px]">
                <div class="flex justify-between gap-4 text-neutral-600"><dt>Subtotal</dt><dd class="font-semibold text-neutral-800">Rp {{ number_format((float) $invoice->subtotal, 2, ',', '.') }}</dd></div>
                <div class="flex justify-between gap-4 text-neutral-600"><dt>Diskon</dt><dd class="font-semibold text-neutral-800">Rp {{ number_format((float) $invoice->discount_amount, 2, ',', '.') }}</dd></div>
                <div class="flex justify-between gap-4 text-neutral-600"><dt>Pajak ({{ number_format((float) $invoice->tax_rate, 2, ',', '.') }}%)</dt><dd class="font-semibold text-neutral-800">Rp {{ number_format((float) $invoice->tax_amount, 2, ',', '.') }}</dd></div>
                <div class="flex justify-between gap-4 text-neutral-600"><dt>Biaya lain</dt><dd class="font-semibold text-neutral-800">Rp {{ number_format((float) $invoice->other_charges, 2, ',', '.') }}</dd></div>
                <div class="flex justify-between gap-4 border-t border-neutral-200 pt-3 text-[12px] font-bold text-neutral-900"><dt>Grand Total</dt><dd class="text-[15px] text-primary-500">Rp {{ number_format((float) $invoice->grand_total, 2, ',', '.') }}</dd></div>
            </dl>
        </div>
    </section>

    <section class="mb-4 overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-[0_1px_2px_rgba(20,27,55,0.02)]">
        <div class="border-b border-neutral-100 px-5 py-4">
            <h2 class="text-[13px] font-bold text-neutral-800">Riwayat Pembayaran</h2>
            <p class="mt-1 text-[11px] text-neutral-500">Pembayaran yang dialokasikan ke invoice ini. Hanya pembayaran tervalidasi yang mengurangi sisa tagihan.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[760px] border-collapse text-left">
                <thead><tr class="bg-neutral-50">
                    <th class="border-b border-neutral-200 px-4 py-3 text-[10px] font-bold text-neutral-500">No. Payment</th>
                    <th class="border-b border-neutral-200 px-4 py-3 text-[10px] font-bold text-neutral-500">Tanggal</th>
                    <th class="border-b border-neutral-200 px-4 py-3 text-[10px] font-bold text-neutral-500">Metode</th>
                    <th class="border-b border-neutral-200 px-4 py-3 text-right text-[10px] font-bold text-neutral-500">Dialokasikan</th>
                    <th class="border-b border-neutral-200 px-4 py-3 text-[10px] font-bold text-neutral-500">Status</th>
                    <th class="border-b border-neutral-200 px-4 py-3 text-[10px] font-bold text-neutral-500">Dicatat Oleh</th>
                </tr></thead>
                <tbody>
                    @forelse ($payments as $payment)
                        @php
                            $paymentStatus = $payment->status ?? 'Pending';
                            $paymentStatusClass = $paymentStatus === 'Validated' ? 'bg-success-50 text-success-500' : ($paymentStatus === 'Rejected' ? 'bg-error-50 text-error-500' : 'bg-warning-50 text-warning-500');
                        @endphp
                        <tr class="border-b border-neutral-100 last:border-0">
                            <td class="px-4 py-3 font-mono text-[11px] font-bold text-primary-500">{{ $payment->payment_number }}</td>
                            <td class="px-4 py-3 text-[11px] text-neutral-600">{{ $payment->payment_date ? \Illuminate\Support\Carbon::parse($payment->payment_date)->format('d M Y') : '—' }}</td>
                            <td class="px-4 py-3 text-[11px] text-neutral-600">{{ $payment->payment_method ?: '—' }}</td>
                            <td class="px-4 py-3 text-right text-[11px] font-semibold text-neutral-800">Rp {{ number_format((float) $payment->allocated_amount, 2, ',', '.') }}</td>
                            <td class="px-4 py-3"><span class="inline-flex rounded-[5px] px-2 py-1 text-[10px] font-bold {{ $paymentStatusClass }}">{{ $paymentStatus }}</span></td>
                            <td class="px-4 py-3 text-[11px] text-neutral-600">{{ $payment->recorded_by_name ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-8 text-center text-[11px] text-neutral-500">Belum ada pembayaran untuk invoice ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if ($invoice->notes)
        <section class="rounded-xl border border-neutral-200 bg-white p-5 shadow-[0_1px_2px_rgba(20,27,55,0.02)]">
            <h2 class="text-[12px] font-bold text-neutral-800">Catatan Invoice</h2>
            <p class="mt-2 whitespace-pre-line text-[11px] leading-relaxed text-neutral-600">{{ $invoice->notes }}</p>
        </section>
    @endif
@endsection
