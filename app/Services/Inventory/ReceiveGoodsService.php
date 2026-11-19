<?php

namespace App\Services\Inventory;

use App\Enums\GoodsReceiptStatus;
use App\Enums\ProductSerialStatus;
use App\Enums\StockMovementType;
use App\Models\Inventory\GoodsReceipt;
use App\Models\Inventory\ProductSerial;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Brick\Math\BigDecimal;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReceiveGoodsService
{
    public function __construct(private UpdateStockService $stock, private AuditLogService $audit, private InventoryDocumentNumberService $numbers) {}

    public function receive(User $actor, array $data): GoodsReceipt
    {
        foreach (['inventory.read', 'inventory.create', 'inventory.post'] as $permission) {
            Gate::forUser($actor)->authorize($permission);
        }
        $data['reference'] = trim($data['reference'] ?? '');
        $data['serial_numbers'] ??= [];
        $quantity = BigDecimal::of((string) $data['quantity'])->toScale(3);
        $expected = $data['serial_numbers'];
        sort($expected);
        $fingerprint = hash('sha256', json_encode([(int) $data['product_id'], (int) $data['warehouse_id'], $data['reference'], (string) $quantity, $expected, $data['notes'] ?? ''], JSON_THROW_ON_ERROR));
        $key = empty($data['request_key']) ? null : hash('sha256', $actor->getKey().':'.$data['request_key']);
        $requestId = $this->stock->requestId();
        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                return DB::transaction(function () use ($actor, $data, $quantity, $expected, $fingerprint, $key, $requestId) {
                    [$products] = $this->stock->lockMasters([$data['product_id']], [$data['warehouse_id']]);
                    $product = $products->get($data['product_id']);
                    $existing = $key ? GoodsReceipt::query()->where('posting_key', $key)->first() : null;
                    $byKey = $existing !== null;
                    $existing ??= $data['reference'] !== '' ? GoodsReceipt::query()->where('receipt_number', $data['reference'])->first() : null;
                    if ($existing) {
                        $items = $existing->items()->with('stockMovements.serial')->get();
                        $numbers = $items->flatMap(fn ($i) => $i->stockMovements->pluck('serial.serial_number'))->filter(fn ($number) => $number !== null)->sort()->values()->all();
                        $sameInput = $existing->posting_fingerprint
                            ? hash_equals($existing->posting_fingerprint, $fingerprint)
                            : $numbers === $expected;
                        if ((! $key || $byKey || ! $existing->posting_key) && $sameInput
                            && (int) $existing->received_by === (int) $actor->getKey()
                            && (int) $existing->warehouse_id === (int) $data['warehouse_id']
                            && $existing->status === GoodsReceiptStatus::Posted && ! $existing->deleted_at
                            && $items->count() === 1 && (int) $items[0]->product_id === (int) $product->getKey()
                            && BigDecimal::of($items[0]->quantity_received)->isEqualTo($quantity)
                            && ($existing->notes ?? '') === ($data['notes'] ?? '')) {
                            return $existing;
                        }
                        throw ValidationException::withMessages([$byKey ? 'request_key' : 'reference' => 'Nomor dokumen atau konfirmasi sudah digunakan untuk data yang berbeda.']);
                    }
                    $serials = $data['serial_numbers'];
                    if ($product->track_serial) {
                        if (! str_ends_with((string) $quantity, '.000') || ! $quantity->isPositive()
                            || ($serials !== [] && ! $quantity->isEqualTo(count($serials)))) {
                            throw ValidationException::withMessages(['serial_numbers' => 'Jumlah barang berserial harus bulat. Kosongkan serial untuk otomatis, atau isi satu serial unik per unit.']);
                        }
                        if (ProductSerial::query()->whereIn('serial_number', $serials)->exists()) {
                            throw ValidationException::withMessages(['serial_numbers' => 'Nomor serial sudah terdaftar.']);
                        }
                    } elseif ($serials !== []) {
                        throw ValidationException::withMessages(['serial_numbers' => 'Produk ini tidak menggunakan nomor serial.']);
                    }
                    $before = (string) $this->stock->quantity($product->getKey(), $data['warehouse_id'], lock: true);
                    $receipt = GoodsReceipt::query()->create([
                        'receipt_number' => $data['reference'] !== '' ? $data['reference'] : 'TMP-'.Str::uuid(), 'supplier_id' => null,
                        'posting_key' => $key, 'posting_fingerprint' => $fingerprint,
                        'warehouse_id' => $data['warehouse_id'], 'received_by' => $actor->getKey(),
                        'receipt_date' => now('Asia/Jakarta')->toDateString(), 'status' => GoodsReceiptStatus::Draft, 'notes' => $data['notes'] ?? null,
                    ]);
                    if ($data['reference'] === '') {
                        $this->numbers->assign($receipt);
                    }
                    $item = $receipt->items()->create([
                        'product_id' => $product->getKey(), 'quantity_received' => (string) $quantity,
                        'unit_cost' => $product->purchase_price, 'unit' => $product->unit,
                    ]);
                    $generated = $product->track_serial && $serials === [];
                    if ($product->track_serial) {
                        $units = $quantity->toInt();
                        for ($index = 0; $index < $units; $index++) {
                            $number = $generated ? 'SN-'.$receipt->receipt_number.'-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT) : $serials[$index];
                            while ($generated && ProductSerial::query()->where('serial_number', $number)->exists()) {
                                $number = 'SN-'.$receipt->receipt_number.'-'.($index + 1).'-'.Str::random(12);
                            }
                            $serial = ProductSerial::query()->create([
                                'product_id' => $product->getKey(), 'warehouse_id' => $data['warehouse_id'],
                                'serial_number' => $number, 'status' => ProductSerialStatus::InStock,
                            ]);
                            $this->stock->record($actor, $product->getKey(), $data['warehouse_id'], '1.000', StockMovementType::GoodsIn, $receipt->receipt_number, $item->getKey(), serialId: $serial->getKey());
                        }
                    } else {
                        $this->stock->record($actor, $product->getKey(), $data['warehouse_id'], (string) $quantity, StockMovementType::GoodsIn, $receipt->receipt_number, $item->getKey());
                    }
                    $receipt->update(['status' => GoodsReceiptStatus::Posted, 'posted_at' => now()]);
                    $this->stock->history($actor, 'GOODS_RECEIPTS', $receipt->getKey(), GoodsReceiptStatus::Draft->value, GoodsReceiptStatus::Posted->value, $requestId);
                    $this->audit->record($actor, 'GOODS_IN', 'GOODS_RECEIPTS', $receipt->getKey(), 'Penerimaan barang '.$receipt->receipt_number.' diposting.',
                        ['stock' => $before],
                        ['receipt_number' => $receipt->receipt_number, 'item' => $item->toArray(), 'serial_numbers' => $serials, 'serials_generated' => $generated, 'stock' => (string) BigDecimal::of($before)->plus($quantity), 'supplier_id' => null, 'warehouse_id' => $data['warehouse_id']]);

                    return $receipt;
                }, 3);
            } catch (UniqueConstraintViolationException) {
                if ($attempt === 2) {
                    throw ValidationException::withMessages(['reference' => 'Nomor dokumen atau nomor serial sudah digunakan.']);
                }
            }
        }

        throw new \LogicException('Receipt posting attempts exhausted.');
    }
}
