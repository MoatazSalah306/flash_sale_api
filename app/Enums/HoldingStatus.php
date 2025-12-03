<?php

namespace App\Enums;

enum HoldingStatus: string
{
    use EnumHelpers;


    case ACTIVE = 'Active';
    case USED = 'Used';
    case EXPIRED = 'Expired';
}
