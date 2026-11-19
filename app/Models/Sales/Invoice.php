<?php

namespace App\Models\Sales;

use App\Enums\InvoiceStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    protected $table = 'invoices';

    protected $primaryKey = 'invoice_id';

    protected $fillable = [
        'invoice_number',
        'sales_order_id',
        'created_by',
        'reference_number',
        'sales_name_snapshot',
        'bill_to_snapshot',
        'bill_to_address_snapshot',
        'ship_to_snapshot',
        'ship_to_address_snapshot',
        'contact_phone_snapshot',
        'attention_snapshot',
        'subtotal',
        'discount_amount',
        'tax_rate',
        'tax_amount',
        'other_charges',
        'grand_total',
        'payment_terms_snapshot',
        'invoice_date',
        'due_date',
        'invoice_type',
        'status',
        'total_amount',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_rate' => 'decimal:4',
            'tax_amount' => 'decimal:2',
            'other_charges' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'invoice_date' => 'date',
            'due_date' => 'date',
            'status' => InvoiceStatus::class,
        ];
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id', 'sales_order_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class, 'invoice_id', 'invoice_id');
    }
}
