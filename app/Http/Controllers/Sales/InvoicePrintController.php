<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Sales\Invoice;
use App\Services\Sales\BuildPrintDocumentDataService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\View\View;

class InvoicePrintController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private readonly BuildPrintDocumentDataService $printData)
    {
    }

    public function __invoke(Invoice $invoice): View
    {
        $this->authorize('print', $invoice);

        return view('print.invoices.show', $this->printData->forInvoice($invoice));
    }
}
