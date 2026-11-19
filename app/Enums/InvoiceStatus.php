<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case OUTSTANDING = 'Outstanding';
    case PARTIALLY_PAID = 'Partially Paid';
    case PAID = 'Paid';
    case OVERDUE = 'Overdue';

    public function label(): string
    {
        return match ($this) {
            self::OUTSTANDING => 'Outstanding',
            self::PARTIALLY_PAID => 'Partially Paid',
            self::PAID => 'Paid',
            self::OVERDUE => 'Overdue',
        };
    }
}
