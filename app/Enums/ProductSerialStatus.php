<?php

namespace App\Enums;

enum ProductSerialStatus: string
{
    case InStock = 'In Stock';
    case Issued = 'Issued';
}
