<?php

namespace App\Enums;

enum StockMovementType: string
{
    case GoodsIn = 'GOODS_IN';
    case GoodsOut = 'GOODS_OUT';
    case AdjustmentIn = 'ADJUSTMENT_IN';
    case AdjustmentOut = 'ADJUSTMENT_OUT';
    case OpeningBalance = 'OPENING_BALANCE';
    case ReversalIn = 'REVERSAL_IN';
    case ReversalOut = 'REVERSAL_OUT';

    public static function balanceSql(string $alias = 'stock_movements'): string
    {
        return "COALESCE(SUM(CASE WHEN {$alias}.movement_type IN ('GOODS_IN','ADJUSTMENT_IN','OPENING_BALANCE','REVERSAL_IN') THEN {$alias}.quantity WHEN {$alias}.movement_type IN ('GOODS_OUT','ADJUSTMENT_OUT','REVERSAL_OUT') THEN -{$alias}.quantity ELSE 0 END), 0)";
    }
}
