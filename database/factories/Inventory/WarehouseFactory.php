<?php

namespace Database\Factories\Inventory;

use App\Models\Inventory\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

class WarehouseFactory extends Factory
{
    protected $model = Warehouse::class;

    public function definition(): array
    {
        return ['warehouse_code' => fake()->unique()->bothify('TEST-WH-########'), 'warehouse_name' => 'Test warehouse', 'location' => 'Test location', 'is_active' => true];
    }
}
