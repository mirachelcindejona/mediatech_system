<?php

namespace Tests\Feature\Inventory;

use App\Enums\StockMovementType;
use App\Models\Inventory\Product;
use App\Models\Inventory\ProductSerial;
use App\Models\Inventory\Warehouse;
use App\Models\Sales\SalesOrder;
use App\Models\Sales\SalesOrderItem;
use Database\Factories\Inventory\StockMovementFactory;
use Illuminate\Support\Facades\DB;

class StockMovementTest extends InventoryTestCase
{
    public function test_grouped_numeric_serials_are_not_deduplicated_by_numeric_value(): void
    {
        $product = Product::factory()->create(['track_serial' => true]);
        $warehouse = Warehouse::factory()->create();
        $this->actingAs($this->userWithPermissions());
        $serials = ['0', '00', '01', '1'];
        $receipt = $this->postJson(self::URL.'/goods-receipts', $this->receiptData($product, $warehouse, '4', $serials))->assertCreated();
        $this->getJson(self::URL.'/movements?search='.$receipt->json('number'))->assertOk()
            ->assertJsonPath('total', 1)->assertJsonPath('data.0.quantity', '4.000')->assertJsonPath('data.0.serial_numbers', $serials);
    }

    public function test_all_filtered_movement_exports_retain_serials_across_bounded_lookup_batches(): void
    {
        $product = Product::factory()->create(['track_serial' => true]);
        $warehouse = Warehouse::factory()->create();
        $this->actingAs($actor = $this->userWithPermissions());
        $numbers = [];
        foreach (range(1, 201) as $number) {
            $numbers[] = $value = 'BATCH-SERIAL-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT);
            $serial = ProductSerial::query()->create(['product_id' => $product->getKey(), 'warehouse_id' => $warehouse->getKey(), 'serial_number' => $value, 'status' => 'In Stock']);
            StockMovementFactory::new()->create(['product_id' => $product->getKey(), 'warehouse_id' => $warehouse->getKey(), 'recorded_by' => $actor->getKey(), 'serial_id' => $serial->getKey(), 'quantity' => '1.000']);
        }
        $export = $this->getJson(self::URL.'/export/movements?search=BATCH-SERIAL&per_page=5&page=2')->assertOk()->assertJsonCount(201);
        $this->assertSame($numbers, collect($export->json())->flatMap(fn ($row) => $row['serial_numbers'])->sort()->values()->all());
    }

    public function test_serialized_receipt_history_groups_before_search_pagination_and_export_without_changing_the_ledger(): void
    {
        $product = Product::factory()->create(['track_serial' => true]);
        $warehouse = Warehouse::factory()->create();
        $this->actingAs($this->userWithPermissions());
        $serials = array_map(fn ($n) => 'SERIAL-'.str_pad((string) $n, 3, '0', STR_PAD_LEFT).'-'.str_repeat('X', 105), range(1, 18));
        $receipt = $this->postJson(self::URL.'/goods-receipts', [...$this->receiptData($product, $warehouse, '18', $serials), 'reference' => 'BULK-RECEIPT'])->assertCreated();
        $this->postJson(self::URL.'/goods-receipts', [...$this->receiptData($product, $warehouse, '2'), 'reference' => 'OTHER-RECEIPT'])->assertCreated();
        DB::table('stock_movements')->where('movement_id', DB::table('stock_movements')->min('movement_id'))->update(['reference_number' => 'LEGACY-ONE-UNIT']);
        $before = DB::table('stock_movements')->orderBy('movement_id')->get()->toJson();
        foreach (['BULK-RECEIPT', 'LEGACY-ONE-UNIT', $serials[0], $serials[17]] as $search) {
            $this->getJson(self::URL.'/movements?'.http_build_query(['search' => $search, 'per_page' => 5]))->assertOk()
                ->assertJsonPath('total', 1)->assertJsonCount(1, 'data')->assertJsonPath('data.0.quantity', '18.000')
                ->assertJsonPath('data.0.reference', $receipt->json('number'))->assertJsonPath('data.0.serial_numbers', $serials);
            $this->getJson(self::URL.'/export/movements?'.http_build_query(['search' => $search]))->assertOk()
                ->assertJsonCount(1)->assertJsonPath('0.quantity', '18.000')->assertJsonPath('0.serial_numbers', $serials);
        }
        $this->getJson(self::URL.'/movements?search=RECEIPT&per_page=5')->assertOk()->assertJsonPath('total', 2)->assertJsonCount(2, 'data');
        $this->assertSame($before, DB::table('stock_movements')->orderBy('movement_id')->get()->toJson());
        $this->assertDatabaseCount('stock_movements', 20);
        $this->assertDatabaseCount('product_serials', 20);
        $this->assertDatabaseCount('goods_receipts', 2);
        $this->assertDatabaseCount('audit_logs', 2);
    }

