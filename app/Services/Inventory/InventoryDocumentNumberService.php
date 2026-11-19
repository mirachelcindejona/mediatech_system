<?php

namespace App\Services\Inventory;

use App\Models\Inventory\GoodsIssue;
use App\Models\Inventory\GoodsReceipt;
use Illuminate\Database\UniqueConstraintViolationException;

class InventoryDocumentNumberService
{
    public function assign(GoodsReceipt|GoodsIssue $document): string
    {
        $receipt = $document instanceof GoodsReceipt;
        $column = $receipt ? 'receipt_number' : 'goods_issue_number';
        $prefix = $receipt ? 'GR' : 'GO';
        $year = $receipt ? $document->receipt_date->year : $document->created_at->timezone('Asia/Jakarta')->year;
        $sequence = $document->getKey();
        while (true) {
            $number = $prefix.'-'.$year.'-'.str_pad((string) $sequence++, $receipt ? 3 : 4, '0', STR_PAD_LEFT);
            try {
                $document->update([$column => $number]);

                return $number;
            } catch (UniqueConstraintViolationException) {
            }
        }
    }
}
