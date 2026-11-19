@extends('print.layouts.document')

@section('title', $invoice->invoice_number . ' | Invoice')
@section('back_url', route('invoices.show', $invoice))

@section('content')
    <header class="company">
        <div class="company-brand">
            @if (!empty($companySettings?->logo_path))
                <img class="company-logo" src="{{ asset($companySettings->logo_path) }}" alt="Logo perusahaan">
            @endif
            <div>
                <h1>{{ $companySettings?->company_name ?: 'PT Meditech Cipta Inovasi' }}</h1>
                @if ($companySettings?->company_address)<p>{{ $companySettings->company_address }}</p>@endif
                @if ($companySettings?->company_phone)<p>Telp. {{ $companySettings->company_phone }}</p>@endif
                @if ($companySettings?->company_email)<p>{{ $companySettings->company_email }}</p>@endif
                @if ($companySettings?->company_tax_number)<p>NPWP: {{ $companySettings->company_tax_number }}</p>@endif
            </div>
        </div>
        <div class="doc-heading">
            <h2>{{ $companySettings?->invoice_title ?: 'INVOICE' }}</h2>
            <p><strong>{{ $invoice->invoice_number }}</strong></p>
            <p>{{ $invoice->invoice_date?->format('d M Y') ?? '—' }}</p>
        </div>
    </header>

    <section class="meta-grid avoid-break">
        <div>
            <div class="label">Informasi Tagihan</div>
            <div class="value">Sales Order: {{ $invoice->salesOrder?->so_number ?? '—' }}</div>
            <div class="muted">Referensi: {{ $invoice->reference_number ?: '—' }}</div>
            <div class="muted">Tipe: {{ $invoice->invoice_type }}</div>
        </div>
        <div class="meta-due">
            <div class="label">Tanggal Jatuh Tempo</div>
            <div class="value">{{ $invoice->due_date?->format('d M Y') ?? '—' }}</div>
            <div class="muted">Payment terms: {{ $invoice->payment_terms_snapshot ?: '—' }}</div>
        </div>
    </section>

    <section class="address-grid avoid-break">
        <div class="address-box">
            <div class="label">Bill To</div>
            <div class="value">{{ $invoice->bill_to_snapshot }}</div>
            <div class="muted">{{ $invoice->bill_to_address_snapshot }}</div>
            @if ($invoice->contact_phone_snapshot)<div class="muted">{{ $invoice->contact_phone_snapshot }}</div>@endif
            @if ($invoice->attention_snapshot)<div class="muted">Attention: {{ $invoice->attention_snapshot }}</div>@endif
        </div>
        <div class="address-box">
            <div class="label">Ship To</div>
            <div class="value">{{ $invoice->ship_to_snapshot }}</div>
            <div class="muted">{{ $invoice->ship_to_address_snapshot }}</div>
        </div>
    </section>

    <section>
        <div class="label">Rincian Barang / Jasa</div>
        <table>
            <thead><tr>
                <th style="width:14%">Kode</th><th>Deskripsi</th><th class="right">Qty</th><th class="right">Harga Satuan</th><th class="right">Diskon</th><th class="right">Subtotal</th>
            </tr></thead>
            <tbody>
                @forelse ($invoice->items as $item)
                    <tr>
                        <td>{{ $item->product_code_snapshot }}</td>
                        <td>{{ $item->product_name_snapshot }}<br><span class="muted">{{ $item->unit_snapshot }}</span></td>
                        <td class="right">{{ number_format((float) $item->quantity, 3, ',', '.') }}</td>
                        <td class="right">{{ number_format((float) $item->unit_price, 2, ',', '.') }}</td>
                        <td class="right">{{ number_format((float) $item->discount_amount, 2, ',', '.') }}</td>
                        <td class="right">{{ number_format((float) $item->subtotal, 2, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty-row">Tidak ada item invoice.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>

    <div class="totals-wrap avoid-break">
        <div class="totals">
            <div class="total-row"><span>Subtotal</span><strong>Rp {{ number_format((float) $invoice->subtotal, 2, ',', '.') }}</strong></div>
            <div class="total-row"><span>Diskon</span><strong>Rp {{ number_format((float) $invoice->discount_amount, 2, ',', '.') }}</strong></div>
            <div class="total-row"><span>{{ $companySettings?->default_tax_label ?: 'Pajak' }} ({{ number_format((float) $invoice->tax_rate, 2, ',', '.') }}%)</span><strong>Rp {{ number_format((float) $invoice->tax_amount, 2, ',', '.') }}</strong></div>
            <div class="total-row"><span>Biaya lain</span><strong>Rp {{ number_format((float) $invoice->other_charges, 2, ',', '.') }}</strong></div>
            <div class="total-row grand"><span>Grand Total</span><strong>Rp {{ number_format((float) $invoice->grand_total, 2, ',', '.') }}</strong></div>
        </div>
    </div>

    <section class="payment-summary avoid-break">
        <div class="total-row"><span>Total Invoice</span><strong>Rp {{ number_format((float) $invoice->grand_total, 2, ',', '.') }}</strong></div>
        <div class="total-row"><span>Pembayaran Tervalidasi</span><strong>Rp {{ number_format((float) $paidAmount, 2, ',', '.') }}</strong></div>
        <div class="total-row"><span>Sisa Tagihan</span><strong>Rp {{ number_format((float) $remainingAmount, 2, ',', '.') }}</strong></div>
    </section>

    @if ($invoice->notes || $companySettings?->footer_notes)
        <section class="notes">
            @if ($invoice->notes)<p><strong>Catatan:</strong> {{ $invoice->notes }}</p>@endif
            @if ($companySettings?->footer_notes)<p>{{ $companySettings->footer_notes }}</p>@endif
        </section>
    @endif

    <section class="signature avoid-break">
        <div class="signature-box">
            <div>{{ $companySettings?->signature_label ?: 'Bagian Penjualan' }}</div>
            <div class="signature-space"></div>
            <div class="value">{{ $invoice->createdBy?->name ?? '____________________' }}</div>
        </div>
    </section>

    <footer class="footer">Dokumen ini diterbitkan melalui sistem Meditech.</footer>
@endsection

@push('styles')
    <style>
        .company { display: flex; align-items: flex-start; justify-content: space-between; gap: 24px; padding-bottom: 24px; border-bottom: 2px solid #0d21a1; }
        .company-brand { display: flex; align-items: flex-start; gap: 14px; }
        .company-logo { width: 72px; max-height: 64px; object-fit: contain; }
        .company h1 { margin: 0 0 5px; color: #0d21a1; font-size: 19px; }
        .company p { margin: 2px 0; color: #666; font-size: 10px; }
        .doc-heading { text-align: right; }
        .doc-heading h2 { margin: 0; color: #0d21a1; font-size: 25px; letter-spacing: 1px; }
        .doc-heading p { margin: 4px 0; color: #666; font-size: 11px; }
        .meta-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; margin: 28px 0; }
        .label { margin-bottom: 4px; color: #888; font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; }
        .value { color: #262626; font-size: 11px; font-weight: 600; }
        .muted { color: #666; font-size: 10px; font-weight: 400; }
        .meta-due { text-align: right; }
        .address-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 26px; }
        .address-box { padding: 13px; border: 1px solid #e5e7eb; border-radius: 6px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th { padding: 10px 8px; background: #f5f6fa; color: #555; text-align: left; font-size: 9px; text-transform: uppercase; }
        td { padding: 10px 8px; border-bottom: 1px solid #e5e7eb; font-size: 10px; }
        .right { text-align: right; }
        .empty-row { text-align: center; }
        .totals-wrap { display: flex; justify-content: flex-end; margin-top: 18px; }
        .totals { width: 300px; }
        .total-row { display: flex; justify-content: space-between; gap: 12px; padding: 5px 0; color: #555; font-size: 10px; }
        .total-row.grand { margin-top: 6px; padding-top: 11px; border-top: 1px solid #d4d4d4; color: #0d21a1; font-size: 13px; font-weight: 800; }
        .payment-summary { margin-top: 26px; padding: 13px 15px; border: 1px solid #e5e7eb; border-radius: 6px; }
        .notes { margin-top: 24px; color: #666; font-size: 10px; }
        .signature { display: flex; justify-content: flex-end; margin-top: 55px; text-align: center; }
        .signature-box { width: 210px; }
        .signature-space { height: 66px; }
        .footer { margin-top: 46px; padding-top: 12px; border-top: 1px solid #e5e7eb; color: #777; text-align: center; font-size: 9px; }
        @media (max-width: 640px) {
            .company { flex-direction: column; }
            .doc-heading, .meta-due { text-align: left; }
            .meta-grid, .address-grid { grid-template-columns: 1fr; }
        }
    </style>
@endpush
