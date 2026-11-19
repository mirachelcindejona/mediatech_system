<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('payments.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'invoice_id' => ['required', 'integer', 'exists:invoices,invoice_id'],
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'payment_method' => ['required', Rule::in(['Transfer bank', 'Virtual account', 'Tunai', 'Giro'])],
            'bank_account_id' => [
                Rule::requiredIf(fn () => $this->input('payment_method') !== 'Tunai'),
                'nullable',
                'integer',
                'exists:bank_accounts,bank_account_id',
            ],
            'reference_number' => ['required', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'invoice_id.required' => 'Invoice harus dipilih.',
            'amount.gt' => 'Nominal pembayaran harus lebih besar dari 0.',
            'amount.decimal' => 'Nominal pembayaran maksimal menggunakan 2 angka desimal.',
            'bank_account_id.required' => 'Rekening tujuan harus dipilih untuk metode pembayaran ini.',
            'proof.mimes' => 'Bukti pembayaran harus berupa JPG, PNG, WebP, atau PDF.',
            'proof.max' => 'Ukuran bukti pembayaran maksimal 5 MB.',
        ];
    }
}
