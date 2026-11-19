<?php

namespace App\Models\Sales;

use App\Models\Inventory\GoodsIssueItem;
use App\Models\Inventory\Product;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesOrderItem extends Model
{
    use HasFactory;

    protected $primaryKey = 'so_item_id';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['quantity_ordered' => 'decimal:3', 'quantity_delivered' => 'decimal:3', 'unit_price' => 'decimal:2', 'unit_cost_snapshot' => 'decimal:2', 'discount_amount' => 'decimal:2', 'subtotal' => 'decimal:2'];
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function goodsIssueItems(): HasMany
    {
        return $this->hasMany(GoodsIssueItem::class, 'so_item_id');
    }
}
