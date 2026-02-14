<?php

namespace App\Enums;

enum TransactionType: string
{
    case RECHARGE = 'recharge';
    case SUPPORT  = 'support';
    case WIN      = 'win';
    case LOSS     = 'loss';
    case WITHDRAW = 'withdraw';
}
