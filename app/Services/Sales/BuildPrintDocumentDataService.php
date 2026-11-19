<?php

namespace App\Services\Sales;

use App\Models\Sales\Invoice;
use Illuminate\Support\Facades\DB;

class BuildPrintDocumentDataService
{
    public function __construct(private readonly InvoiceQueryService $invoices)
    {
    }

    public function forInvoice(Invoice $invoice): array
    {
        $invoice->load(['salesOrder', 'createdBy', 'items']);
        $paidAmount = $this->invoices->paidAmount($invoice->invoice_id);

        return [
            'invoice' => $invoice,
            'payments' => $this->invoices->paymentsForInvoice($invoice->invoice_id),
            'paidAmount' => $paidAmount,
            'remainingAmount' => max(0, (float) $invoice->grand_total - $paidAmount),
            'companySettings' => DB::table('company_document_settings')
                ->where('document_type', 'invoice')
                ->where('is_active', true)
                ->first(),
        ];
    }
}
