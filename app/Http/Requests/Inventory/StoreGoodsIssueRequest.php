<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class StoreGoodsIssueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('inventory.update') && $this->user()->can('inventory.post');
    }

    public function rules(): array
    {
        return [
            'request_key' => ['required', 'uuid'],
            'sales_order_id' => ['required', 'integer', 'min:1'],
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['required', 'array:so_item_id,warehouse_id,quantity,serial_ids'],
            'items.*.so_item_id' => ['required', 'integer', 'min:1', 'distinct'],
            'items.*.warehouse_id' => ['required', 'integer', 'min:1'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'regex:/^\d{1,15}(?:\.\d{1,3})?$/'],
            'items.*.serial_ids' => ['present', 'array'],
            'items.*.serial_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
