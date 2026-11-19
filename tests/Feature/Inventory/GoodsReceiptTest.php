<?php

namespace Tests\Feature\Inventory;

use App\Models\Inventory\GoodsReceipt;
use App\Models\Inventory\Product;
use App\Models\Inventory\Warehouse;
use App\Services\Audit\AuditLogService;
use App\Services\Inventory\UpdateStockService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mockery\MockInterface;

class GoodsReceiptTest extends InventoryTestCase
{
    public function test_blank_receipt_fields_generate_one_document_and_serials_with_identical_movement_references(): void
    {
        $product = Product::factory()->create(['track_serial' => true]);
        $warehouse = Warehouse::factory()->create();
        $this->actingAs($this->userWithPermissions());
        $data = [...$this->receiptData($product, $warehouse, '2'), 'reference' => '   ', 'request_key' => (string) Str::uuid()];
        $first = $this->postJson(self::URL.'/goods-receipts', $data)->assertCreated();
        $number = $first->json('number');
        $this->assertMatchesRegularExpression('/^GR-'.now('Asia/Jakarta')->year.'-\d{3,}$/', $number);
        $this->postJson(self::URL.'/goods-receipts', $data)->assertOk()->assertJsonPath('id', $first->json('id'))->assertJsonPath('number', $number);
        $this->assertDatabaseCount('goods_receipts', 1);
        $this->assertDatabaseCount('product_serials', 2);
        $this->assertSame(2, DB::table('product_serials')->distinct()->count('serial_number'));
        $this->assertSame(2, DB::table('stock_movements')->where('reference_number', $number)->whereNotNull('serial_id')->count());
        $this->assertDatabaseCount('audit_logs', 1);
        $this->getJson(self::URL.'/movements')->assertOk()->assertJsonPath('data.0.reference', $number)->assertJsonPath('data.0.quantity', '2.000')
            ->assertJsonCount(1, 'data')->assertJsonCount(2, 'data.0.serial_numbers');
        $this->assertSame($number, json_decode(DB::table('audit_logs')->value('new_values'), true)['receipt_number']);
        $this->postJson(self::URL.'/goods-receipts', [...$data, 'quantity' => '3'])->assertUnprocessable()->assertJsonValidationErrors('request_key');
    }

    public function test_omitted_optional_receipt_fields_generate_defaults_and_manual_references_are_preserved(): void
    {
        $product = Product::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $this->actingAs($this->userWithPermissions());
        $data = ['product_id' => $product->getKey(), 'warehouse_id' => $warehouse->getKey(), 'quantity' => '0.125'];
        $first = $this->postJson(self::URL.'/goods-receipts', $data)->assertCreated();
        $this->assertDatabaseHas('stock_movements', ['reference_number' => $first->json('number'), 'quantity' => '0.125']);
        $manual = $this->postJson(self::URL.'/goods-receipts', [...$data, 'reference' => '  MY-DELIVERY-REF  '])->assertCreated()->assertJsonPath('number', 'MY-DELIVERY-REF');
        $this->assertDatabaseHas('stock_movements', ['reference_number' => $manual->json('number')]);
        $this->assertDatabaseCount('product_serials', 0);
    }

    public function test_zero_reference_is_preserved_and_retried_without_a_separate_request_key(): void
    {
        $product = Product::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $this->actingAs($this->userWithPermissions());
        $data = [...$this->receiptData($product, $warehouse, '1'), 'reference' => '0'];
        $first = $this->postJson(self::URL.'/goods-receipts', $data)->assertCreated()->assertJsonPath('number', '0');
        $this->postJson(self::URL.'/goods-receipts', $data)->assertOk()->assertJsonPath('id', $first->json('id'))->assertJsonPath('number', '0');
        $this->assertDatabaseHas('stock_movements', ['reference_number' => '0', 'quantity' => '1.000']);
        foreach (['goods_receipts', 'goods_receipt_items', 'stock_movements', 'audit_logs', 'transaction_status_history'] as $table) {
            $this->assertDatabaseCount($table, 1);
        }
    }

