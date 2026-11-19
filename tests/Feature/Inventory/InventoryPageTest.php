<?php

namespace Tests\Feature\Inventory;

use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route;

class InventoryPageTest extends InventoryTestCase
{
    public function test_guest_and_inactive_users_cannot_access_inventory(): void
    {
        $this->get(self::URL)->assertRedirect('http://app.meditech.local/login');
        $this->actingAs($this->userWithPermissions(self::ALL, false))->get(self::URL)->assertRedirect();
    }

    public function test_permission_is_required_for_page_and_read_endpoints(): void
    {
        $this->actingAs($this->userWithPermissions([]));
        foreach (['', '/stocks', '/movements', '/orders', '/orders/1', '/export/stocks'] as $path) {
            $this->getJson(self::URL.$path)->assertForbidden();
        }
    }

    public function test_reader_sees_live_inventory_without_posting_forms_or_export_actions(): void
    {
        $this->scenario();
        $this->actingAs($this->userWithPermissions(['inventory.read']))->get(self::URL)->assertOk()
            ->assertSee('inventory-stock-rows', false)->assertSee('inventory-bootstrap', false)
            ->assertDontSee('id="goods-in-form"', false)->assertDontSee('id="goods-out-form"', false)
            ->assertDontSee('data-export-table=', false)->assertDontSee('Pratinjau');
        $this->getJson(self::URL.'/stocks')->assertOk()->assertJsonPath('stocks.total', 1)->assertJsonPath('summary.units', '10.000');
        $this->assertDatabaseCount('goods_receipts', 0);
        $this->assertDatabaseCount('goods_issues', 0);
    }

    public function test_create_and_update_do_not_grant_posting_permission(): void
    {
        $this->actingAs($this->userWithPermissions(['inventory.read', 'inventory.create', 'inventory.update']))
            ->get(self::URL)->assertOk()->assertDontSee('id="goods-in-form"', false)->assertDontSee('id="goods-out-form"', false);
        $this->postJson(self::URL.'/goods-receipts', [])->assertForbidden();
        $this->postJson(self::URL.'/goods-issues', [])->assertForbidden();
    }

    public function test_forms_and_all_four_pagers_are_available_to_authorized_poster(): void
    {
        $response = $this->actingAs($this->userWithPermissions())->get(self::URL)->assertOk()
            ->assertSee('id="goods-in-form"', false)->assertSee('id="goods-out-form"', false)
            ->assertSee('Konfirmasi &amp; keluarkan stok', false)->assertDontSee('name="supplier_id"', false)
            ->assertDontSee('name="unit_cost"', false);
        foreach (['inventory-stock', 'inventory-movement', 'inventory-issue', 'inventory-confirm'] as $prefix) {
            foreach (['size', 'prev', 'next', 'count', 'page'] as $control) {
                $response->assertSee('id="'.$prefix.'-'.$control.'"', false);
            }
        }
    }

    public function test_post_permission_does_not_replace_read_create_or_update_permissions(): void
    {
        foreach ([
            ['inventory.read', 'inventory.post'],
            ['inventory.create', 'inventory.update', 'inventory.post'],
            ['inventory.read', 'inventory.create'],
            ['inventory.read', 'inventory.update'],
        ] as $permissions) {
            $this->actingAs($this->userWithPermissions($permissions));
            $this->postJson(self::URL.'/goods-receipts', [])->assertForbidden();
            $this->postJson(self::URL.'/goods-issues', [])->assertForbidden();
        }
        $this->assertDatabaseCount('goods_receipts', 0);
        $this->assertDatabaseCount('goods_issues', 0);
    }

    public function test_export_permission_is_required_and_unknown_export_targets_are_rejected(): void
    {
        $this->actingAs($this->userWithPermissions(['inventory.read']))->getJson(self::URL.'/export/stocks')->assertForbidden();
        $this->actingAs($this->userWithPermissions(['inventory.read', 'inventory.export']))->getJson(self::URL.'/export/stocks')->assertOk();
        $this->getJson(self::URL.'/export/payments')->assertNotFound();
    }

    public function test_inventory_routes_remain_available_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        Route::setRoutes(new RouteCollection);
        Route::middleware(['auth', 'active'])->group(base_path('routes/inventory.php'));
        Route::getRoutes()->refreshNameLookups();
        $route = Route::getRoutes()->getByName('inventory.index');
        $this->assertSame('inventory', $route->uri());
        foreach (['auth', 'active', 'permission:inventory.read'] as $middleware) {
            $this->assertContains($middleware, $route->gatherMiddleware());
        }
    }
}
