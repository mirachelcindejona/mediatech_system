<?php

namespace Tests\Feature\Inventory;

use App\Models\Inventory\GoodsIssue;
use App\Models\Inventory\Product;
use App\Models\Inventory\ProductSerial;
use App\Models\Inventory\Warehouse;
use App\Models\Sales\SalesOrderItem;
use App\Services\Audit\AuditLogService;
use App\Services\Inventory\UpdateStockService;
use Database\Factories\Inventory\StockMovementFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mockery\MockInterface;

class GoodsIssueTest extends InventoryTestCase
{
    public function test_confirmation_retries_survive_a_year_change_and_legacy_hash_numbering(): void
    {
        $s = $this->scenario();
        $actor = $this->userWithPermissions();
        $this->actingAs($actor);
        $this->travelTo(now()->setDate(2026, 12, 31)->setTime(16, 59, 59));
        $data = $this->issueData($s);
        $first = $this->postJson(self::URL.'/goods-issues', $data)->assertCreated();
        $this->travel(2)->seconds();
        $this->postJson(self::URL.'/goods-issues', $data)->assertOk()->assertJsonPath('number', $first->json('number'));
        $legacy = 'GI-'.strtoupper(substr(hash('sha256', $actor->getKey().':'.$data['request_key']), 0, 32));
        DB::table('goods_issues')->where('goods_issue_id', $first->json('id'))->update(['posting_key' => null, 'goods_issue_number' => $legacy]);
        $this->postJson(self::URL.'/goods-issues', $data)->assertOk()->assertJsonPath('id', $first->json('id'))->assertJsonPath('number', $legacy);
        $this->assertDatabaseCount('goods_issues', 1);
        $this->assertDatabaseCount('audit_logs', 1);
        $this->assertSame('2.500', $s['item']->fresh()->quantity_delivered);
    }

