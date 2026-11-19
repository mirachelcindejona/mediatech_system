<?php

namespace Tests\Feature\Inventory;

use App\Models\Inventory\Product;
use App\Models\Inventory\Warehouse;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Sales\SalesOrder;
use App\Models\Sales\SalesOrderItem;
use App\Models\User;
use Database\Factories\Inventory\StockMovementFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

abstract class InventoryTestCase extends TestCase
{
    use RefreshDatabase;

    protected const URL = 'http://app.meditech.local/inventory';

    protected const ALL = ['inventory.read', 'inventory.create', 'inventory.update', 'inventory.post', 'inventory.export'];

    protected function userWithPermissions(array $codes = self::ALL, bool $active = true): User
    {
        $user = User::factory()->create(['is_active' => $active]);
        $role = Role::query()->create(['role_name' => 'Inventory test '.Str::uuid(), 'description' => 'Isolated Inventory test role', 'is_active' => true, 'is_super_admin' => false, 'is_system_role' => false]);
        $user->roles()->attach($role->getKey(), ['assigned_at' => now()]);
        foreach ($codes as $code) {
            [$module, $operation] = explode('.', $code);
            $permission = Permission::query()->firstOrCreate(['permission_code' => $code], [
                'module_code' => $module, 'operation_code' => $operation, 'description' => 'Inventory test permission',
                'is_active' => true, 'is_assignable' => true, 'is_destructive_operation' => false,
            ]);
            $role->permissions()->attach($permission->getKey(), ['granted_at' => now()]);
        }

        return $user;
    }

    protected function scenario(string $available = '10.000', string $ordered = '10.000'): array
    {
        $product = Product::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $order = SalesOrder::factory()->create();
        $item = SalesOrderItem::factory()->create(['sales_order_id' => $order->getKey(), 'product_id' => $product->getKey(), 'quantity_ordered' => $ordered]);
        StockMovementFactory::new()->create(['product_id' => $product->getKey(), 'warehouse_id' => $warehouse->getKey(), 'quantity' => $available]);

        return compact('product', 'warehouse', 'order', 'item');
    }

    protected function receiptData(Product $product, Warehouse $warehouse, string $quantity = '2.500', array $serials = []): array
    {
        return ['reference' => 'TEST-GR-'.Str::uuid(), 'product_id' => $product->getKey(), 'warehouse_id' => $warehouse->getKey(), 'quantity' => $quantity, 'serial_numbers' => $serials, 'notes' => null];
    }

    protected function issueData(array $scenario, string $quantity = '2.500', array $serials = []): array
    {
        return ['request_key' => (string) Str::uuid(), 'sales_order_id' => $scenario['order']->getKey(), 'notes' => null, 'items' => [
            ['so_item_id' => $scenario['item']->getKey(), 'warehouse_id' => $scenario['warehouse']->getKey(), 'quantity' => $quantity, 'serial_ids' => $serials],
        ]];
    }
}
