<?php

namespace App\Enums;

enum OrderStatus: string
{
    use EnumHelpers;

    case PENDING = 'Pending';
    case PAID = 'Paid';
    case CANCELLED = 'Cancelled';
}
