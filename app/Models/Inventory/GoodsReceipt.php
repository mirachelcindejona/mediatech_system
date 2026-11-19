<?php

namespace App\Models\Inventory;

use App\Enums\GoodsReceiptStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoodsReceipt extends Model
{
    protected $primaryKey = 'receipt_id';

    public $timestamps = false;

    protected $guarded = [];

    protected $hidden = ['posting_key', 'posting_fingerprint'];

    protected function casts(): array
    {
        return ['receipt_date' => 'date', 'posted_at' => 'datetime', 'status' => GoodsReceiptStatus::class];
    }

    public function items(): HasMany
    {
        return $this->hasMany(GoodsReceiptItem::class, 'receipt_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }
}
