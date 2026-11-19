<?php

namespace App\Services\Inventory;

use App\Enums\ProductSerialStatus;
use App\Enums\SalesOrderStatus;
use App\Enums\StockMovementType;
use App\Models\Inventory\Product;
use App\Models\Inventory\ProductSerial;
use App\Models\Inventory\Warehouse;
use App\Models\Sales\SalesOrder;
use Brick\Math\BigDecimal;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InventoryQueryService
{
    public function stockQuery(array $filters = []): Builder
    {
        $balances = DB::table('stock_movements as m')->select('m.product_id', 'm.warehouse_id')
            ->selectRaw(StockMovementType::balanceSql('m').' AS qty')->groupBy('m.product_id', 'm.warehouse_id');
        $query = DB::query()->fromSub($balances, 'b')
            ->join('products as p', 'p.product_id', '=', 'b.product_id')
            ->join('warehouses as w', 'w.warehouse_id', '=', 'b.warehouse_id')
            ->join('product_categories as c', 'c.category_id', '=', 'p.category_id')
            ->where('p.product_type', 'Barang')->where('p.is_active', true)->whereNull('p.deleted_at')
            ->select('p.product_id', 'p.sku', 'p.product_name as name', 'c.category_name as category',
                'w.warehouse_id', 'w.warehouse_name as warehouse', 'p.min_stock', 'p.unit', 'b.qty');
        if (! empty($filters['warehouse_id'])) {
            $query->where('w.warehouse_id', $filters['warehouse_id']);
        }
        if (trim($filters['search'] ?? '') !== '') {
            $pattern = $this->pattern($filters['search']);
            $query->where(fn ($q) => $q->whereRaw("p.sku LIKE ? ESCAPE '!'", [$pattern])->orWhereRaw("p.product_name LIKE ? ESCAPE '!'", [$pattern]));
        }

        return $query;
    }

    public function summary(): array
    {
        $row = DB::query()->fromSub($this->stockQuery(), 's')
            ->selectRaw('COUNT(DISTINCT product_id) AS skus, COUNT(DISTINCT warehouse_id) AS warehouses, COALESCE(SUM(qty), 0) AS units, COUNT(DISTINCT CASE WHEN qty < min_stock THEN product_id END) AS low')->first();

        return ['skus' => (int) $row->skus, 'warehouses' => (int) $row->warehouses, 'units' => (string) BigDecimal::of((string) $row->units)->toScale(3), 'low' => (int) $row->low];
    }

    public function stocks(array $filters): array
    {
        return $this->stockQuery($filters)->orderBy('p.sku')->orderBy('w.warehouse_id')
            ->paginate($filters['per_page'] ?? 10, ['*'], 'page', $filters['page'] ?? 1)
            ->through(fn ($row) => $this->stockRow($row))->toArray();
    }

    public function stockRow(object $row): array
    {
        return [...(array) $row, 'qty' => (string) BigDecimal::of((string) $row->qty)->toScale(3), 'min_stock' => (string) BigDecimal::of((string) $row->min_stock)->toScale(3)];
    }

    public function movementQuery(array $filters = []): Builder
    {
        $query = DB::table('stock_movements as m')->join('products as p', 'p.product_id', '=', 'm.product_id')
            ->join('warehouses as w', 'w.warehouse_id', '=', 'm.warehouse_id')
            ->leftJoin('goods_issue_items as gi', 'gi.goods_issue_item_id', '=', 'm.goods_issue_item_id')
            ->leftJoin('sales_order_items as si', 'si.so_item_id', '=', 'gi.so_item_id')
            ->leftJoin('goods_receipt_items as ri', 'ri.receipt_item_id', '=', 'm.receipt_item_id')
            ->leftJoin('goods_receipts as gr', 'gr.receipt_id', '=', 'ri.receipt_id')
            ->leftJoin('goods_issues as gd', 'gd.goods_issue_id', '=', 'gi.goods_issue_id')
            ->leftJoin('product_serials as sn', 'sn.serial_id', '=', 'm.serial_id')
            ->select('m.product_id', 'm.warehouse_id', 'm.receipt_item_id', 'm.goods_issue_item_id', 'm.movement_type as action')
            ->selectRaw('MAX(m.movement_id) AS movement_id, MAX(m.movement_date) AS movement_date, SUM(m.quantity) AS quantity,
                MAX(w.warehouse_name) AS warehouse,
                CASE WHEN COUNT(m.serial_id) = 1 THEN MAX(m.serial_id) ELSE NULL END AS serial_id,
                CASE WHEN COUNT(m.serial_id) = 1 THEN MAX(sn.serial_number) ELSE NULL END AS serial_number,
                MAX(COALESCE(gr.receipt_number, gd.goods_issue_number, m.reference_number)) AS reference,
                MAX(COALESCE(si.product_code_snapshot, p.sku)) AS sku,
                MAX(COALESCE(si.product_name_snapshot, p.product_name)) AS name,
                MAX(COALESCE(gi.unit, ri.unit, p.unit)) AS unit')
            ->groupBy('m.product_id', 'm.warehouse_id', 'm.receipt_item_id', 'm.goods_issue_item_id', 'm.movement_type')
            // Only posted document items are grouped. Standalone corrections/opening/reversals keep their identity.
            ->groupByRaw("CASE WHEN (m.movement_type = 'GOODS_IN' AND m.receipt_item_id IS NOT NULL)
                OR (m.movement_type = 'GOODS_OUT' AND m.goods_issue_item_id IS NOT NULL) THEN NULL ELSE m.movement_id END");
        if (! empty($filters['warehouse_id'])) {
            $query->where('m.warehouse_id', $filters['warehouse_id']);
        }
        if (trim($filters['search'] ?? '') !== '') {
            $pattern = $this->pattern($filters['search']);
            $columns = ['m.reference_number', 'gr.receipt_number', 'gd.goods_issue_number', 'p.sku', 'p.product_name', 'si.product_code_snapshot', 'si.product_name_snapshot', 'w.warehouse_name', 'sn.serial_number', 'm.movement_type'];
            $conditions = implode(' OR ', array_map(fn ($column) => "$column LIKE ? ESCAPE '!'", $columns));
            // A serial/legacy-reference match selects its whole item, not just the matching unit's quantity.
            $query->havingRaw("MAX(CASE WHEN ($conditions) THEN 1 ELSE 0 END) = 1", array_fill(0, count($columns), $pattern));
        }

        return DB::query()->fromSub($query, 'm');
    }

    public function movementRow(object $row): array
    {
        return [...(array) $row,
            'time' => Carbon::parse($row->movement_date, config('app.timezone'))->timezone('Asia/Jakarta')->format('d M Y, H:i'),
            'quantity' => (string) BigDecimal::of((string) $row->quantity)->toScale(3)];
    }

    public function movements(array $filters): array
    {
        $page = $this->movementQuery($filters)->orderByDesc('m.movement_date')->orderByDesc('m.movement_id')
            ->paginate($filters['per_page'] ?? 10, ['*'], 'page', $filters['page'] ?? 1);

        return $page->setCollection($this->movementRows($page->getCollection()))->toArray();
    }

    public function movementRows(Collection $rows): Collection
    {
        if ($rows->isEmpty()) {
            return $rows;
        }
        if ($rows->count() > 200) {
            return $rows->chunk(200)->flatMap(fn ($chunk) => $this->movementRows($chunk))->values();
        }
        // Batch-load full serial lists for the selected groups. SQL concatenation can silently truncate long lists.
        $serials = DB::table('stock_movements as m')->join('product_serials as sn', 'sn.serial_id', '=', 'm.serial_id')
            ->where(fn ($q) => $q->whereIn('m.receipt_item_id', $rows->pluck('receipt_item_id')->filter()->unique())
                ->orWhereIn('m.goods_issue_item_id', $rows->pluck('goods_issue_item_id')->filter()->unique())
                ->orWhereIn('m.movement_id', $rows->pluck('movement_id')))
            ->select('m.movement_id', 'm.product_id', 'm.warehouse_id', 'm.receipt_item_id', 'm.goods_issue_item_id', 'm.movement_type as action', 'sn.serial_number')
            ->orderBy('sn.serial_number')->get()->groupBy(fn ($row) => $this->movementGroup($row));

        return $rows->map(fn ($row) => [
            ...$this->movementRow($row),
            'serial_numbers' => $serials->get($this->movementGroup($row), collect())->pluck('serial_number')->uniqueStrict()->values()->all(),
        ]);
    }

    private function movementGroup(object $row): string
    {
        $documentItem = ($row->action === StockMovementType::GoodsIn->value && $row->receipt_item_id !== null)
            || ($row->action === StockMovementType::GoodsOut->value && $row->goods_issue_item_id !== null);

        return implode(':', [$row->product_id, $row->warehouse_id, $row->action, $row->receipt_item_id, $row->goods_issue_item_id, $documentItem ? '' : $row->movement_id]);
    }

    public function products(): array
    {
        return Product::query()->where('is_active', true)->whereNull('deleted_at')->where('product_type', 'Barang')->orderBy('sku')
            ->get(['product_id', 'sku', 'product_name', 'unit', 'track_serial'])->toArray();
    }

    public function warehouses(): array
    {
        return Warehouse::query()->where('is_active', true)->whereNull('deleted_at')->orderBy('warehouse_id')
            ->get(['warehouse_id', 'warehouse_name'])->toArray();
    }

    public function eligibleOrders()
    {
        return SalesOrder::query()->whereNotIn('status', [SalesOrderStatus::Completed->value, SalesOrderStatus::Cancelled->value])
            ->whereNull('cancelled_at')->whereHas('items', fn ($q) => $q->whereColumn('quantity_delivered', '<', 'quantity_ordered')
            ->whereHas('product', fn ($p) => $p->where('product_type', 'Barang')));
    }

    public function orders(): array
    {
        return $this->eligibleOrders()->orderByDesc('so_number')->get(['sales_order_id', 'so_number', 'sold_to_name_snapshot'])->map(fn ($o) => [
            'id' => $o->getKey(), 'number' => $o->so_number, 'customer' => $o->sold_to_name_snapshot,
        ])->all();
    }

    public function order(int|string $id): array
    {
        $order = $this->eligibleOrders()->findOrFail($id);
        $items = $order->items()->with('product')->whereColumn('quantity_delivered', '<', 'quantity_ordered')
            ->whereHas('product', fn ($q) => $q->where('product_type', 'Barang'))->orderBy('so_item_id')->get();
        $warehouseIds = array_column($this->warehouses(), 'warehouse_id');
        $stocks = $this->stockQuery()->whereIn('p.product_id', $items->pluck('product_id'))->whereIn('w.warehouse_id', $warehouseIds)->where('b.qty', '>', 0)->get();
        $serials = ProductSerial::query()->whereIn('product_id', $items->pluck('product_id'))->whereIn('warehouse_id', $warehouseIds)->where('status', ProductSerialStatus::InStock)->orderBy('serial_number')->get();

        return ['id' => $order->getKey(), 'number' => $order->so_number, 'customer' => $order->sold_to_name_snapshot,
            'items' => $items->map(fn ($item) => [
                'id' => $item->getKey(), 'product_id' => $item->product_id, 'sku' => $item->product_code_snapshot,
                'name' => $item->product_name_snapshot, 'unit' => $item->unit_snapshot,
                'ordered' => $item->quantity_ordered, 'delivered' => $item->quantity_delivered, 'track_serial' => $item->product->track_serial,
                'stocks' => $stocks->where('product_id', $item->product_id)->map(fn ($s) => [
                    ...$this->stockRow($s), 'serials' => $serials->where('product_id', $item->product_id)->where('warehouse_id', $s->warehouse_id)
                        ->map(fn ($sn) => ['id' => $sn->getKey(), 'number' => $sn->serial_number])->values()->all(),
                ])->values()->all(),
            ])->all()];
    }

    private function pattern(string $value): string
    {
        return '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], trim($value)).'%';
    }
}
