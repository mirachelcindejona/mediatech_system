<?php

// Creates a NEW disposable database only. Never accepts or resets an existing database.
use App\Models\Inventory\Product;
use App\Models\Inventory\ProductSerial;
use App\Models\Inventory\Warehouse;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Sales\SalesOrder;
use App\Models\Sales\SalesOrderItem;
use App\Models\User;
use Database\Factories\Inventory\StockMovementFactory;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$path = $argv[1] ?? '';
if (PHP_SAPI !== 'cli' || realpath(dirname($path)) !== realpath(storage_path('framework/testing'))
    || ! preg_match('/^inventory-browser-[a-f0-9]{10}\.sqlite$/', basename($path)) || file_exists($path)) {
    throw new RuntimeException('Use a NEW inventory-browser-<10 hex>.sqlite file inside storage/framework/testing.');
}
touch($path);
config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $path, 'database.connections.sqlite.url' => null]);
DB::purge('sqlite');
if (Artisan::call('migrate', ['--force' => true, '--no-interaction' => true]) !== 0) {
    throw new RuntimeException('Disposable fixture migrations failed.');
}
$actor = User::factory()->create(['name' => 'Inventory browser fixture', 'email' => 'inventory-browser@example.test', 'password' => 'inventory-test-only']);
$role = Role::query()->create(['role_name' => 'Inventory browser fixture', 'description' => 'Disposable fixtures only', 'is_active' => true, 'is_super_admin' => false, 'is_system_role' => false]);
$actor->roles()->attach($role->getKey(), ['assigned_at' => now()]);
foreach (['read', 'create', 'update', 'post', 'export'] as $operation) {
    $permission = Permission::query()->create(['permission_code' => 'inventory.'.$operation, 'module_code' => 'inventory', 'operation_code' => $operation,
        'description' => 'Disposable Inventory permission', 'is_active' => true, 'is_assignable' => true, 'is_destructive_operation' => false]);
    $role->permissions()->attach($permission->getKey(), ['granted_at' => now()]);
}
$warehouses = [Warehouse::factory()->create(['warehouse_name' => 'Gudang Utama']), Warehouse::factory()->create(['warehouse_name' => 'Gudang Jakarta'])];
$order = SalesOrder::factory()->create(['so_number' => 'SO-TEST-001']);
for ($index = 1; $index <= 12; $index++) {
    $sku = 'BROWSER-'.str_pad((string) $index, 3, '0', STR_PAD_LEFT);
    $product = Product::factory()->create(['sku' => $sku, 'product_name' => 'Browser product '.$index, 'track_serial' => $index === 1]);
    $warehouse = $warehouses[$index === 2 ? 1 : 0];
    StockMovementFactory::new()->create(['product_id' => $product->getKey(), 'warehouse_id' => $warehouse->getKey(), 'quantity' => $index === 2 ? '20.000' : ($index === 3 ? '5.000' : '10.000')]);
    if ($index === 3) {
        StockMovementFactory::new()->create(['product_id' => $product->getKey(), 'warehouse_id' => $warehouses[1]->getKey(), 'quantity' => '5.000']);
    }
    SalesOrderItem::factory()->create(['sales_order_id' => $order->getKey(), 'product_id' => $product->getKey(), 'product_code_snapshot' => $sku,
        'product_name_snapshot' => $product->product_name, 'quantity_ordered' => $index === 2 ? '16.000' : '10.000', 'quantity_delivered' => $index === 2 ? '10.000' : '0.000']);
    if ($index === 1) {
        foreach (['BROWSER-LEGACY-A', 'BROWSER-LEGACY-B'] as $number) {
            ProductSerial::query()->create(['product_id' => $product->getKey(), 'warehouse_id' => $warehouse->getKey(), 'serial_number' => $number, 'status' => 'In Stock']);
        }
    }
}
echo json_encode(['fixture' => basename($path), 'products' => 12, 'opening_units' => '130.000', 'order' => $order->getKey()], JSON_THROW_ON_ERROR).PHP_EOL;
