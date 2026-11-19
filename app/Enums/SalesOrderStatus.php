<?php

namespace App\Enums;

enum SalesOrderStatus: string
{
    case Open = 'Open';
    case Processing = 'Processing';
    case Completed = 'Completed';
    case Cancelled = 'Cancelled';
}
