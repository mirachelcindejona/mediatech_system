<?php

namespace App\Enums;

enum GoodsIssueStatus: string
{
    case Draft = 'Draft';
    case Posted = 'Posted';
    case Reversed = 'Reversed';
}