    public function test_serialized_issues_keep_separate_dispatches_so_items_and_warehouses_and_do_not_merge_adjustments(): void
    {
        $s = $this->scenario('1', '10');
        $s['product']->update(['track_serial' => true]);
        $this->actingAs($this->userWithPermissions());
        $this->postJson(self::URL.'/goods-receipts', $this->receiptData($s['product'], $s['warehouse'], '6'))->assertCreated();
        $serials = DB::table('product_serials')->orderBy('serial_id')->pluck('serial_id')->all();
        $first = $this->postJson(self::URL.'/goods-issues', $this->issueData($s, '2', array_slice($serials, 0, 2)))->assertCreated();
        $second = $this->postJson(self::URL.'/goods-issues', $this->issueData($s, '2', array_slice($serials, 2, 2)))->assertCreated();
        foreach ([$first, $second] as $issue) {
            $this->getJson(self::URL.'/movements?search='.$issue->json('number'))->assertOk()
                ->assertJsonPath('total', 1)->assertJsonPath('data.0.quantity', '2.000')->assertJsonCount(2, 'data.0.serial_numbers');
        }
        $otherItem = SalesOrderItem::factory()->create(['sales_order_id' => $s['order']->getKey(), 'product_id' => $s['product']->getKey(), 'quantity_ordered' => '1']);
        $data = $this->issueData($s, '1', [$serials[4]]);
        $data['items'][] = [...$data['items'][0], 'so_item_id' => $otherItem->getKey(), 'serial_ids' => [$serials[5]]];
        $third = $this->postJson(self::URL.'/goods-issues', $data)->assertCreated();
        $this->getJson(self::URL.'/movements?search='.$third->json('number'))->assertOk()->assertJsonPath('total', 2)->assertJsonCount(2, 'data');
        $otherWarehouse = Warehouse::factory()->create();
        $receiptItem = DB::table('goods_receipt_items')->value('receipt_item_id');
        StockMovementFactory::new()->create(['product_id' => $s['product']->getKey(), 'warehouse_id' => $otherWarehouse->getKey(),
            'receipt_item_id' => $receiptItem, 'movement_type' => 'GOODS_IN', 'quantity' => '3', 'reference_number' => 'SPLIT-WAREHOUSE']);
        $this->getJson(self::URL.'/movements?warehouse_id='.$otherWarehouse->getKey())->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.quantity', '3.000')->assertJsonCount(0, 'data.0.serial_numbers');
        foreach (range(1, 2) as $unused) {
            StockMovementFactory::new()->create(['product_id' => $s['product']->getKey(), 'warehouse_id' => $s['warehouse']->getKey(),
                'movement_type' => 'ADJUSTMENT_IN', 'quantity' => '0.125', 'reference_number' => 'SAME-REFERENCE', 'adjustment_reason' => 'Separate corrections']);
        }
        $this->getJson(self::URL.'/movements?search=SAME-REFERENCE')->assertOk()->assertJsonPath('total', 2)->assertJsonPath('data.0.quantity', '0.125');
    }

    public function test_grouped_movement_pagination_counts_documents_not_serials_and_export_includes_off_page_groups(): void
    {
        $product = Product::factory()->create(['track_serial' => true]);
        $warehouse = Warehouse::factory()->create();
        $this->actingAs($this->userWithPermissions());
        foreach (range(1, 6) as $number) {
            $this->postJson(self::URL.'/goods-receipts', [...$this->receiptData($product, $warehouse, '2'), 'reference' => 'PAGE-GROUP-'.$number])->assertCreated();
        }
        $this->getJson(self::URL.'/movements?search=PAGE-GROUP&per_page=5')->assertOk()->assertJsonPath('total', 6)
            ->assertJsonCount(5, 'data')->assertJsonPath('last_page', 2)->assertJsonPath('data.0.quantity', '2.000');
        $this->getJson(self::URL.'/movements?search=PAGE-GROUP&per_page=5&page=2')->assertOk()->assertJsonCount(1, 'data')->assertJsonCount(2, 'data.0.serial_numbers');
        $this->getJson(self::URL.'/export/movements?search=PAGE-GROUP&per_page=5&page=2')->assertOk()->assertJsonCount(6)->assertJsonCount(2, '5.serial_numbers');
    }

    public function test_linked_document_number_is_the_canonical_reference_even_for_legacy_movement_identifiers(): void
    {
        $s = $this->scenario();
        $this->actingAs($this->userWithPermissions());
        $receipt = $this->postJson(self::URL.'/goods-receipts', [...$this->receiptData($s['product'], $s['warehouse']), 'reference' => 'MY-RECEIPT'])->assertCreated();
        DB::table('stock_movements')->where('movement_type', 'GOODS_IN')->update(['reference_number' => 'MOV-LEGACY-IN']);
        $issue = $this->postJson(self::URL.'/goods-issues', $this->issueData($s))->assertCreated();
        DB::table('stock_movements')->where('movement_type', 'GOODS_OUT')->update(['reference_number' => 'MOV-LEGACY-OUT']);
        foreach ([['MY-RECEIPT', 'MOV-LEGACY-IN'], [$issue->json('number'), 'MOV-LEGACY-OUT']] as [$canonical, $legacy]) {
            $this->getJson(self::URL.'/movements?search='.$canonical)->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.reference', $canonical);
            $this->getJson(self::URL.'/movements?search='.$legacy)->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.reference', $canonical);
            $this->getJson(self::URL.'/export/movements?search='.$canonical)->assertOk()->assertJsonPath('0.reference', $canonical);
        }
        $this->assertDatabaseHas('stock_movements', ['reference_number' => 'MOV-LEGACY-IN']);
        $this->assertDatabaseHas('stock_movements', ['reference_number' => 'MOV-LEGACY-OUT']);
    }

