<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\StoreInvoiceRequest;
use App\Models\Sales\Invoice;
use App\Services\Sales\CreateInvoiceService;
use App\Services\Sales\InvoiceQueryService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly InvoiceQueryService $invoices,
        private readonly CreateInvoiceService $createInvoice,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Invoice::class);

        $filters = $request->only(['search', 'status', 'per_page']);

        return view('sales.invoices.index', [
            'invoices' => $this->invoices->paginate($filters),
            'summary' => $this->invoices->summary(),
            'filters' => $this->invoices->normalizedFilters($filters),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Invoice::class);

        return view('sales.invoices.create', [
            'salesOrders' => $this->invoices->salesOrdersForInvoiceForm(),
        ]);
    }

    public function store(StoreInvoiceRequest $request): RedirectResponse
    {
        $invoice = $this->createInvoice->create($request->validated(), $request->user());

        return redirect()
            ->route('invoices.show', $invoice)
            ->with('success', 'Invoice ' . $invoice->invoice_number . ' berhasil diterbitkan.');
    }

    
    public function show(Invoice $invoice): View
    {
        $this->authorize('view', $invoice);
        
        $invoice->load(['salesOrder', 'createdBy', 'items']);
        $paidAmount = $this->invoices->paidAmount($invoice->invoice_id);
        
        return view('sales.invoices.show', [
            'invoice' => $invoice,
            'payments' => $this->invoices->paymentsForInvoice($invoice->invoice_id),
            'paidAmount' => $paidAmount,
            'remainingAmount' => max(0, (float) $invoice->grand_total - $paidAmount),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', Invoice::class);

        // Ambil filter yang sedang digunakan pada halaman index.
        $filters = $request->only([
            'search',
            'status',
        ]);

        // Ambil seluruh data berdasarkan filter, tanpa pagination.
        $invoices = $this->invoices
            ->exportRows($filters);

        $fileName = 'data-invoice-' . now()->format('Y-m-d-His') . '.csv';

        return response()->streamDownload(function () use ($invoices) {
            $handle = fopen('php://output', 'w');

            // BOM agar karakter CSV terbaca dengan baik di Microsoft Excel.
            fwrite($handle, "\xEF\xBB\xBF");

            // Header kolom CSV.
            fputcsv($handle, [
                'No. Invoice',
                'No. Sales Order',
                'Customer',
                'Tanggal Invoice',
                'Tanggal Jatuh Tempo',
                'Total Tagihan',
                'Sudah Dibayar',
                'Sisa Tagihan',
                'Status',
            ]);

            // Menggunakan cursor agar data diproses bertahap.
            foreach ($invoices->cursor() as $invoice) {
                $grandTotal = (float) $invoice->grand_total;
                $paidAmount = (float) ($invoice->paid_amount ?? 0);
                $remaining = max(0, $grandTotal - $paidAmount);

                fputcsv($handle, [
                    $invoice->invoice_number,
                    $invoice->salesOrder?->so_number ?? '-',
                    $invoice->bill_to_snapshot,
                    $invoice->invoice_date,
                    $invoice->due_date,
                    number_format($grandTotal, 2, '.', ''),
                    number_format($paidAmount, 2, '.', ''),
                    number_format($remaining, 2, '.', ''),
                    $invoice->status?->value ?? $invoice->status,
                ]);
            }

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}