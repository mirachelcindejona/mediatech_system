<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Services\Inventory\InventoryQueryService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InventoryController extends Controller
{
    public function __construct(private InventoryQueryService $query) {}

    public function __invoke(Request $request): View
    {
        $data = $request->validate(['sales_order_id' => ['nullable', 'integer', 'min:1']]);

        return view('inventory.index', ['inventory' => [
            'summary' => $this->query->summary(), 'stocks' => $this->query->stocks([]),
            'products' => $request->user()->can('inventory.create') && $request->user()->can('inventory.post') ? $this->query->products() : [],
            'warehouses' => $this->query->warehouses(),
            'orders' => $request->user()->can('inventory.update') && $request->user()->can('inventory.post') ? $this->query->orders() : [],
            'sales_order_id' => $data['sales_order_id'] ?? null,
            'urls' => [
                'stocks' => route('inventory.stocks'), 'movements' => route('inventory.movements'),
                'orders' => route('inventory.orders'), 'order' => route('inventory.orders.show', ['salesOrder' => '__ID__']),
                'receive' => route('inventory.goods-receipts.store'), 'dispatch' => route('inventory.goods-issues.store'),
                'export' => route('inventory.export', ['table' => '__TABLE__']),
            ],
        ]]);
    }

    private function filters(Request $request): array
    {
        return $request->validate([
            'search' => ['nullable', 'string', 'max:191'], 'warehouse_id' => ['nullable', 'integer', 'min:1'],
            'page' => ['nullable', 'integer', 'min:1'], 'per_page' => ['nullable', Rule::in([5, 10, 25, 50])],
        ]);
    }

    public function stocks(Request $request): JsonResponse
    {
        return response()->json(['stocks' => $this->query->stocks($this->filters($request)), 'summary' => $this->query->summary()]);
    }

    public function movements(Request $request): JsonResponse
    {
        return response()->json($this->query->movements($this->filters($request)));
    }

    public function orders(): JsonResponse
    {
        return response()->json($this->query->orders());
    }

    public function order(string $salesOrder): JsonResponse
    {
        return response()->json($this->query->order($salesOrder));
    }

    public function export(Request $request, string $table): JsonResponse
    {
        $filters = $this->filters($request);
        $rows = $table === 'stocks'
            ? $this->query->stockQuery($filters)->orderBy('p.sku')->orderBy('w.warehouse_id')->get()->map(fn ($r) => $this->query->stockRow($r))
            : $this->query->movementRows($this->query->movementQuery($filters)->orderByDesc('m.movement_date')->orderByDesc('m.movement_id')->get());

        return response()->json($rows);
    }
}