    public function test_receipt_posts_without_supplier_and_preserves_cost_and_unit_snapshots(): void
    {
        $product = Product::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $this->actingAs($this->userWithPermissions());
        $this->travelTo(now()->setTime(18, 0));
        $data = $this->receiptData($product, $warehouse, '0.001');
        $this->postJson(self::URL.'/goods-receipts', $data)->assertCreated();
        $receipt = GoodsReceipt::query()->with('items.stockMovements')->firstOrFail();
        $this->assertNull($receipt->supplier_id);
        $this->assertSame(now('Asia/Jakarta')->toDateString(), $receipt->receipt_date->toDateString());
        $this->assertSame('75.25', $receipt->items[0]->unit_cost);
        $this->assertSame('0.001', $receipt->items[0]->quantity_received);
        $this->assertSame('pcs', $receipt->items[0]->unit);
        $this->assertSame($receipt->items[0]->getKey(), $receipt->items[0]->stockMovements[0]->receipt_item_id);
        $product->update(['purchase_price' => '999.00', 'unit' => 'box']);
        $this->assertSame('75.25', $receipt->items[0]->fresh()->unit_cost);
        $this->assertSame('pcs', $receipt->items[0]->fresh()->unit);
        $this->assertSame('0.001', (string) app(UpdateStockService::class)->quantity($product->getKey(), $warehouse->getKey()));
        $this->assertDatabaseHas('transaction_status_history', ['entity_name' => 'GOODS_RECEIPTS', 'old_status' => 'Draft', 'new_status' => 'Posted']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'GOODS_IN']);
        $this->assertSame(DB::table('audit_logs')->value('request_id'), DB::table('transaction_status_history')->value('request_id'));
    }

    public function test_generated_numbers_skip_manual_reservations_and_retries_survive_the_year_boundary(): void
    {
        $product = Product::factory()->create(['track_serial' => true]);
        $warehouse = Warehouse::factory()->create();
        $this->actingAs($this->userWithPermissions());
        $this->travelTo(now()->setDate(2026, 12, 31)->setTime(16, 59, 59));
        $manual = $this->receiptData($product, $warehouse, '1', ['MANUAL-UNCHANGED']);
        $this->postJson(self::URL.'/goods-receipts', [...$manual, 'reference' => 'GR-2026-002'])->assertCreated();
        $data = [...$this->receiptData($product, $warehouse, '2'), 'reference' => null, 'request_key' => (string) Str::uuid()];
        $first = $this->postJson(self::URL.'/goods-receipts', $data)->assertCreated()->assertJsonPath('number', 'GR-2026-003');
        $this->travel(2)->seconds();
        $this->postJson(self::URL.'/goods-receipts', $data)->assertOk()->assertJsonPath('id', $first->json('id'))->assertJsonPath('number', 'GR-2026-003');
        $this->assertDatabaseHas('product_serials', ['serial_number' => 'MANUAL-UNCHANGED']);
        $this->assertDatabaseCount('goods_receipts', 2);
        $this->assertDatabaseCount('product_serials', 3);
        $this->postJson(self::URL.'/goods-receipts', [...$data, 'serial_numbers' => ['CHANGED-A', 'CHANGED-B']])->assertUnprocessable()->assertJsonValidationErrors('request_key');
    }

    public function test_receipt_retries_do_not_add_stock_or_audit_twice(): void
    {
        $product = Product::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $data = $this->receiptData($product, $warehouse);
        $this->actingAs($this->userWithPermissions());
        $first = $this->postJson(self::URL.'/goods-receipts', $data)->assertCreated();
        $this->postJson(self::URL.'/goods-receipts', $data)->assertOk()->assertJsonPath('id', $first->json('id'));
        $data['quantity'] = '3.000';
        $this->postJson(self::URL.'/goods-receipts', $data)->assertUnprocessable()->assertJsonValidationErrors('reference');
        foreach (['goods_receipts', 'goods_receipt_items', 'stock_movements', 'audit_logs', 'transaction_status_history'] as $table) {
            $this->assertDatabaseCount($table, 1);
        }
    }

    public function test_legacy_receipt_retry_preserves_a_valid_zero_serial_number(): void
    {
        $product = Product::factory()->create(['track_serial' => true]);
        $warehouse = Warehouse::factory()->create();
        $this->actingAs($this->userWithPermissions());
        $data = $this->receiptData($product, $warehouse, '1', ['0']);
        $first = $this->postJson(self::URL.'/goods-receipts', $data)->assertCreated();
        DB::table('goods_receipts')->where('receipt_id', $first->json('id'))->update(['posting_key' => null, 'posting_fingerprint' => null]);
        $this->postJson(self::URL.'/goods-receipts', $data)->assertOk()->assertJsonPath('id', $first->json('id'));
        $this->assertDatabaseCount('goods_receipts', 1);
        $this->assertDatabaseCount('product_serials', 1);
    }

    public function test_manual_serial_receipt_links_each_unit_and_rejects_incomplete_duplicate_or_reused_serials(): void
    {
        $product = Product::factory()->create(['track_serial' => true]);
        $warehouse = Warehouse::factory()->create();
        $this->actingAs($this->userWithPermissions());
        foreach ([['2', ['ONE']], ['1.5', ['ONE']], ['1.5', []], ['2', ['ONE', 'one']]] as [$qty, $serials]) {
            $this->postJson(self::URL.'/goods-receipts', $this->receiptData($product, $warehouse, $qty, $serials))->assertUnprocessable();
        }
        $data = $this->receiptData($product, $warehouse, '2', ['ONE', 'TWO']);
        $this->postJson(self::URL.'/goods-receipts', $data)->assertCreated();
        $this->postJson(self::URL.'/goods-receipts', $data)->assertOk();
        $this->postJson(self::URL.'/goods-receipts', $this->receiptData($product, $warehouse, '1', ['ONE']))->assertUnprocessable();
        $this->assertDatabaseCount('product_serials', 2);
        $this->assertDatabaseCount('stock_movements', 2);
        $this->assertSame(2, DB::table('stock_movements')->whereNotNull('serial_id')->where('quantity', '1.000')->count());
        $this->assertDatabaseHas('product_serials', ['serial_number' => 'ONE', 'status' => 'In Stock', 'warehouse_id' => $warehouse->getKey()]);
    }

    public function test_bad_inputs_inactive_masters_and_service_products_cannot_change_stock(): void
    {
        $product = Product::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $data = $this->receiptData($product, $warehouse);
        $this->actingAs($this->userWithPermissions());
        foreach (['0', '-1', '0.0001', '1000000000000000', '1e2'] as $quantity) {
            $this->postJson(self::URL.'/goods-receipts', [...$data, 'quantity' => $quantity])->assertUnprocessable();
        }
        foreach (['reference' => str_repeat('A', 51), 'product_id' => 99999, 'warehouse_id' => 99999, 'serial_numbers' => ['NOT-TRACKED']] as $field => $value) {
            $this->postJson(self::URL.'/goods-receipts', [...$data, $field => $value])->assertUnprocessable();
        }
        $product->update(['product_type' => 'Jasa']);
        $this->postJson(self::URL.'/goods-receipts', $data)->assertUnprocessable();
        $product->update(['product_type' => 'Barang', 'is_active' => false]);
        $this->postJson(self::URL.'/goods-receipts', $data)->assertUnprocessable();
        $product->update(['is_active' => true]);
        $warehouse->update(['deleted_at' => now()]);
        $this->postJson(self::URL.'/goods-receipts', $data)->assertUnprocessable();
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertDatabaseCount('goods_receipts', 0);
    }

    public function test_audit_failure_rolls_back_receipt_items_serials_movements_and_history(): void
    {
        $product = Product::factory()->create(['track_serial' => true]);
        $warehouse = Warehouse::factory()->create();
        $this->actingAs($this->userWithPermissions());
        $this->mock(AuditLogService::class, fn (MockInterface $mock) => $mock->shouldReceive('record')->once()->andThrow(new \RuntimeException('Test audit failure')));
        $this->postJson(self::URL.'/goods-receipts', $this->receiptData($product, $warehouse, '1', ['ROLLBACK']))->assertServerError();
        foreach (['goods_receipts', 'goods_receipt_items', 'product_serials', 'stock_movements', 'transaction_status_history'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    public function test_supplier_migration_rollback_refuses_to_erase_unknown_suppliers(): void
    {
        $product = Product::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $this->actingAs($this->userWithPermissions())->postJson(self::URL.'/goods-receipts', $this->receiptData($product, $warehouse))->assertCreated();
        $migration = require database_path('migrations/2026_11_18_144440_make_goods_receipt_supplier_optional.php');
        try {
            $migration->down();
            $this->fail('Rollback must not silently assign or discard supplier-less receipts.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('actual suppliers', $e->getMessage());
        }
        $this->assertDatabaseCount('goods_receipts', 1);
    }

    public function test_retry_metadata_migrations_preserve_used_posting_identities_and_can_roll_back_when_empty(): void
    {
        $files = ['2026_11_18_161640_add_posting_key_to_goods_receipts_table.php', '2026_11_18_161642_add_posting_key_to_goods_issues_table.php'];
        foreach ($files as $file) {
            $migration = require database_path('migrations/'.$file);
            $migration->down();
            $migration->up();
        }
        $s = $this->scenario();
        $this->actingAs($this->userWithPermissions());
        $this->postJson(self::URL.'/goods-receipts', [...$this->receiptData($s['product'], $s['warehouse']), 'request_key' => (string) Str::uuid()])->assertCreated();
        $this->postJson(self::URL.'/goods-issues', $this->issueData($s))->assertCreated();
        foreach ($files as $file) {
            $migration = require database_path('migrations/'.$file);
            try {
                $migration->down();
                $this->fail('Rollback must preserve used posting identities.');
            } catch (\RuntimeException $error) {
                $this->assertStringContainsString('posting identities', $error->getMessage());
            }
        }
        $this->assertDatabaseCount('goods_receipts', 1);
        $this->assertDatabaseCount('goods_issues', 1);
    }

    public function test_supplier_nullability_migration_preserves_the_foreign_key_and_can_roll_back_when_safe(): void
    {
        $migration = require database_path('migrations/2026_11_18_144440_make_goods_receipt_supplier_optional.php');
        $supplierColumn = fn () => collect(Schema::getColumns('goods_receipts'))->firstWhere('name', 'supplier_id');
        $supplierKey = fn () => collect(Schema::getForeignKeys('goods_receipts'))->first(fn ($fk) => $fk['columns'] === ['supplier_id']);
        $this->assertTrue($supplierColumn()['nullable']);
        $this->assertSame('suppliers', $supplierKey()['foreign_table']);
        $this->assertSame('restrict', strtolower($supplierKey()['on_delete']));
        $migration->down();
        $this->assertFalse($supplierColumn()['nullable']);
        $this->assertSame('suppliers', $supplierKey()['foreign_table']);
        $migration->up();
        $this->assertTrue($supplierColumn()['nullable']);
        $this->assertSame('suppliers', $supplierKey()['foreign_table']);
    }
}
