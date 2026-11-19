<?php

namespace App\Models\Inventory;

use App\Enums\ProductSerialStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductSerial extends Model
{
    protected $primaryKey = 'serial_id';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['status' => ProductSerialStatus::class];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'serial_id');
    }
}
