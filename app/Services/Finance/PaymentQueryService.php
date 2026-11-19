<?php

namespace App\Services\Finance;

use App\Enums\PaymentStatus;
use App\Models\Finance\BankAccount;
use App\Models\Finance\Payment;
use App\Models\Sales\Invoice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PaymentQueryService
{
    private const VALID_PER_PAGE = [5, 10, 25, 50];

    private const VALID_STATUSES = [
        PaymentStatus::PENDING_VALIDATION->value,
        PaymentStatus::VALIDATED->value,
        PaymentStatus::REJECTED->value,
    ];

    public function normalizedFilters(array $filters): array
    {
        $perPage = (int) ($filters['per_page'] ?? 10);
        if (!in_array($perPage, self::VALID_PER_PAGE, true)) {
            $perPage = 10;
        }

        $status = $filters['status'] ?? null;
        if ($status !== null && !in_array($status, self::VALID_STATUSES, true)) {
            $status = null;
        }

        return [
            'search' => trim((string) ($filters['search'] ?? '')),
            'status' => $status,
            'per_page' => $perPage,
        ];
    }

    public function paginate(array $filters): LengthAwarePaginator
    {
        $filters = $this->normalizedFilters($filters);

        return $this->filteredQuery($filters)
            ->with(['bankAccount', 'recordedBy', 'validatedBy', 'allocations.invoice'])
            ->orderByDesc('payment_date')
            ->orderByDesc('payment_id')
            ->paginate($filters['per_page'])
            ->withQueryString();
    }

    public function summary(): array
    {
        $validatedThisMonth = Payment::query()
            ->where('status', PaymentStatus::VALIDATED->value)
            ->whereYear('payment_date', now()->year)
            ->whereMonth('payment_date', now()->month)
            ->sum('amount');

        return [
            'validated_this_month' => (float) $validatedThisMonth,
            'pending_count' => Payment::query()
                ->where('status', PaymentStatus::PENDING_VALIDATION->value)
                ->count(),
            'rejected_count' => Payment::query()
                ->where('status', PaymentStatus::REJECTED->value)
                ->count(),
        ];
    }

    /**
     * Mengambil semua Payment yang cocok dengan filter, tanpa pagination.
     * Query diurutkan berdasarkan primary key agar aman diproses dengan chunkById().
     */
    public function exportRows(array $filters): Builder
    {
        $filters = $this->normalizedFilters($filters);

        return $this->filteredQuery($filters)
            ->with(['bankAccount', 'recordedBy', 'validatedBy', 'allocations.invoice'])
            ->orderBy('payments.payment_id');
    }

    /**
     * Invoice yang masih dapat menerima pembayaran.
     * Pembayaran Pending Validation ikut mengurangi nominal yang dapat dipilih
     * agar beberapa transaksi pending tidak melampaui total invoice.
     */
    public function availableInvoices(): Collection
    {
        $reservedAmount = DB::table('payment_allocations as pa')
            ->join('payments as p', 'p.payment_id', '=', 'pa.payment_id')
            ->whereColumn('pa.invoice_id', 'invoices.invoice_id')
            ->whereIn('p.status', [
                PaymentStatus::PENDING_VALIDATION->value,
                PaymentStatus::VALIDATED->value,
            ])
            ->selectRaw('COALESCE(SUM(pa.allocated_amount), 0)');

        $paidAmount = DB::table('payment_allocations as pa')
            ->join('payments as p', 'p.payment_id', '=', 'pa.payment_id')
            ->whereColumn('pa.invoice_id', 'invoices.invoice_id')
            ->where('p.status', PaymentStatus::VALIDATED->value)
            ->selectRaw('COALESCE(SUM(pa.allocated_amount), 0)');

        return Invoice::query()
            ->select('invoices.*')
            ->selectSub($reservedAmount, 'reserved_payment_amount')
            ->selectSub($paidAmount, 'paid_payment_amount')
            ->orderByDesc('invoice_date')
            ->orderByDesc('invoice_id')
            ->get()
            ->map(function (Invoice $invoice) {
                $total = (float) $invoice->grand_total;
                $reserved = (float) $invoice->reserved_payment_amount;
                $paid = (float) $invoice->paid_payment_amount;
                $remaining = max(0, round($total - $reserved, 2));

                $invoice->setAttribute('paid_amount', round($paid, 2));
                $invoice->setAttribute('remaining_amount', $remaining);

                return $invoice;
            })
            ->filter(fn (Invoice $invoice) => $invoice->remaining_amount > 0)
            ->values();
    }

    public function activeBankAccounts(): Collection
    {
        return BankAccount::query()
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->orderBy('bank_name')
            ->orderBy('account_code')
            ->get();
    }

    private function filteredQuery(array $filters): Builder
    {
        $query = Payment::query();

        if ($filters['search'] !== '') {
            $search = '%' . $filters['search'] . '%';

            $query->where(function (Builder $builder) use ($search) {
                $builder->where('payments.payment_number', 'like', $search)
                    ->orWhere('payments.reference_number', 'like', $search)
                    ->orWhere('payments.notes', 'like', $search)
                    ->orWhereHas('allocations.invoice', function (Builder $invoiceQuery) use ($search) {
                        $invoiceQuery->where('invoice_number', 'like', $search)
                            ->orWhere('bill_to_snapshot', 'like', $search);
                    });
            });
        }

        if ($filters['status']) {
            $query->where('payments.status', $filters['status']);
        }

        return $query;
    }
}
