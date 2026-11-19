<?php

namespace App\Models\Inventory;

use App\Enums\StockMovementType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    protected $primaryKey = 'movement_id';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['movement_date' => 'datetime', 'quantity' => 'decimal:3', 'movement_type' => StockMovementType::class];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function receiptItem(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiptItem::class, 'receipt_item_id');
    }

    public function goodsIssueItem(): BelongsTo
    {
        return $this->belongsTo(GoodsIssueItem::class, 'goods_issue_item_id');
    }

    public function serial(): BelongsTo
    {
        return $this->belongsTo(ProductSerial::class, 'serial_id');
    }
}
