<?php

namespace Database\Factories\Sales;

use App\Models\Inventory\Product;
use App\Models\Sales\SalesOrder;
use App\Models\Sales\SalesOrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class SalesOrderItemFactory extends Factory
{
    protected $model = SalesOrderItem::class;

    public function definition(): array
    {
        return [
            'sales_order_id' => SalesOrder::factory(), 'product_id' => Product::factory(),
            'product_code_snapshot' => fn ($a) => Product::findOrFail($a['product_id'])->sku,
            'product_name_snapshot' => 'Original product snapshot', 'unit_snapshot' => 'pcs',
            'quantity_ordered' => '10.000', 'quantity_delivered' => '0.000', 'unit_price' => '100.00',
            'unit_cost_snapshot' => '70.00', 'discount_amount' => '0.00', 'subtotal' => '1000.00',
        ];
    }
}
