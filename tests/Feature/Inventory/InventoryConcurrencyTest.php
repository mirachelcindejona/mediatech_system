<?php

namespace Tests\Feature\Inventory;

use App\Models\Inventory\Product;
use App\Models\Inventory\ProductSerial;
use App\Models\Inventory\Warehouse;
use App\Models\Role;
use App\Models\Sales\SalesOrder;
use App\Models\Sales\SalesOrderItem;
use App\Models\User;
use App\Services\Inventory\UpdateStockService;
use Database\Factories\Inventory\StockMovementFactory;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class InventoryConcurrencyTest extends TestCase
{
    private bool $disposable = false;

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('INVENTORY_DISPOSABLE_MYSQL') !== '1' || DB::connection()->getDriverName() !== 'mysql'
            || ! str_starts_with(DB::connection()->getDatabaseName(), 'mediatech_inventory_test_')) {
            $this->markTestSkipped('Requires an isolated mediatech_inventory_test_* MySQL database and INVENTORY_DISPOSABLE_MYSQL=1.');
        }
        $this->disposable = true;
        Artisan::call('migrate:fresh', ['--force' => true]);
    }

    protected function tearDown(): void
    {
        try {
            if ($this->disposable) {
                Artisan::call('migrate:fresh', ['--force' => true]);
            }
        } finally {
            parent::tearDown();
        }
    }

    private function actor(): User
    {
        $actor = User::factory()->create();
        $role = Role::query()->create(['role_name' => 'Concurrent Inventory test', 'description' => 'Disposable test role', 'is_active' => true, 'is_super_admin' => true, 'is_system_role' => false]);
        $actor->roles()->attach($role->getKey(), ['assigned_at' => now()]);

        return $actor;
    }

    private function compete(bool $retry = false, bool $serialized = false): array
    {
        $actor = $this->actor();
        $product = Product::factory()->create(['track_serial' => $serialized]);
        $warehouse = Warehouse::factory()->create();
        StockMovementFactory::new()->create(['product_id' => $product->getKey(), 'warehouse_id' => $warehouse->getKey(), 'quantity' => '5.000']);
        $serial = $serialized ? ProductSerial::query()->create(['product_id' => $product->getKey(), 'warehouse_id' => $warehouse->getKey(), 'serial_number' => 'CONCURRENT-SERIAL', 'status' => 'In Stock']) : null;
        $order = SalesOrder::factory()->create();
        $item = SalesOrderItem::factory()->create(['sales_order_id' => $order->getKey(), 'product_id' => $product->getKey()]);
        $otherOrder = $retry ? $order : SalesOrder::factory()->create();
        $otherItem = $retry ? $item : SalesOrderItem::factory()->create(['sales_order_id' => $otherOrder->getKey(), 'product_id' => $product->getKey()]);
        $key = (string) Str::uuid();
        $payload = fn ($o, $i, $requestKey) => ['request_key' => $requestKey, 'sales_order_id' => $o->getKey(), 'notes' => null, 'items' => [
            ['so_item_id' => $i->getKey(), 'warehouse_id' => $warehouse->getKey(), 'quantity' => $serialized ? '1.000' : '4.000', 'serial_ids' => $serial ? [$serial->getKey()] : []],
        ]];
        $config = DB::connection()->getConfig();
        $env = [
            'APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => $config['database'],
            'DB_HOST' => $config['host'], 'DB_PORT' => (string) $config['port'], 'DB_USERNAME' => $config['username'],
            'DB_PASSWORD' => (string) $config['password'], 'DB_URL' => '', 'SESSION_DRIVER' => 'array', 'CACHE_STORE' => 'array',
            'INVENTORY_DISPOSABLE_MYSQL' => '1',
        ];
        $processes = [];
        foreach ([$payload($order, $item, $key), $payload($otherOrder, $otherItem, $retry ? $key : (string) Str::uuid())] as $data) {
            $processes[] = new Process([PHP_BINARY, base_path('tests/Support/inventory-post-worker.php'), (string) $actor->getKey(), base64_encode(json_encode($data, JSON_THROW_ON_ERROR))], base_path(), $env, timeout: 20);
        }
        DB::beginTransaction();
        try {
            Product::query()->whereKey($product->getKey())->lockForUpdate()->firstOrFail();
            foreach ($processes as $process) {
                $process->start();
            }
            $ids = [];
            foreach ($processes as $process) {
                $this->assertTrue($process->waitUntil(fn ($type, $buffer) => preg_match('/READY:\d+\R/', $process->getOutput()) === 1), 'Worker must reach the real HTTP request.');
                preg_match('/READY:(\d+)/', $process->getOutput(), $matches);
                $ids[] = (int) $matches[1];
            }
            $waiting = 0;
            $deadline = microtime(true) + 8;
            do {
                $waiting = DB::table('information_schema.innodb_trx')->whereIn('trx_mysql_thread_id', $ids)->where('trx_state', 'LOCK WAIT')->count();
                if ($waiting === 2) {
                    break;
                }
                usleep(20000);
            } while (microtime(true) < $deadline);
            $this->assertSame(2, $waiting, 'Both actual MySQL transactions must overlap and wait before the gate is released.');
            DB::commit();
            $responses = [];
            foreach ($processes as $process) {
                $this->assertSame(0, $process->wait(), $process->getErrorOutput());
                $lines = preg_split('/\R/', trim($process->getOutput()));
                $responses[] = json_decode(end($lines), true, flags: JSON_THROW_ON_ERROR);
            }

            return [$responses, $product, $warehouse, $serial];
        } finally {
            if (DB::transactionLevel()) {
                DB::rollBack();
            }
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }
        }
    }

    public function test_competing_orders_cannot_oversell_the_same_product(): void
    {
        [$responses, $product, $warehouse] = $this->compete();
        $statuses = array_column($responses, 'status');
        sort($statuses);
        $this->assertSame([201, 422], $statuses);
        $this->assertSame('1.000', (string) app(UpdateStockService::class)->quantity($product->getKey(), $warehouse->getKey()));
        $this->assertDatabaseCount('goods_issues', 1);
        $this->assertEquals(4, DB::table('sales_order_items')->sum('quantity_delivered'));
    }

    public function test_overlapping_retries_create_only_one_posted_issue(): void
    {
        [$responses, $product, $warehouse] = $this->compete(retry: true);
        $statuses = array_column($responses, 'status');
        sort($statuses);
        $this->assertSame([200, 201], $statuses);
        $this->assertSame($responses[0]['data']['id'], $responses[1]['data']['id']);
        $this->assertSame('1.000', (string) app(UpdateStockService::class)->quantity($product->getKey(), $warehouse->getKey()));
        $this->assertDatabaseCount('goods_issues', 1);
        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_competing_orders_cannot_issue_one_serial_twice(): void
    {
        [$responses, $product, $warehouse, $serial] = $this->compete(serialized: true);
        $statuses = array_column($responses, 'status');
        sort($statuses);
        $this->assertSame([201, 422], $statuses);
        $this->assertSame('Issued', $serial->fresh()->status->value);
        $this->assertSame('4.000', (string) app(UpdateStockService::class)->quantity($product->getKey(), $warehouse->getKey()));
        $this->assertDatabaseCount('goods_issues', 1);
    }

    public function test_mysql_preserves_maximum_quantity_precision_and_rejects_invalid_ledger_sources(): void
    {
        $actor = $this->actor();
        $product = Product::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $maximum = '999999999999999.999';
        $this->actingAs($actor)->postJson('http://app.meditech.local/inventory/goods-receipts', [
            'product_id' => $product->getKey(), 'warehouse_id' => $warehouse->getKey(),
            'reference' => 'MYSQL-MAXIMUM-QUANTITY', 'quantity' => $maximum, 'serial_numbers' => [],
        ])->assertCreated();
        $this->assertSame($maximum, (string) app(UpdateStockService::class)->quantity($product->getKey(), $warehouse->getKey()));
        foreach ([
            ['movement_type' => 'OPENING_BALANCE', 'quantity' => '-1.000'],
            ['movement_type' => 'GOODS_IN', 'quantity' => '1.000'],
        ] as $invalid) {
            try {
                StockMovementFactory::new()->create([...$invalid, 'product_id' => $product->getKey(), 'warehouse_id' => $warehouse->getKey()]);
                $this->fail('MySQL must reject an invalid quantity or missing receipt source.');
            } catch (QueryException $error) {
                $this->assertStringContainsString('constraint', strtolower($error->getMessage()));
            }
        }
        $this->assertDatabaseCount('stock_movements', 1);
    }
}
