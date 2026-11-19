<?php

namespace Database\Factories\Inventory;

use App\Enums\StockMovementType;
use App\Models\Inventory\Product;
use App\Models\Inventory\StockMovement;
use App\Models\Inventory\Warehouse;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class StockMovementFactory extends Factory
{
    protected $model = StockMovement::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(), 'warehouse_id' => Warehouse::factory(), 'recorded_by' => User::factory(),
            'movement_date' => now(), 'movement_type' => StockMovementType::OpeningBalance, 'quantity' => '10.000',
            'reference_number' => fake()->unique()->bothify('TEST-OPEN-########'), 'adjustment_reason' => 'Opening balance for isolated tests.',
        ];
    }
}
