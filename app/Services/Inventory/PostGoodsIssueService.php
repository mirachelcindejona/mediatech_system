<?php

namespace App\Services\Inventory;

use App\Enums\GoodsIssueStatus;
use App\Enums\ProductSerialStatus;
use App\Enums\SalesOrderStatus;
use App\Enums\StockMovementType;
use App\Models\Inventory\GoodsIssue;
use App\Models\Inventory\ProductSerial;
use App\Models\Sales\SalesOrder;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Brick\Math\BigDecimal;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PostGoodsIssueService
{
    public function __construct(private UpdateStockService $stock, private AuditLogService $audit, private InventoryDocumentNumberService $numbers) {}

    private function canonicalItems(array $items): array
    {
        $canonical = [];
        foreach ($items as $item) {
            $serials = array_map('intval', $item['serial_ids']);
            sort($serials);
            $canonical[(int) $item['so_item_id']] = [(int) $item['warehouse_id'], (string) BigDecimal::of((string) $item['quantity'])->toScale(3), $serials];
        }
        ksort($canonical);

        return $canonical;
    }

    public function post(User $actor, array $data): GoodsIssue
    {
        foreach (['inventory.read', 'inventory.update', 'inventory.post'] as $permission) {
            Gate::forUser($actor)->authorize($permission);
        }
        $postingKey = hash('sha256', $actor->getKey().':'.$data['request_key']);
        $legacyNumber = 'GI-'.strtoupper(substr($postingKey, 0, 32));
        $requestId = $this->stock->requestId();
        try {
            return DB::transaction(function () use ($actor, $data, $postingKey, $legacyNumber, $requestId) {
                $order = SalesOrder::query()->whereKey($data['sales_order_id'])->lockForUpdate()->first();
                if (! $order) {
                    throw ValidationException::withMessages(['sales_order_id' => 'Sales Order tidak ditemukan.']);
                }
                $existing = GoodsIssue::query()->with('items.stockMovements')
                    ->where(fn ($q) => $q->where('posting_key', $postingKey)->orWhere('goods_issue_number', $legacyNumber))->first();
                if ($existing) {
                    $items = $existing->items->map(fn ($i) => ['so_item_id' => $i->so_item_id, 'warehouse_id' => $i->warehouse_id, 'quantity' => $i->quantity_issued, 'serial_ids' => $i->stockMovements->pluck('serial_id')->filter()->values()->all()])->all();
                    if ((int) $existing->sales_order_id !== (int) $order->getKey() || ($existing->notes ?? '') !== ($data['notes'] ?? '')
                        || $this->canonicalItems($items) !== $this->canonicalItems($data['items'])
                        || $existing->status !== GoodsIssueStatus::Posted || $existing->deleted_at) {
                        throw ValidationException::withMessages(['request_key' => 'Konfirmasi ini sudah digunakan untuk data yang berbeda. Muat ulang sebelum mengirim transaksi baru.']);
                    }

                    return $existing;
                }
                if (in_array($order->status, [SalesOrderStatus::Completed->value, SalesOrderStatus::Cancelled->value], true) || $order->cancelled_at) {
                    throw ValidationException::withMessages(['sales_order_id' => 'Sales Order sudah selesai atau dibatalkan.']);
                }
                $orderItems = $order->items()->orderBy('so_item_id')->lockForUpdate()->get()->keyBy('so_item_id');
                $chosen = collect($data['items']);
                if ($chosen->contains(fn ($line) => ! $orderItems->has($line['so_item_id']))) {
                    throw ValidationException::withMessages(['items' => 'Barang bukan bagian dari Sales Order ini.']);
                }
                [$products] = $this->stock->lockMasters($orderItems->pluck('product_id')->all(), $chosen->pluck('warehouse_id')->all(),
                    $chosen->map(fn ($line) => $orderItems[$line['so_item_id']]->product_id)->all());
                $serialIds = $chosen->flatMap(fn ($line) => $line['serial_ids'])->all();
                $serials = ProductSerial::query()->whereKey($serialIds)->orderBy('serial_id')->lockForUpdate()->get()->keyBy('serial_id');
                $usedSerials = [];
                $stockBefore = [];
                $stockUsed = [];
                foreach ($data['items'] as $index => $line) {
                    $item = $orderItems[$line['so_item_id']];
                    $product = $products[$item->product_id];
                    $qty = BigDecimal::of((string) $line['quantity'])->toScale(3);
                    $remaining = BigDecimal::of($item->quantity_ordered)->minus($item->quantity_delivered);
                    if (! $qty->isPositive() || $qty->isGreaterThan($remaining)) {
                        throw ValidationException::withMessages(["items.$index.quantity" => 'Jumlah melebihi sisa pesanan.']);
                    }
                    if (mb_strlen($item->unit_snapshot) > 30) {
                        throw ValidationException::withMessages(["items.$index" => 'Satuan snapshot SO melebihi kapasitas satuan Goods Out.']);
                    }
                    $key = $item->product_id.':'.$line['warehouse_id'];
                    $stockBefore[$key] ??= $this->stock->quantity($item->product_id, $line['warehouse_id'], lock: true);
                    $stockUsed[$key] = ($stockUsed[$key] ?? BigDecimal::zero())->plus($qty);
                    if ($stockUsed[$key]->isGreaterThan($stockBefore[$key])) {
                        throw ValidationException::withMessages(["items.$index.quantity" => 'Stok gudang tidak cukup. Data mungkin sudah berubah.']);
                    }
                    if ($product->track_serial && (! $qty->isEqualTo(count($line['serial_ids'])) || ! $line['serial_ids'])) {
                        throw ValidationException::withMessages(["items.$index.serial_ids" => 'Pilih satu serial tersedia untuk setiap unit. Stok lama tanpa serial tidak dapat dikirim sebagai barang berserial.']);
                    }
                    if (! $product->track_serial && $line['serial_ids']) {
                        throw ValidationException::withMessages(["items.$index.serial_ids" => 'Produk ini tidak menggunakan nomor serial.']);
                    }
                    foreach ($line['serial_ids'] as $id) {
                        $serial = $serials->get($id);
                        if (! $serial || isset($usedSerials[$id]) || (int) $serial->product_id !== (int) $item->product_id
                            || (int) $serial->warehouse_id !== (int) $line['warehouse_id'] || $serial->status !== ProductSerialStatus::InStock) {
                            throw ValidationException::withMessages(["items.$index.serial_ids" => 'Serial tidak tersedia untuk barang dan gudang yang dipilih.']);
                        }
                        $usedSerials[$id] = true;
                    }
                }
                $issue = GoodsIssue::query()->create([
                    'goods_issue_number' => 'TMP-'.Str::uuid(), 'posting_key' => $postingKey, 'sales_order_id' => $order->getKey(), 'created_by' => $actor->getKey(),
                    'created_at' => now(), 'status' => GoodsIssueStatus::Draft, 'notes' => $data['notes'] ?? null,
                ]);
                $number = $this->numbers->assign($issue);
                $deliveredBefore = $orderItems->map->quantity_delivered->all();
                foreach ($data['items'] as $line) {
                    $item = $orderItems[$line['so_item_id']];
                    $qty = (string) BigDecimal::of((string) $line['quantity'])->toScale(3);
                    $issueItem = $issue->items()->create([
                        'so_item_id' => $item->getKey(), 'warehouse_id' => $line['warehouse_id'], 'quantity_issued' => $qty, 'unit' => $item->unit_snapshot,
                    ]);
                    foreach ($line['serial_ids'] as $id) {
                        $serial = $serials[$id];
                        $this->stock->record($actor, $item->product_id, $line['warehouse_id'], '1.000', StockMovementType::GoodsOut, $number, issueItemId: $issueItem->getKey(), serialId: $serial->getKey());
                        $serial->update(['status' => ProductSerialStatus::Issued, 'warehouse_id' => null]);
                        $this->stock->history($actor, 'PRODUCT_SERIALS', $serial->getKey(), ProductSerialStatus::InStock->value, ProductSerialStatus::Issued->value, $requestId);
                    }
                    if (! $line['serial_ids']) {
                        $this->stock->record($actor, $item->product_id, $line['warehouse_id'], $qty, StockMovementType::GoodsOut, $number, issueItemId: $issueItem->getKey());
                    }
                    $item->update(['quantity_delivered' => (string) BigDecimal::of($item->quantity_delivered)->plus($qty)]);
                }
                $goods = $orderItems->filter(fn ($item) => $products[$item->product_id]->product_type === 'Barang');
                $status = $goods->every(fn ($i) => BigDecimal::of($i->quantity_delivered)->isGreaterThanOrEqualTo($i->quantity_ordered))
                    ? SalesOrderStatus::Completed->value : SalesOrderStatus::Processing->value;
                $oldStatus = $order->status;
                $order->update(['status' => $status]);
                $issue->update(['status' => GoodsIssueStatus::Posted, 'posted_at' => now()]);
                $this->stock->history($actor, 'GOODS_ISSUES', $issue->getKey(), GoodsIssueStatus::Draft->value, GoodsIssueStatus::Posted->value, $requestId);
                if ($oldStatus !== $status) {
                    $this->stock->history($actor, 'SALES_ORDERS', $order->getKey(), $oldStatus, $status, $requestId);
                }
                $this->audit->record($actor, 'GOODS_OUT', 'GOODS_ISSUES', $issue->getKey(), 'Pengeluaran barang '.$number.' diposting.',
                    ['stock' => array_map('strval', $stockBefore), 'delivered' => $deliveredBefore, 'sales_order_status' => $oldStatus],
                    ['goods_issue_number' => $number, 'sales_order_id' => $order->getKey(), 'items' => $data['items'], 'delivered' => $orderItems->map->quantity_delivered->all(), 'sales_order_status' => $status]);

                return $issue;
            }, 3);
        } catch (UniqueConstraintViolationException $e) {
            throw ValidationException::withMessages(['request_key' => 'Konfirmasi sudah diproses. Muat ulang untuk melihat hasilnya.']);
        }
    }
}
