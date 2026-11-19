@extends('admin.layouts.layout')

@section('title', 'Detail Payment')
@section('page_title', 'Detail Payment')
@section('page_breadcrumb', 'Finance / Payment / Detail')

@section('content')
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
    @endphp

    <div class="mb-6 flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center">
        <div>
            <div class="mb-[7px] text-[12px] font-bold text-[#0d21a1]">Finance workspace</div>
            <h1 class="m-0 font-heading text-[28px] font-semibold leading-tight tracking-[-0.8px] text-[#171717] sm:text-[31px]">Detail Payment</h1>
            <p class="mt-[6px] text-[13px] text-neutral-500">Informasi lengkap pembayaran {{ $payment->payment_number }}.</p>
        </div>
        <a href="{{ route('payments.index') }}" class="inline-flex items-center justify-center rounded-lg border border-neutral-200 bg-white px-4 py-[10px] text-[11px] font-semibold text-neutral-600 hover:bg-neutral-50">Kembali ke Payment</a>
    </div>

    <div class="mb-5 flex flex-col gap-3 rounded-xl border border-neutral-200 bg-white p-5 shadow-[0_1px_2px_rgba(20,27,55,0.02)] sm:flex-row sm:items-center sm:justify-between sm:px-6">
        <div>
            <div class="text-[10px] font-bold uppercase tracking-wide text-neutral-500">No. Payment</div>
            <div class="mt-1 font-mono text-[18px] font-bold text-primary-500">{{ $payment->payment_number }}</div>
        </div>
        <span class="inline-flex w-fit items-center gap-[5px] rounded-[5px] px-3 py-2 text-[10px] font-extrabold {{ $statusClass }}"><span class="h-[5px] w-[5px] rounded-full bg-current"></span>{{ $paymentStatus }}</span>
    </div>

    <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
        <section class="overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-[0_1px_2px_rgba(20,27,55,0.02)]">
            <div class="border-b border-neutral-100 px-5 py-4 sm:px-6">
                <h2 class="font-heading text-[14px] font-semibold text-neutral-900">Informasi pembayaran</h2>
            </div>
            <dl class="grid grid-cols-1 gap-x-6 gap-y-4 p-5 sm:grid-cols-2 sm:p-6">
                <div><dt class="text-[10px] text-neutral-500">Tanggal pembayaran</dt><dd class="mt-1 text-[12px] font-semibold text-neutral-800">{{ $payment->payment_date?->format('d M Y') ?? '—' }}</dd></div>
                <div><dt class="text-[10px] text-neutral-500">Nominal pembayaran</dt><dd class="mt-1 text-[12px] font-semibold text-neutral-800">Rp {{ number_format((float) $payment->amount, 2, ',', '.') }}</dd></div>
                <div><dt class="text-[10px] text-neutral-500">Metode pembayaran</dt><dd class="mt-1 text-[12px] font-semibold text-neutral-800">{{ $payment->payment_method }}</dd></div>
                <div><dt class="text-[10px] text-neutral-500">Rekening tujuan</dt><dd class="mt-1 text-[12px] font-semibold text-neutral-800">{{ $payment->bankAccount ? $payment->bankAccount->bank_name . ' — ' . $payment->bankAccount->account_code : '—' }}</dd></div>
                <div class="sm:col-span-2"><dt class="text-[10px] text-neutral-500">Nomor referensi</dt><dd class="mt-1 break-all text-[12px] font-semibold text-neutral-800">{{ $payment->reference_number ?: '—' }}</dd></div>
                <div class="sm:col-span-2"><dt class="text-[10px] text-neutral-500">Catatan</dt><dd class="mt-1 whitespace-pre-line text-[12px] leading-relaxed text-neutral-700">{{ $payment->notes ?: 'Tidak ada catatan.' }}</dd></div>
            </dl>
        </section>

        <section class="overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-[0_1px_2px_rgba(20,27,55,0.02)]">
            <div class="border-b border-neutral-100 px-5 py-4 sm:px-6">
                <h2 class="font-heading text-[14px] font-semibold text-neutral-900">Informasi pencatatan dan validasi</h2>
            </div>
            <dl class="grid grid-cols-1 gap-x-6 gap-y-4 p-5 sm:grid-cols-2 sm:p-6">
                <div><dt class="text-[10px] text-neutral-500">Dicatat oleh</dt><dd class="mt-1 text-[12px] font-semibold text-neutral-800">{{ $payment->recordedBy?->name ?? '—' }}</dd></div>
                <div><dt class="text-[10px] text-neutral-500">Divalidasi oleh</dt><dd class="mt-1 text-[12px] font-semibold text-neutral-800">{{ $payment->validatedBy?->name ?? 'Belum divalidasi' }}</dd></div>
                <div><dt class="text-[10px] text-neutral-500">Waktu validasi</dt><dd class="mt-1 text-[12px] font-semibold text-neutral-800">{{ $payment->validated_at?->format('d M Y H:i') ?? '—' }}</dd></div>
                <div><dt class="text-[10px] text-neutral-500">Status Payment</dt><dd class="mt-1 text-[12px] font-semibold text-neutral-800">{{ $paymentStatus }}</dd></div>
            </dl>
        </section>
    </div>

    <section class="mt-5 overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-[0_1px_2px_rgba(20,27,55,0.02)]">
        <div class="border-b border-neutral-100 px-5 py-4 sm:px-6">
            <h2 class="font-heading text-[14px] font-semibold text-neutral-900">Invoice yang dibayarkan</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[640px] border-collapse text-left">
                <thead><tr class="bg-neutral-50">
                    <th class="border-b border-neutral-200 px-4 py-3 text-[10px] font-bold text-neutral-500">No. Invoice</th>
                    <th class="border-b border-neutral-200 px-4 py-3 text-[10px] font-bold text-neutral-500">Customer</th>
                    <th class="border-b border-neutral-200 px-4 py-3 text-[10px] font-bold text-neutral-500">Tanggal Invoice</th>
                    <th class="border-b border-neutral-200 px-4 py-3 text-right text-[10px] font-bold text-neutral-500">Dialokasikan</th>
                </tr></thead>
                <tbody>
                    @forelse ($payment->allocations as $allocation)
                        <tr>
                            <td class="border-b border-neutral-100 px-4 py-3 font-mono text-[11px] font-bold text-primary-500">{{ $allocation->invoice?->invoice_number ?? '—' }}</td>
                            <td class="border-b border-neutral-100 px-4 py-3 text-[11px] text-neutral-600">{{ $allocation->invoice?->bill_to_snapshot ?? '—' }}</td>
                            <td class="border-b border-neutral-100 px-4 py-3 text-[11px] text-neutral-600">{{ $allocation->invoice?->invoice_date?->format('d M Y') ?? '—' }}</td>
                            <td class="border-b border-neutral-100 px-4 py-3 text-right font-mono text-[11px] font-semibold text-neutral-700">Rp {{ number_format((float) $allocation->allocated_amount, 2, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-8 text-center text-[11px] text-neutral-400">Tidak ada alokasi Invoice untuk pembayaran ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="mt-5 overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-[0_1px_2px_rgba(20,27,55,0.02)]">
        <div class="border-b border-neutral-100 px-5 py-4 sm:px-6">
            <h2 class="font-heading text-[14px] font-semibold text-neutral-900">Bukti pembayaran</h2>
            <p class="mt-1 text-[10px] text-neutral-500">Lampiran disimpan secara privat dan hanya dapat diakses oleh pengguna yang memiliki izin melihat Payment.</p>
        </div>
        <div class="space-y-3 p-5 sm:p-6">
            @forelse ($payment->attachments as $attachment)
                <div class="flex flex-col gap-2 rounded-lg border border-neutral-200 p-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        <div class="truncate text-[11px] font-semibold text-neutral-800">{{ $attachment->file_name }}</div>
                        <div class="mt-1 text-[10px] text-neutral-400">{{ strtoupper($attachment->media_type) }} · {{ number_format($attachment->file_size_kb) }} KB · Diunggah {{ $attachment->uploaded_at?->format('d M Y H:i') ?? '—' }}</div>
                    </div>
                    <a href="{{ route('payments.attachments.show', [$payment, $attachment]) }}" target="_blank" rel="noopener"
                        class="inline-flex shrink-0 items-center justify-center rounded-lg border border-neutral-200 bg-white px-3 py-2 text-[10px] font-bold text-primary-500 hover:bg-primary-50">Lihat bukti</a>
                </div>
            @empty
                <p class="text-[11px] text-neutral-400">Belum ada bukti pembayaran yang diunggah.</p>
            @endforelse
        </div>
    </section>
@endsection
