<?php

namespace App\Services\Inventory;

use App\Enums\StockMovementType;
use App\Models\Inventory\Product;
use App\Models\Inventory\StockMovement;
use App\Models\Inventory\Warehouse;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UpdateStockService
{
    public function lockMasters(array $productIds, array $warehouseIds, ?array $stockProductIds = null): array
    {
        $productIds = array_unique($productIds);
        $warehouseIds = array_unique($warehouseIds);
        $products = Product::query()->whereKey($productIds)->orderBy('product_id')->lockForUpdate()->get()->keyBy('product_id');
        $warehouses = Warehouse::query()->whereKey($warehouseIds)->orderBy('warehouse_id')->lockForUpdate()->get()->keyBy('warehouse_id');
        $stockProductIds = array_unique($stockProductIds ?? $productIds);
        $selected = $products->only($stockProductIds);
        if ($products->count() !== count($productIds) || $selected->count() !== count($stockProductIds)
            || $selected->contains(fn ($p) => ! $p->is_active || $p->deleted_at || $p->product_type !== 'Barang')) {
            throw ValidationException::withMessages(['product_id' => 'Barang tidak ditemukan, tidak aktif, atau bukan produk stok.']);
        }
        if ($warehouses->count() !== count($warehouseIds) || $warehouses->contains(fn ($w) => ! $w->is_active || $w->deleted_at)) {
            throw ValidationException::withMessages(['warehouse_id' => 'Gudang tidak ditemukan atau tidak aktif.']);
        }

        return [$products, $warehouses];
    }

    public function quantity(int $productId, int $warehouseId, bool $lock = false): BigDecimal
    {
        $row = DB::table('stock_movements')->where('product_id', $productId)->where('warehouse_id', $warehouseId)
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->selectRaw(StockMovementType::balanceSql().' AS balance')->first();

        return BigDecimal::of((string) $row->balance)->toScale(3);
    }

    public function record(User $actor, int $productId, int $warehouseId, string $quantity, StockMovementType $type, string $reference, ?int $receiptItemId = null, ?int $issueItemId = null, ?int $serialId = null): StockMovement
    {
        return StockMovement::query()->create([
            'product_id' => $productId, 'warehouse_id' => $warehouseId, 'quantity' => $quantity,
            'movement_type' => $type, 'reference_number' => $reference, 'recorded_by' => $actor->getKey(),
            'movement_date' => now(), 'receipt_item_id' => $receiptItemId,
            'goods_issue_item_id' => $issueItemId, 'serial_id' => $serialId,
        ]);
    }

    public function requestId(): string
    {
        $request = request();
        $id = (string) ($request->attributes->get('request_id') ?? Str::uuid());
        $request->attributes->set('request_id', $id);

        return $id;
    }

    public function history(User $actor, string $entity, int $id, string $old, string $new, string $requestId): void
    {
        DB::table('transaction_status_history')->insert([
            'entity_name' => $entity, 'entity_id' => (string) $id, 'old_status' => $old,
            'new_status' => $new, 'changed_by' => $actor->getKey(), 'changed_at' => now(),
            'reason' => null, 'request_id' => $requestId,
        ]);
    }
}
