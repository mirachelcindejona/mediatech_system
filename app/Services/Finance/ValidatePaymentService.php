<?php

namespace App\Services\Finance;

use App\Enums\CashTransactionSource;
use App\Enums\CashTransactionStatus;
use App\Enums\CashTransactionType;
use App\Enums\PaymentStatus;
use App\Models\Finance\CashTransaction;
use App\Models\Finance\Payment;
use App\Models\Finance\PaymentAllocation;
use App\Models\Sales\Invoice;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ValidatePaymentService
{
    public function __construct(private readonly AuditLogService $auditLog)
    {
    }

    public function handle(Payment $payment, User $actor): Payment
    {
        return DB::transaction(function () use ($payment, $actor) {
            /** @var Payment $lockedPayment */
            $lockedPayment = Payment::query()
                ->where('payment_id', $payment->payment_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedPayment->status !== PaymentStatus::PENDING_VALIDATION) {
                throw ValidationException::withMessages([
                    'payment' => 'Pembayaran ini sudah diproses dan tidak lagi menunggu validasi.',
                ]);
            }

            $allocations = PaymentAllocation::query()
                ->where('payment_id', $lockedPayment->payment_id)
                ->orderBy('invoice_id')
                ->get();

            if ($allocations->isEmpty()) {
                throw ValidationException::withMessages([
                    'payment' => 'Pembayaran tidak memiliki alokasi invoice.',
                ]);
            }

            $allocatedTotal = round((float) $allocations->sum('allocated_amount'), 2);
            if (abs($allocatedTotal - (float) $lockedPayment->amount) >= 0.01) {
                throw ValidationException::withMessages([
                    'payment' => 'Total alokasi pembayaran tidak sesuai dengan nominal Payment.',
                ]);
            }

            $invoiceRecords = [];

            foreach ($allocations as $allocation) {
                /** @var Invoice $invoice */
                $invoice = Invoice::query()
                    ->where('invoice_id', $allocation->invoice_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $alreadyValidated = (float) DB::table('payment_allocations as pa')
                    ->join('payments as p', 'p.payment_id', '=', 'pa.payment_id')
                    ->where('pa.invoice_id', $invoice->invoice_id)
                    ->where('p.status', PaymentStatus::VALIDATED->value)
                    ->where('p.payment_id', '<>', $lockedPayment->payment_id)
                    ->sum('pa.allocated_amount');

                $newPaidTotal = round($alreadyValidated + (float) $allocation->allocated_amount, 2);
                if ($newPaidTotal - (float) $invoice->grand_total > 0.009) {
                    throw ValidationException::withMessages([
                        'payment' => 'Sisa tagihan invoice ' . $invoice->invoice_number
                            . ' sudah berubah. Pembayaran tidak dapat divalidasi karena akan melebihi total invoice.',
                    ]);
                }

                $invoiceRecords[] = [
                    'invoice' => $invoice,
                    'new_paid_total' => $newPaidTotal,
                ];
            }

            $oldStatus = $lockedPayment->status->value;
            $lockedPayment->status = PaymentStatus::VALIDATED;
            $lockedPayment->validated_by = $actor->user_id;
            $lockedPayment->validated_at = now();
            $lockedPayment->save();

            foreach ($invoiceRecords as $record) {
                /** @var Invoice $invoice */
                $invoice = $record['invoice'];
                $newStatus = $record['new_paid_total'] >= (float) $invoice->grand_total
                    ? 'Paid'
                    : 'Partially Paid';

                DB::table('invoices')
                    ->where('invoice_id', $invoice->invoice_id)
                    ->update([
                        'status' => $newStatus,
                        'updated_at' => now(),
                    ]);
            }

            $invoiceNumbers = collect($invoiceRecords)
                ->map(fn (array $record) => $record['invoice']->invoice_number)
                ->filter()
                ->unique()
                ->implode(', ');

            // payment_id memiliki unique constraint di cash_transactions; firstOrCreate
            // menjaga agar validasi ulang tidak membuat Cash In ganda.
            $cashTransactionNumber = preg_replace('/^PAY-/', 'CSH-', $lockedPayment->payment_number);
            if (CashTransaction::query()->where('transaction_number', $cashTransactionNumber)->exists()) {
                // Nomor CSH juga dipakai sumber Cash In/Cash Out lain seperti Petty Cash.
                $cashTransactionNumber = 'CSH-' . now()->format('Y') . '-PAY-' . $lockedPayment->payment_id;
            }

            CashTransaction::query()->firstOrCreate(
                ['payment_id' => $lockedPayment->payment_id],
                [
                    'transaction_number' => $cashTransactionNumber,
                    'transaction_date' => $lockedPayment->payment_date?->copy()->startOfDay() ?? now(),
                    'transaction_type' => CashTransactionType::CASH_IN,
                    'source_type' => CashTransactionSource::PAYMENT,
                    'category' => 'Pembayaran customer',
                    'description' => 'Pembayaran ' . $invoiceNumbers,
                    'amount' => $lockedPayment->amount,
                    'bank_account_id' => $lockedPayment->bank_account_id,
                    'petty_cash_id' => null,
                    'recorded_by' => $actor->user_id,
                    'status' => CashTransactionStatus::POSTED,
                ]
            );

            $requestId = (string) (request()->attributes->get('request_id') ?? Str::uuid());
            DB::table('transaction_status_history')->insert([
                'entity_name' => 'PAYMENTS',
                'entity_id' => (string) $lockedPayment->payment_id,
                'old_status' => $oldStatus,
                'new_status' => PaymentStatus::VALIDATED->value,
                'changed_by' => $actor->user_id,
                'changed_at' => now(),
                'reason' => null,
                'request_id' => $requestId,
            ]);

            $this->auditLog->record(
                $actor,
                'VALIDATE_PAYMENT',
                'PAYMENTS',
                $lockedPayment->payment_id,
                'Pembayaran tervalidasi dan Cash In dicatat otomatis.',
                ['status' => $oldStatus],
                [
                    'status' => PaymentStatus::VALIDATED->value,
                    'validated_by' => $actor->user_id,
                    'validated_at' => $lockedPayment->validated_at?->toDateTimeString(),
                    'cash_transaction_created' => true,
                ]
            );

            return $lockedPayment->load([
                'bankAccount',
                'recordedBy',
                'validatedBy',
                'attachments',
                'allocations.invoice',
            ]);
        });
    }
}
