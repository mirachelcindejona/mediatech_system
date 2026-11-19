<?php

namespace Tests\Feature\Finance;

use App\Models\User;
use Database\Seeders\System\PermissionSeeder;
use Database\Seeders\System\RolePermissionSeeder;
use Database\Seeders\System\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PaymentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private const INTERNAL_HOST = 'http://app.meditech.local';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);
    }

    private function createUserWithRole(string $email, string $roleName): User
    {
        $userId = DB::table('users')->insertGetId([
            'name' => 'Test ' . $roleName,
            'email' => $email,
            'password' => Hash::make('password'),
            'is_active' => true,
            'email_verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ], 'user_id');

        $roleId = (int) DB::table('roles')->where('role_name', $roleName)->value('role_id');
        DB::table('user_roles')->insert([
            'user_id' => $userId,
            'role_id' => $roleId,
            'assigned_by' => null,
            'assigned_at' => now(),
        ]);

        return User::findOrFail($userId);
    }

    public function test_guest_cannot_access_payments(): void
    {
        $this->get(self::INTERNAL_HOST . '/payments')
            ->assertRedirect(self::INTERNAL_HOST . '/login');
    }

    public function test_finance_role_can_open_payment_index(): void
    {
        $user = $this->createUserWithRole('payment-finance@test.local', 'Finance');

        $this->actingAs($user)
            ->get(self::INTERNAL_HOST . '/payments')
            ->assertOk()
            ->assertSee('Payment')
            ->assertSee('Ekspor Excel/CSV');
    }

    public function test_payment_export_returns_csv_for_finance_role(): void
    {
        $user = $this->createUserWithRole('payment-export@test.local', 'Finance');

        $response = $this->actingAs($user)->get(self::INTERNAL_HOST . '/payments/export');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('No. Payment', $response->streamedContent());
    }

    public function test_role_without_payment_permission_cannot_open_payment_index(): void
    {
        $user = $this->createUserWithRole('payment-sales@test.local', 'Sales');

        $this->actingAs($user)
            ->get(self::INTERNAL_HOST . '/payments')
            ->assertForbidden();
    }
}
