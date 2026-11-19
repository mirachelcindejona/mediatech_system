<?php

namespace Database\Factories\Sales;

use App\Enums\SalesOrderStatus;
use App\Models\Sales\SalesOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

class SalesOrderFactory extends Factory
{
    protected $model = SalesOrder::class;

    public function definition(): array
    {
        $actor = User::factory()->create();
        $customer = DB::table('customers')->insertGetId([
            'customer_code' => fake()->unique()->bothify('TEST-CUST-########'), 'name' => 'Test customer',
            'industry_sector' => 'Testing', 'address' => 'Test address', 'phone' => '000000', 'email' => 'customer@example.test',
        ], 'customer_id');
        $quotation = DB::table('quotations')->insertGetId([
            'quotation_number' => fake()->unique()->bothify('TEST-QT-########'), 'customer_id' => $customer, 'created_by' => $actor->getKey(),
            'quotation_date' => today(), 'valid_until' => today()->addDays(30), 'bill_to_name_snapshot' => 'Test customer',
            'contact_phone_snapshot' => '000000', 'attention_snapshot' => 'Test contact', 'payment_terms_snapshot' => 'Test terms',
            'subtotal' => '1000.00', 'tax_rate' => '0.0000', 'tax_amount' => '0.00', 'grand_total' => '1000.00', 'status' => 'Approved',
        ], 'quotation_id');

        return [
            'so_number' => fake()->unique()->bothify('TEST-SO-########'), 'customer_id' => $customer, 'quotation_id' => $quotation,
            'created_by' => $actor->getKey(), 'salesperson_user_id' => $actor->getKey(), 'reference_number' => 'TEST-REFERENCE',
            'sales_name_snapshot' => 'Test salesperson', 'sold_to_name_snapshot' => 'Test customer', 'sold_to_address_snapshot' => 'Test address',
            'ship_to_name_snapshot' => 'Test customer', 'contact_phone_snapshot' => '000000', 'attention_snapshot' => 'Test contact',
            'payment_terms_snapshot' => 'Test terms', 'order_date' => today(), 'delivery_date' => today()->addDays(7),
            'shipment_method' => 'Test shipment', 'peripheral_notes' => '', 'subtotal' => '1000.00', 'tax_rate' => '0.0000',
            'tax_amount' => '0.00', 'total_amount' => '1000.00', 'grand_total' => '1000.00', 'status' => SalesOrderStatus::Open->value,
        ];
    }
}
