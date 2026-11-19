<?php

namespace App\Services\Finance;

use App\Enums\PaymentStatus;
use App\Models\Finance\BankAccount;
use App\Models\Finance\Payment;
use App\Models\Finance\PaymentAllocation;
use App\Models\Finance\PaymentAttachment;
use App\Models\Sales\Invoice;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class RecordPaymentService
{
    public function __construct(private readonly AuditLogService $auditLog)
    {
    }

    public function handle(array $data, User $actor): Payment
    {
        $storedFiles = [];

        try {
            return DB::transaction(function () use ($data, $actor, &$storedFiles) {
                /** @var Invoice $invoice */
                $invoice = Invoice::query()
                    ->where('invoice_id', $data['invoice_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                $amount = round((float) $data['amount'], 2);
                $reservedAmount = (float) DB::table('payment_allocations as pa')
                    ->join('payments as p', 'p.payment_id', '=', 'pa.payment_id')
                    ->where('pa.invoice_id', $invoice->invoice_id)
                    ->whereIn('p.status', [
                        PaymentStatus::PENDING_VALIDATION->value,
                        PaymentStatus::VALIDATED->value,
                    ])
                    ->sum('pa.allocated_amount');

                $remainingAmount = max(0, round((float) $invoice->grand_total - $reservedAmount, 2));

                if ($amount <= 0 || $amount > $remainingAmount) {
                    throw ValidationException::withMessages([
                        'amount' => 'Nominal pembayaran tidak boleh melebihi sisa tagihan yang masih tersedia ('
                            . number_format($remainingAmount, 2, ',', '.') . ').',
                    ]);
                }

                $bankAccountId = $data['bank_account_id'] ?? null;
                if ($bankAccountId) {
                    $accountExists = BankAccount::query()
                        ->where('bank_account_id', $bankAccountId)
                        ->where('is_active', true)
                        ->whereNull('deleted_at')
                        ->exists();

                    if (!$accountExists) {
                        throw ValidationException::withMessages([
                            'bank_account_id' => 'Rekening tujuan tidak aktif atau sudah tidak tersedia.',
                        ]);
                    }
                }

                // Nomor final dibentuk dari payment_id agar tetap unik saat ada request bersamaan.
                $payment = Payment::query()->create([
                    'payment_number' => 'TEMP-' . Str::uuid(),
                    'payment_date' => $data['payment_date'],
                    'amount' => number_format($amount, 2, '.', ''),
                    'payment_method' => $data['payment_method'],
                    'bank_account_id' => $bankAccountId,
                    'reference_number' => trim($data['reference_number']),
                    'notes' => $data['notes'] ?? null,
                    'recorded_by' => $actor->user_id,
                    'validated_by' => null,
                    'validated_at' => null,
                    'status' => PaymentStatus::PENDING_VALIDATION,
                ]);

                $payment->payment_number = sprintf('PAY-%s-%03d', now()->format('Y'), $payment->payment_id);
                $payment->save();

                PaymentAllocation::query()->create([
                    'payment_id' => $payment->payment_id,
                    'invoice_id' => $invoice->invoice_id,
                    'allocated_amount' => number_format($amount, 2, '.', ''),
                ]);

                $proof = $data['proof'] ?? null;
                if ($proof instanceof UploadedFile) {
                    $path = $proof->store('payment-proofs', 'local');
                    if (!$path) {
                        throw new \RuntimeException('Bukti pembayaran gagal disimpan.');
                    }

                    $storedFiles[] = $path;

                    PaymentAttachment::query()->create([
                        'payment_id' => $payment->payment_id,
                        'file_name' => mb_substr($proof->getClientOriginalName(), 0, 255),
                        'file_path' => $path,
                        'media_type' => $proof->getMimeType() ?: 'application/octet-stream',
                        'file_size_kb' => (int) ceil($proof->getSize() / 1024),
                        'uploaded_by' => $actor->user_id,
                        'uploaded_at' => now(),
                    ]);
                }

                $this->auditLog->record(
                    $actor,
                    'RECORD_PAYMENT',
                    'PAYMENTS',
                    $payment->payment_id,
                    'Pembayaran dicatat dan menunggu validasi.',
                    [],
                    [
                        'payment_number' => $payment->payment_number,
                        'invoice_id' => $invoice->invoice_id,
                        'amount' => number_format($amount, 2, '.', ''),
                        'status' => PaymentStatus::PENDING_VALIDATION->value,
                    ]
                );

                return $payment->load([
                    'bankAccount',
                    'recordedBy',
                    'attachments',
                    'allocations.invoice',
                ]);
            });
        } catch (Throwable $exception) {
            foreach ($storedFiles as $storedFile) {
                Storage::disk('local')->delete($storedFile);
            }

            throw $exception;
        }
    }
}
