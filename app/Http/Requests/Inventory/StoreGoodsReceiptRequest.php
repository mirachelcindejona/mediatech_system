<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class StoreGoodsReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('inventory.create') && $this->user()->can('inventory.post');
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'min:1'],
            'warehouse_id' => ['required', 'integer', 'min:1'],
            'request_key' => ['nullable', 'uuid'],
            'reference' => ['nullable', 'string', 'max:50'],
            'quantity' => ['required', 'numeric', 'gt:0', 'regex:/^\d{1,15}(?:\.\d{1,3})?$/'],
            'serial_numbers' => ['sometimes', 'array'],
            'serial_numbers.*' => ['required', 'string', 'max:120', 'distinct:ignore_case'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
