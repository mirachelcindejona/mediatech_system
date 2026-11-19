<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case PENDING_VALIDATION = 'Pending Validation';
    case VALIDATED = 'Validated';
    case REJECTED = 'Rejected';

    public function label(): string
    {
        return $this->value;
    }
}
