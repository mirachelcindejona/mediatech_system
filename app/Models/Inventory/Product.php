<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $primaryKey = 'product_id';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['selling_price' => 'decimal:2', 'purchase_price' => 'decimal:2', 'min_stock' => 'decimal:3', 'track_serial' => 'boolean', 'is_active' => 'boolean', 'deleted_at' => 'datetime'];
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'product_id');
    }

    public function serials(): HasMany
    {
        return $this->hasMany(ProductSerial::class, 'product_id');
    }
}