    public function test_stock_and_movement_queries_filter_paginate_and_export_all_filtered_rows_without_prices(): void
    {
        $warehouse = Warehouse::factory()->create();
        foreach (range(1, 6) as $number) {
            $product = Product::factory()->create(['sku' => "MATCH-$number", 'product_name' => 'Find this laptop']);
            StockMovementFactory::new()->create(['product_id' => $product->getKey(), 'warehouse_id' => $warehouse->getKey()]);
        }
        $this->actingAs($this->userWithPermissions());
        $this->getJson(self::URL.'/stocks?search=laptop&per_page=5&page=1')->assertOk()->assertJsonPath('stocks.total', 6)
            ->assertJsonCount(5, 'stocks.data')->assertJsonPath('summary.units', '60.000')->assertDontSee('purchase_price')->assertDontSee('unit_cost');
        $this->getJson(self::URL.'/stocks?search=laptop&per_page=5&page=2')->assertOk()->assertJsonCount(1, 'stocks.data');
        $this->getJson(self::URL.'/stocks?search=laptop&warehouse_id=999999')->assertOk()->assertJsonPath('stocks.total', 0);
        $this->getJson(self::URL.'/stocks?search=%25')->assertOk()->assertJsonPath('stocks.total', 0);
        $this->getJson(self::URL.'/stocks?per_page=999')->assertUnprocessable();
        $this->getJson(self::URL.'/export/stocks?search=laptop&per_page=5&page=2')->assertOk()->assertJsonCount(6);
        $this->getJson(self::URL.'/movements?search=MATCH&per_page=5')->assertOk()->assertJsonPath('total', 6)->assertJsonCount(5, 'data');
        $this->getJson(self::URL.'/export/movements?search=MATCH&per_page=5')->assertOk()->assertJsonCount(6);
    }

    public function test_signed_ledger_includes_all_existing_movement_types(): void
    {
        $s = $this->scenario();
        $this->actingAs($actor = $this->userWithPermissions());
        $this->postJson(self::URL.'/goods-receipts', $this->receiptData($s['product'], $s['warehouse'], '1'))->assertCreated();
        $this->postJson(self::URL.'/goods-issues', $this->issueData($s, '2.500'))->assertCreated();
        $sourceIn = DB::table('stock_movements')->where('movement_type', 'GOODS_IN')->value('movement_id');
        $sourceOut = DB::table('stock_movements')->where('movement_type', 'GOODS_OUT')->value('movement_id');
        foreach ([[StockMovementType::AdjustmentIn, '2.000', null], [StockMovementType::AdjustmentOut, '1.000', null],
            [StockMovementType::ReversalIn, '0.500', $sourceOut], [StockMovementType::ReversalOut, '0.250', $sourceIn]] as [$type, $qty, $source]) {
            StockMovementFactory::new()->create(['product_id' => $s['product']->getKey(), 'warehouse_id' => $s['warehouse']->getKey(),
                'recorded_by' => $actor->getKey(), 'movement_type' => $type, 'quantity' => $qty,
                'reversal_of_movement_id' => $source, 'adjustment_reason' => $source ? null : 'Test adjustment']);
        }
        $this->getJson(self::URL.'/stocks')->assertOk()->assertJsonPath('stocks.data.0.qty', '9.750')->assertJsonPath('summary.units', '9.750');
        $this->getJson(self::URL.'/movements')->assertOk()->assertJsonPath('total', 7);
    }

    public function test_order_lookup_returns_only_eligible_outstanding_stock_items_and_accepts_so_deep_link(): void
    {
        $s = $this->scenario();
        SalesOrder::factory()->create(['status' => 'Cancelled']);
        SalesOrder::factory()->create(['status' => 'Completed']);
        $this->actingAs($this->userWithPermissions());
        $this->getJson(self::URL.'/orders')->assertOk()->assertJsonCount(1);
        $this->getJson(self::URL.'/orders/'.$s['order']->getKey())->assertOk()->assertJsonPath('items.0.id', $s['item']->getKey())
            ->assertJsonPath('items.0.name', 'Original product snapshot')->assertDontSee('unit_cost_snapshot')->assertDontSee('unit_price');
        $this->get(self::URL.'?sales_order_id='.$s['order']->getKey())->assertOk()->assertSee('inventory-bootstrap', false);
        $this->postJson(self::URL.'/goods-issues', $this->issueData($s, '10'))->assertCreated();
        $this->getJson(self::URL.'/orders')->assertOk()->assertJsonCount(0);
        $this->getJson(self::URL.'/orders/'.$s['order']->getKey())->assertNotFound();
        $this->getJson(self::URL.'/orders/999999999999999999999999999999')->assertNotFound();
    }
}
