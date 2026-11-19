<?php

namespace App\Models\Sales;

use App\Models\Inventory\GoodsIssue;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesOrder extends Model
{
    use HasFactory;

    protected $primaryKey = 'sales_order_id';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['order_date' => 'date', 'delivery_date' => 'date', 'cancelled_at' => 'datetime',
            'subtotal' => 'decimal:2', 'discount_amount' => 'decimal:2', 'tax_rate' => 'decimal:4', 'tax_amount' => 'decimal:2',
            'other_charges' => 'decimal:2', 'total_amount' => 'decimal:2', 'grand_total' => 'decimal:2'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(SalesOrderItem::class, 'sales_order_id');
    }

    public function goodsIssues(): HasMany
    {
        return $this->hasMany(GoodsIssue::class, 'sales_order_id');
    }
}
