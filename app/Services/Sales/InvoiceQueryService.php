<?php

namespace App\Services\Sales;

use App\Enums\InvoiceStatus;
use App\Models\Sales\Invoice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InvoiceQueryService
{
    private const VALID_PER_PAGE = [5, 10, 25, 50];

    public function normalizedFilters(array $filters): array
    {
        $perPage = (int) ($filters['per_page'] ?? 10);
        if (!in_array($perPage, self::VALID_PER_PAGE, true)) {
            $perPage = 10;
        }

        $status = $filters['status'] ?? null;
        $validStatuses = array_map(fn (InvoiceStatus $status) => $status->value, InvoiceStatus::cases());
        if ($status !== null && !in_array($status, $validStatuses, true)) {
            $status = null;
        }

        return [
            'search' => trim((string) ($filters['search'] ?? '')),
            'status' => $status,
            'per_page' => $perPage,
        ];
    }

    public function paginate(array $filters): LengthAwarePaginator
    {
        $filters = $this->normalizedFilters($filters);

        $query = $this->invoiceQuery()
            ->with(['salesOrder', 'createdBy', 'items']);

        if ($filters['search'] !== '') {
            $search = '%' . $filters['search'] . '%';
            $query->where(function (Builder $builder) use ($search) {
                $builder->where('invoice_number', 'like', $search)
                    ->orWhere('bill_to_snapshot', 'like', $search)
                    ->orWhere('reference_number', 'like', $search)
                    ->orWhereIn('sales_order_id', function ($subquery) use ($search) {
                        $subquery->select('sales_order_id')
                            ->from('sales_orders')
                            ->where('so_number', 'like', $search);
                    });
            });
        }

        if ($filters['status'] !== null) {
            $query->where('status', $filters['status']);
        }

        return $query
            ->orderByDesc('invoice_date')
            ->orderByDesc('invoice_id')
            ->paginate($filters['per_page'])
            ->withQueryString();
    }

    public function summary(): array
    {
        $rows = DB::table('invoices')
            ->select(['grand_total', 'due_date'])
            ->selectSub($this->validatedPaidAmountSubquery(), 'paid_amount')
            ->get();

        $outstanding = 0.0;
        $paid = 0.0;
        $overdueCount = 0;
        $today = now()->toDateString();

        foreach ($rows as $row) {
            $grandTotal = (float) $row->grand_total;
            $paidAmount = (float) ($row->paid_amount ?? 0);
            $remaining = max(0, $grandTotal - $paidAmount);

            $paid += $paidAmount;
            if ($remaining > 0.009) {
                $outstanding += $remaining;
                if ($row->due_date < $today) {
                    $overdueCount++;
                }
            }
        }

        return [
            'outstanding_total' => round($outstanding, 2),
            'paid_total' => round($paid, 2),
            'overdue_count' => $overdueCount,
        ];
    }

    /**
     * Sales Order dan item yang masih mempunyai kuantitas yang dapat ditagihkan.
     * Barang keluar dibatasi quantity_delivered; Termin dibatasi quantity_ordered.
     */
    public function salesOrdersForInvoiceForm(): Collection
    {
        $billedQuantities = DB::table('invoice_items')
            ->select('so_item_id')
            ->selectRaw('SUM(quantity) as billed_quantity')
            ->groupBy('so_item_id');

        $orders = DB::table('sales_orders as so')
            ->where('so.status', '<>', 'Cancelled')
            ->orderByDesc('so.order_date')
            ->orderByDesc('so.sales_order_id')
            ->get([
                'so.sales_order_id',
                'so.so_number',
                'so.status',
                'so.reference_number',
                'so.sold_to_name_snapshot',
                'so.sold_to_address_snapshot',
                'so.ship_to_name_snapshot',
                'so.ship_to_address_snapshot',
                'so.contact_phone_snapshot',
                'so.attention_snapshot',
                'so.sales_name_snapshot',
                'so.payment_terms_snapshot',
                'so.subtotal',
                'so.discount_amount',
                'so.tax_rate',
                'so.tax_amount',
                'so.other_charges',
                'so.grand_total',
                'so.order_date',
            ]);

        return $orders->map(function ($order) use ($billedQuantities) {
            $items = DB::table('sales_order_items as soi')
                ->leftJoinSub(clone $billedQuantities, 'billed', function ($join) {
                    $join->on('billed.so_item_id', '=', 'soi.so_item_id');
                })
                ->where('soi.sales_order_id', $order->sales_order_id)
                ->orderBy('soi.so_item_id')
                ->get([
                    'soi.so_item_id',
                    'soi.product_code_snapshot',
                    'soi.product_name_snapshot',
                    'soi.unit_snapshot',
                    'soi.quantity_ordered',
                    'soi.quantity_delivered',
                    'soi.unit_price',
                    'soi.discount_amount',
                    'soi.subtotal',
                    DB::raw('COALESCE(billed.billed_quantity, 0) as billed_quantity'),
                ])
                ->map(function ($item) {
                    $billed = (float) $item->billed_quantity;
                    $availableForDelivery = max(0, (float) $item->quantity_delivered - $billed);
                    $availableForTerm = max(0, (float) $item->quantity_ordered - $billed);

                    return [
                        'so_item_id' => (int) $item->so_item_id,
                        'product_code' => $item->product_code_snapshot,
                        'product_name' => $item->product_name_snapshot,
                        'unit' => $item->unit_snapshot,
                        'quantity_ordered' => (float) $item->quantity_ordered,
                        'quantity_delivered' => (float) $item->quantity_delivered,
                        'billed_quantity' => $billed,
                        'available_for_delivery' => $availableForDelivery,
                        'available_for_term' => $availableForTerm,
                        'unit_price' => (float) $item->unit_price,
                        'discount_amount' => (float) $item->discount_amount,
                        'subtotal' => (float) $item->subtotal,
                    ];
                })
                ->filter(fn (array $item) => $item['available_for_delivery'] > 0 || $item['available_for_term'] > 0)
                ->values();

            if ($items->isEmpty()) {
                return null;
            }

            return [
                'sales_order_id' => (int) $order->sales_order_id,
                'so_number' => $order->so_number,
                'status' => $order->status,
                'reference_number' => $order->reference_number,
                'customer_name' => $order->sold_to_name_snapshot,
                'customer_address' => $order->sold_to_address_snapshot,
                'ship_to_name' => $order->ship_to_name_snapshot,
                'ship_to_address' => $order->ship_to_address_snapshot,
                'contact_phone' => $order->contact_phone_snapshot,
                'attention' => $order->attention_snapshot,
                'sales_name' => $order->sales_name_snapshot,
                'payment_terms' => $order->payment_terms_snapshot,
                'subtotal' => (float) $order->subtotal,
                'discount_amount' => (float) $order->discount_amount,
                'tax_rate' => (float) $order->tax_rate,
                'tax_amount' => (float) $order->tax_amount,
                'other_charges' => (float) $order->other_charges,
                'grand_total' => (float) $order->grand_total,
                'order_date' => $order->order_date,
                'items' => $items,
            ];
        })->filter()->values();
    }

    public function paidAmount(int $invoiceId): float
    {
        return (float) (DB::table('payment_allocations as allocation')
            ->join('payments as payment', 'payment.payment_id', '=', 'allocation.payment_id')
            ->where('allocation.invoice_id', $invoiceId)
            ->where('payment.status', 'Validated')
            ->sum('allocation.allocated_amount'));
    }

    public function paymentsForInvoice(int $invoiceId): Collection
    {
        return DB::table('payment_allocations as allocation')
            ->join('payments as payment', 'payment.payment_id', '=', 'allocation.payment_id')
            ->leftJoin('users as recorder', 'recorder.user_id', '=', 'payment.recorded_by')
            ->leftJoin('users as validator', 'validator.user_id', '=', 'payment.validated_by')
            ->where('allocation.invoice_id', $invoiceId)
            ->orderByDesc('payment.payment_date')
            ->orderByDesc('payment.payment_id')
            ->get([
                'payment.payment_id',
                'payment.payment_number',
                'payment.payment_date',
                'payment.payment_method',
                'payment.reference_number',
                'payment.status',
                'payment.validated_at',
                'payment.amount',
                'allocation.allocated_amount',
                'recorder.name as recorded_by_name',
                'validator.name as validated_by_name',
            ]);
    }

    private function invoiceQuery(): Builder
    {
        return Invoice::query()
            ->select('invoices.*')
            ->selectSub($this->validatedPaidAmountSubquery(), 'paid_amount');
    }

    private function validatedPaidAmountSubquery()
    {
        return DB::table('payment_allocations as allocation')
            ->join('payments as payment', 'payment.payment_id', '=', 'allocation.payment_id')
            ->whereColumn('allocation.invoice_id', 'invoices.invoice_id')
            ->where('payment.status', 'Validated')
            ->selectRaw('COALESCE(SUM(allocation.allocated_amount), 0)');
    }

    public function exportRows(array $filters = []): Builder
    {
        $filters = $this->normalizedFilters($filters);

        $query = $this->invoiceQuery()
            ->with('salesOrder');

        // Filter pencarian.
        if (!empty($filters['search'])) {
            $search = $filters['search'];

            $query->where(function (Builder $query) use ($search) {
                $query->where('invoices.invoice_number', 'like', "%{$search}%")
                    ->orWhere('invoices.bill_to_snapshot', 'like', "%{$search}%")
                    ->orWhere('invoices.reference_number', 'like', "%{$search}%")
                    ->orWhereHas('salesOrder', function (Builder $salesOrderQuery) use ($search) {
                        $salesOrderQuery->where(
                            'so_number',
                            'like',
                            "%{$search}%"
                        );
                    });
            });
        }

        // Filter status.
        if (!empty($filters['status'])) {
            $query->where('invoices.status', $filters['status']);
        }

        return $query->orderBy('invoices.invoice_id');
    }
}
