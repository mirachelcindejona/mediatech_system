<?php

namespace App\Http\Controllers\Finance;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StorePaymentRequest;
use App\Http\Requests\Finance\ValidatePaymentRequest;
use App\Models\Finance\Payment;
use App\Models\Finance\PaymentAttachment;
use App\Services\Finance\PaymentQueryService;
use App\Services\Finance\RecordPaymentService;
use App\Services\Finance\ValidatePaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentQueryService $paymentQuery)
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Payment::class);

        $filters = $request->only(['search', 'status', 'per_page']);

        return view('finance.payments.index', [
            'payments' => $this->paymentQuery->paginate($filters),
            'summary' => $this->paymentQuery->summary(),
            'filters' => $this->paymentQuery->normalizedFilters($filters),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Payment::class);

        return view('finance.payments.create', [
            'invoices' => $this->paymentQuery->availableInvoices(),
            'bankAccounts' => $this->paymentQuery->activeBankAccounts(),
            'paymentMethods' => ['Transfer bank', 'Virtual account', 'Tunai', 'Giro'],
        ]);
    }

    public function store(StorePaymentRequest $request, RecordPaymentService $service)
    {
        $payment = $service->handle($request->validated(), $request->user());

        return redirect()
            ->route('payments.show', $payment)
            ->with('success', $payment->payment_number . ' berhasil dicatat dan menunggu validasi.');
    }

    public function show(Payment $payment): View
    {
        $this->authorize('view', $payment);

        $payment->load([
            'bankAccount',
            'recordedBy',
            'validatedBy',
            'attachments.uploadedBy',
            'allocations.invoice',
        ]);

        return view('finance.payments.show', compact('payment'));
    }

    public function validatePayment(
        ValidatePaymentRequest $request,
        Payment $payment,
        ValidatePaymentService $service
    ) {
        $this->authorize('validate', $payment);

        $validatedPayment = $service->handle($payment, $request->user());

        return redirect()
            ->route('payments.index')
            ->with('success', $validatedPayment->payment_number . ' berhasil divalidasi. Cash In telah dicatat otomatis.');
    }

    public function showAttachment(Payment $payment, PaymentAttachment $attachment)
    {
        $this->authorize('view', $payment);

        abort_unless((int) $attachment->payment_id === (int) $payment->payment_id, 404);
        abort_unless(Storage::disk('local')->exists($attachment->file_path), 404);

        return Storage::disk('local')->response(
            $attachment->file_path,
            $attachment->file_name,
            [
                'Content-Type' => $attachment->media_type,
                'X-Content-Type-Options' => 'nosniff',
            ],
            'inline'
        );
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', Payment::class);
        abort_unless($request->user()?->can('payments.export'), 403);

        $filters = $request->only(['search', 'status']);
        $query = $this->paymentQuery->exportRows($filters);
        $filename = 'payments-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($query) {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");

            fputcsv($output, [
                'No. Payment',
                'No. Invoice',
                'Customer',
                'Tanggal Pembayaran',
                'Metode Pembayaran',
                'Nominal (Rp)',
                'Rekening Tujuan',
                'Nomor Referensi',
                'Dicatat Oleh',
                'Status',
                'Divalidasi Oleh',
                'Waktu Validasi',
            ]);

            $query->chunkById(500, function ($payments) use ($output) {
                foreach ($payments as $payment) {
                    $invoiceNumbers = $payment->allocations
                        ->map(fn ($allocation) => $allocation->invoice?->invoice_number)
                        ->filter()
                        ->unique()
                        ->implode(', ');
                    $customers = $payment->allocations
                        ->map(fn ($allocation) => $allocation->invoice?->bill_to_snapshot)
                        ->filter()
                        ->unique()
                        ->implode(', ');

                    $status = $payment->status instanceof PaymentStatus
                        ? $payment->status->value
                        : (string) $payment->status;

                    fputcsv($output, [
                        $payment->payment_number,
                        $invoiceNumbers,
                        $customers,
                        $payment->payment_date?->format('d M Y') ?? '',
                        $payment->payment_method,
                        number_format((float) $payment->amount, 2, ',', '.'),
                        $payment->bankAccount?->account_code ?? '',
                        $payment->reference_number,
                        $payment->recordedBy?->name ?? '',
                        $status,
                        $payment->validatedBy?->name ?? '',
                        $payment->validated_at?->format('d M Y H:i') ?? '',
                    ]);
                }
            }, 'payments.payment_id', 'payment_id');

            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
