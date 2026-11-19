<?php

namespace App\Services\Sales;

use App\Enums\InvoiceStatus;
use App\Models\Sales\Invoice;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateInvoiceService
{
    public function __construct(private readonly AuditLogService $auditLog)
    {
    }

    public function create(array $data, User $actor): Invoice
    {
        return DB::transaction(function () use ($data, $actor) {
            $salesOrder = DB::table('sales_orders')
                ->where('sales_order_id', $data['sales_order_id'])
                ->lockForUpdate()
                ->first();

            if (!$salesOrder) {
                throw ValidationException::withMessages([
                    'sales_order_id' => 'Sales Order yang dipilih tidak ditemukan.',
                ]);
            }

            if ($salesOrder->status === 'Cancelled') {
                throw ValidationException::withMessages([
                    'sales_order_id' => 'Sales Order yang sudah dibatalkan tidak dapat ditagihkan.',
                ]);
            }

            $invoiceType = $data['invoice_type'];
            if (!in_array($invoiceType, ['Barang keluar', 'Termin'], true)) {
                throw ValidationException::withMessages([
                    'invoice_type' => 'Tipe invoice tidak valid.',
                ]);
            }

            $requestedItems = collect($data['items'] ?? []);
            if ($requestedItems->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => 'Pilih minimal satu item Sales Order untuk ditagihkan.',
                ]);
            }

            $itemIds = $requestedItems->pluck('so_item_id')->map(fn ($id) => (int) $id)->all();
            if (count($itemIds) !== count(array_unique($itemIds))) {
                throw ValidationException::withMessages([
                    'items' => 'Item Sales Order tidak boleh dipilih lebih dari satu kali.',
                ]);
            }

            // Lock item SO agar dua request bersamaan tidak menagihkan kuantitas yang sama.
            $salesOrderItems = DB::table('sales_order_items')
                ->where('sales_order_id', $salesOrder->sales_order_id)
                ->whereIn('so_item_id', $itemIds)
                ->orderBy('so_item_id')
                ->lockForUpdate()
                ->get()
                ->keyBy('so_item_id');

            if ($salesOrderItems->count() !== count($itemIds)) {
                throw ValidationException::withMessages([
                    'items' => 'Satu atau lebih item tidak berasal dari Sales Order yang dipilih.',
                ]);
            }

            $billedQuantities = DB::table('invoice_items')
                ->select('so_item_id')
                ->selectRaw('SUM(quantity) as billed_quantity')
                ->whereIn('so_item_id', $itemIds)
                ->groupBy('so_item_id')
                ->pluck('billed_quantity', 'so_item_id');

            $invoiceLines = [];
            $invoiceSubtotal = 0.0;

            foreach ($requestedItems as $index => $requestedItem) {
                $soItemId = (int) $requestedItem['so_item_id'];
                $salesOrderItem = $salesOrderItems->get($soItemId);
                $quantity = round((float) $requestedItem['quantity'], 3);
                $orderedQuantity = (float) $salesOrderItem->quantity_ordered;
                $deliveredQuantity = (float) $salesOrderItem->quantity_delivered;
                $alreadyBilled = (float) ($billedQuantities[$soItemId] ?? 0);
                $billableQuantity = $invoiceType === 'Barang keluar'
                    ? $deliveredQuantity
                    : $orderedQuantity;
                $availableQuantity = max(0, $billableQuantity - $alreadyBilled);

                if ($quantity <= 0 || $quantity > $availableQuantity + 0.0001) {
                    throw ValidationException::withMessages([
                        "items.{$index}.quantity" => sprintf(
                            'Kuantitas yang dapat ditagihkan untuk %s maksimal %s %s.',
                            $salesOrderItem->product_name_snapshot,
                            rtrim(rtrim(number_format($availableQuantity, 3, '.', ''), '0'), '.'),
                            $salesOrderItem->unit_snapshot
                        ),
                    ]);
                }

                if ($orderedQuantity <= 0) {
                    throw ValidationException::withMessages([
                        "items.{$index}.quantity" => 'Kuantitas pesanan pada Sales Order tidak valid.',
                    ]);
                }

                $unitPrice = (float) $salesOrderItem->unit_price;
                $grossAmount = round($quantity * $unitPrice, 2);
                $lineDiscount = round(((float) $salesOrderItem->discount_amount) * ($quantity / $orderedQuantity), 2);
                $lineSubtotal = max(0, round($grossAmount - $lineDiscount, 2));

                $invoiceLines[] = [
                    'so_item_id' => $soItemId,
                    'product_code_snapshot' => $salesOrderItem->product_code_snapshot,
                    'product_name_snapshot' => $salesOrderItem->product_name_snapshot,
                    'unit_snapshot' => $salesOrderItem->unit_snapshot,
                    'quantity' => $quantity,
                    'unit_price' => round($unitPrice, 2),
                    'discount_amount' => $lineDiscount,
                    'subtotal' => $lineSubtotal,
                ];

                $invoiceSubtotal += $lineSubtotal;
            }

            $invoiceSubtotal = round($invoiceSubtotal, 2);
            if ($invoiceSubtotal <= 0) {
                throw ValidationException::withMessages([
                    'items' => 'Total item invoice harus lebih dari nol.',
                ]);
            }

            // Nilai header (diskon tambahan, pajak, dan biaya lain) dialokasikan
            // proporsional terhadap nilai item yang ditagihkan dari total SO.
            $salesOrderSubtotal = (float) $salesOrder->subtotal;
            $allocationRatio = $salesOrderSubtotal > 0
                ? min(1, $invoiceSubtotal / $salesOrderSubtotal)
                : 0;

            $headerDiscount = round((float) $salesOrder->discount_amount * $allocationRatio, 2);
            $taxAmount = round((float) $salesOrder->tax_amount * $allocationRatio, 2);
            $otherCharges = round((float) $salesOrder->other_charges * $allocationRatio, 2);
            $grandTotal = max(0, round($invoiceSubtotal - $headerDiscount + $taxAmount + $otherCharges, 2));

            if ($grandTotal <= 0) {
                throw ValidationException::withMessages([
                    'items' => 'Total invoice setelah perhitungan harus lebih dari nol.',
                ]);
            }

            $invoiceNumber = $this->nextInvoiceNumber((string) $data['invoice_date']);

            $invoice = Invoice::query()->create([
                'invoice_number' => $invoiceNumber,
                'sales_order_id' => $salesOrder->sales_order_id,
                'created_by' => $actor->user_id,
                'reference_number' => $salesOrder->reference_number ?: '-',
                'sales_name_snapshot' => $salesOrder->sales_name_snapshot,
                'bill_to_snapshot' => $salesOrder->sold_to_name_snapshot,
                'bill_to_address_snapshot' => $salesOrder->sold_to_address_snapshot,
                'ship_to_snapshot' => $salesOrder->ship_to_name_snapshot,
                'ship_to_address_snapshot' => $salesOrder->ship_to_address_snapshot,
                'contact_phone_snapshot' => $salesOrder->contact_phone_snapshot,
                'attention_snapshot' => $salesOrder->attention_snapshot,
                'subtotal' => $invoiceSubtotal,
                'discount_amount' => $headerDiscount,
                'tax_rate' => $salesOrder->tax_rate,
                'tax_amount' => $taxAmount,
                'other_charges' => $otherCharges,
                'grand_total' => $grandTotal,
                'payment_terms_snapshot' => $salesOrder->payment_terms_snapshot,
                'invoice_date' => $data['invoice_date'],
                'due_date' => $data['due_date'],
                'invoice_type' => $invoiceType,
                'status' => $data['due_date'] < now()->toDateString()
                    ? InvoiceStatus::OVERDUE
                    : InvoiceStatus::OUTSTANDING,
                'total_amount' => $grandTotal,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($invoiceLines as $line) {
                $invoice->items()->create($line);
            }

            $this->auditLog->record(
                $actor,
                'CREATE_INVOICE',
                'INVOICES',
                $invoice->invoice_id,
                'Invoice diterbitkan dari Sales Order ' . $salesOrder->so_number . '.',
                [],
                [
                    'invoice_number' => $invoice->invoice_number,
                    'sales_order_id' => (int) $salesOrder->sales_order_id,
                    'invoice_type' => $invoiceType,
                    'grand_total' => $grandTotal,
                    'item_count' => count($invoiceLines),
                ]
            );

            return $invoice->load(['items', 'createdBy']);
        }, 3);
    }

    private function nextInvoiceNumber(string $invoiceDate): string
    {
        $year = date('Y', strtotime($invoiceDate));
        $prefix = 'INV-' . $year . '-';

        // invoice_number memiliki unique index. Baris prefix tahun dikunci selama
        // transaksi untuk mengurangi risiko nomor yang sama pada request paralel.
        $existingNumbers = DB::table('invoices')
            ->where('invoice_number', 'like', $prefix . '%')
            ->lockForUpdate()
            ->pluck('invoice_number');

        $lastNumber = 0;
        foreach ($existingNumbers as $existingNumber) {
            if (preg_match('/^INV-' . preg_quote($year, '/') . '-(\d+)$/', $existingNumber, $matches)) {
                $lastNumber = max($lastNumber, (int) $matches[1]);
            }
        }

        return $prefix . str_pad((string) ($lastNumber + 1), 3, '0', STR_PAD_LEFT);
    }
}