    public function test_partial_and_full_dispatch_update_linked_deliveries_but_not_financial_snapshots_or_services(): void
    {
        $s = $this->scenario();
        $service = Product::factory()->create(['product_type' => 'Jasa']);
        $serviceItem = SalesOrderItem::factory()->create(['sales_order_id' => $s['order']->getKey(), 'product_id' => $service->getKey()]);
        $this->actingAs($this->userWithPermissions());
        $snapshots = $s['item']->only(['unit_cost_snapshot', 'unit_price', 'subtotal', 'product_name_snapshot']);
        $orderFinancials = $s['order']->only(['grand_total', 'subtotal', 'tax_amount']);
        $data = $this->issueData($s);
        $first = $this->postJson(self::URL.'/goods-issues', $data)->assertCreated();
        $this->assertMatchesRegularExpression('/^GO-'.now('Asia/Jakarta')->year.'-\d{4,}$/', $first->json('number'));
        $this->assertDatabaseHas('stock_movements', ['reference_number' => $first->json('number'), 'movement_type' => 'GOODS_OUT']);
        $this->postJson(self::URL.'/goods-issues', $data)->assertOk()->assertJsonPath('id', $first->json('id'));
        $this->assertSame('2.500', $s['item']->fresh()->quantity_delivered);
        $this->assertSame('Processing', $s['order']->fresh()->status);
        $this->assertSame('7.500', (string) app(UpdateStockService::class)->quantity($s['product']->getKey(), $s['warehouse']->getKey()));
        $issue = GoodsIssue::query()->with('items.salesOrderItem', 'items.stockMovements', 'salesOrder')->firstOrFail();
        $this->assertSame($s['order']->getKey(), $issue->salesOrder->getKey());
        $this->assertSame($s['item']->getKey(), $issue->items[0]->salesOrderItem->getKey());
        $this->assertSame($issue->items[0]->getKey(), $issue->items[0]->stockMovements[0]->goods_issue_item_id);
        $this->postJson(self::URL.'/goods-issues', $this->issueData($s, '7.500'))->assertCreated();
        $this->assertSame('Completed', $s['order']->fresh()->status);
        $this->assertSame('10.000', $s['item']->fresh()->quantity_delivered);
        $this->assertSame('0.000', $serviceItem->fresh()->quantity_delivered);
        $this->assertSame($snapshots, $s['item']->fresh()->only(array_keys($snapshots)));
        $this->assertSame($orderFinancials, $s['order']->fresh()->only(array_keys($orderFinancials)));
        $this->assertSame('0.000', (string) app(UpdateStockService::class)->quantity($s['product']->getKey(), $s['warehouse']->getKey()));
        $this->assertDatabaseCount('goods_issues', 2);
        $this->assertDatabaseCount('audit_logs', 2);
        $this->assertDatabaseHas('transaction_status_history', ['entity_name' => 'SALES_ORDERS', 'old_status' => 'Processing', 'new_status' => 'Completed']);
        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_reused_confirmation_key_with_different_payload_is_rejected(): void
    {
        $s = $this->scenario();
        $this->actingAs($this->userWithPermissions());
        $data = $this->issueData($s);
        $this->postJson(self::URL.'/goods-issues', $data)->assertCreated();
        $data['items'][0]['quantity'] = '1.000';
        $this->postJson(self::URL.'/goods-issues', $data)->assertUnprocessable()->assertJsonValidationErrors('request_key');
        $this->assertDatabaseCount('goods_issues', 1);
        $this->assertSame('2.500', $s['item']->fresh()->quantity_delivered);
    }

    public function test_stale_stock_stale_so_quantities_and_closed_orders_cannot_be_posted(): void
    {
        $s = $this->scenario('3.000');
        $this->actingAs($this->userWithPermissions());
        $this->getJson(self::URL.'/orders/'.$s['order']->getKey())->assertOk()->assertJsonPath('items.0.stocks.0.qty', '3.000');
        $stale = $this->issueData($s, '2.000');
        $this->postJson(self::URL.'/goods-issues', $this->issueData($s, '2.000'))->assertCreated();
        $this->postJson(self::URL.'/goods-issues', $stale)->assertUnprocessable();
        $this->assertDatabaseCount('goods_issues', 1);
        $s['item']->update(['quantity_delivered' => '9.500']);
        $this->postJson(self::URL.'/goods-issues', $this->issueData($s, '1.000'))->assertUnprocessable();
        foreach (['Completed', 'Cancelled'] as $status) {
            $s['order']->update(['status' => $status]);
            $this->postJson(self::URL.'/goods-issues', $this->issueData($s, '0.100'))->assertUnprocessable();
        }
        $this->assertDatabaseCount('goods_issues', 1);
    }

    public function test_duplicate_product_lines_share_warehouse_stock_and_multiple_warehouses_work(): void
    {
        $s = $this->scenario('5.000');
        $second = SalesOrderItem::factory()->create(['sales_order_id' => $s['order']->getKey(), 'product_id' => $s['product']->getKey()]);
        $this->actingAs($this->userWithPermissions());
        $data = $this->issueData($s, '3.000');
        $data['items'][] = [...$data['items'][0], 'so_item_id' => $second->getKey()];
        $this->postJson(self::URL.'/goods-issues', $data)->assertUnprocessable();
        $this->assertDatabaseCount('goods_issues', 0);
        $warehouse = Warehouse::factory()->create(['warehouse_name' => 'Another test warehouse']);
        StockMovementFactory::new()->create(['product_id' => $s['product']->getKey(), 'warehouse_id' => $warehouse->getKey(), 'quantity' => '4.000']);
        $data['items'][1]['warehouse_id'] = $warehouse->getKey();
        $this->postJson(self::URL.'/goods-issues', $data)->assertCreated();
        $this->assertSame('3.000', $second->fresh()->quantity_delivered);
        $this->assertSame('3.000', $s['item']->fresh()->quantity_delivered);
        $this->assertDatabaseCount('goods_issue_items', 2);
    }

    public function test_foreign_so_lines_services_and_forged_duplicate_or_invalid_quantities_are_rejected(): void
    {
        $s = $this->scenario();
        $foreign = SalesOrderItem::factory()->create();
        $this->actingAs($this->userWithPermissions());
        $data = $this->issueData($s);
        $bad = $data;
        $bad['items'][0]['so_item_id'] = $foreign->getKey();
        $this->postJson(self::URL.'/goods-issues', $bad)->assertUnprocessable();
        $this->postJson(self::URL.'/goods-issues', [...$data, 'items' => []])->assertUnprocessable();
        $this->postJson(self::URL.'/goods-issues', [...$data, 'items' => [$data['items'][0], $data['items'][0]]])->assertUnprocessable();
        foreach (['0', '-1', '0.0001', '11', '1e2'] as $quantity) {
            $bad = $data;
            $bad['items'][0]['quantity'] = $quantity;
            $this->postJson(self::URL.'/goods-issues', $bad)->assertUnprocessable();
        }
        $s['product']->update(['product_type' => 'Jasa']);
        $this->postJson(self::URL.'/goods-issues', $data)->assertUnprocessable();
        $this->assertDatabaseCount('goods_issues', 0);
    }

    public function test_serial_dispatch_validates_ownership_warehouse_status_count_and_existing_untracked_stock(): void
    {
        $s = $this->scenario('1.000');
        $s['product']->update(['track_serial' => true]);
        $this->actingAs($this->userWithPermissions());
        $this->postJson(self::URL.'/goods-receipts', $this->receiptData($s['product'], $s['warehouse'], '2', ['SERIAL-A', 'SERIAL-B']))->assertCreated();
        $ids = ProductSerial::query()->orderBy('serial_id')->pluck('serial_id')->all();
        $this->postJson(self::URL.'/goods-issues', $this->issueData($s, '3', $ids))->assertUnprocessable();
        $this->postJson(self::URL.'/goods-issues', $this->issueData($s, '1', [999999]))->assertUnprocessable();
        $otherWarehouse = Warehouse::factory()->create();
        $serial = ProductSerial::query()->firstOrFail();
        $serial->update(['warehouse_id' => $otherWarehouse->getKey()]);
        $this->postJson(self::URL.'/goods-issues', $this->issueData($s, '1', [$serial->getKey()]))->assertUnprocessable();
        $serial->update(['warehouse_id' => $s['warehouse']->getKey()]);
        $data = $this->issueData($s, '1', [$serial->getKey()]);
        $this->postJson(self::URL.'/goods-issues', $data)->assertCreated();
        $this->postJson(self::URL.'/goods-issues', $data)->assertOk();
        $this->assertSame('Issued', $serial->fresh()->status->value);
        $this->assertNull($serial->fresh()->warehouse_id);
        $data['request_key'] = (string) Str::uuid();
        $this->postJson(self::URL.'/goods-issues', $data)->assertUnprocessable();
        $this->assertDatabaseHas('stock_movements', ['serial_id' => $serial->getKey(), 'movement_type' => 'GOODS_OUT', 'quantity' => '1.000']);
        $this->assertDatabaseCount('goods_issues', 1);
    }

    public function test_audit_failure_rolls_back_dispatch_stock_serials_delivered_counts_and_all_statuses(): void
    {
        $s = $this->scenario();
        $s['product']->update(['track_serial' => true]);
        $serial = ProductSerial::query()->create([
            'product_id' => $s['product']->getKey(), 'warehouse_id' => $s['warehouse']->getKey(),
            'serial_number' => 'ROLLBACK-ISSUE', 'status' => 'In Stock',
        ]);
        $this->actingAs($this->userWithPermissions());
        $this->mock(AuditLogService::class, fn (MockInterface $mock) => $mock->shouldReceive('record')->once()->andThrow(new \RuntimeException('Test audit failure')));
        $this->postJson(self::URL.'/goods-issues', $this->issueData($s, '1', [$serial->getKey()]))->assertServerError();
        $this->assertDatabaseCount('goods_issues', 0);
        $this->assertDatabaseCount('goods_issue_items', 0);
        $this->assertDatabaseCount('stock_movements', 1);
        $this->assertDatabaseCount('transaction_status_history', 0);
        $this->assertSame('0.000', $s['item']->fresh()->quantity_delivered);
        $this->assertSame('Open', $s['order']->fresh()->status);
        $this->assertSame('In Stock', $serial->fresh()->status->value);
        $this->assertSame($s['warehouse']->getKey(), $serial->fresh()->warehouse_id);
    }
}
