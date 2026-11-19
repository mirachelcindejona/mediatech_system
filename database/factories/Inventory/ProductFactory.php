<?php

namespace Database\Factories\Inventory;

use App\Models\Inventory\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'sku' => fake()->unique()->bothify('TEST-PRD-########'), 'product_name' => 'Test inventory product',
            'category_id' => fn () => DB::table('product_categories')->insertGetId([
                'category_code' => fake()->unique()->bothify('TEST-CAT-########'),
                'category_name' => fake()->unique()->uuid(), 'is_active' => true,
            ], 'category_id'),
            'product_type' => 'Barang', 'unit' => 'pcs', 'selling_price' => '100.00',
            'purchase_price' => '75.25', 'min_stock' => '5.000', 'track_serial' => false, 'is_active' => true,
        ];
    }
}
