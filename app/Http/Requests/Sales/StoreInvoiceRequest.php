<?php

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('invoices.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'sales_order_id' => ['required', 'integer', Rule::exists('sales_orders', 'sales_order_id')],
            'invoice_type' => ['required', 'string', Rule::in(['Barang keluar', 'Termin'])],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:invoice_date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.so_item_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('sales_order_items', 'so_item_id'),
            ],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'decimal:0,3'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'sales_order_id.required' => 'Sales Order wajib dipilih.',
            'invoice_type.in' => 'Tipe invoice harus Barang keluar atau Termin.',
            'due_date.after_or_equal' => 'Tanggal jatuh tempo tidak boleh sebelum tanggal invoice.',
            'items.required' => 'Pilih minimal satu item untuk ditagihkan.',
            'items.min' => 'Pilih minimal satu item untuk ditagihkan.',
            'items.*.so_item_id.distinct' => 'Item Sales Order tidak boleh dipilih lebih dari satu kali.',
            'items.*.quantity.gt' => 'Kuantitas tagihan harus lebih dari nol.',
        ];
    }
}
