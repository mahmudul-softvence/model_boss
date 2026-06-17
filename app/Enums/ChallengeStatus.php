<?php

namespace App\Enums;

enum ChallengeStatus: string
{
    case PENDING = 'pending';
    case OFFERED = 'offered';
    case ACCEPTED = 'accepted';
    case REJECTED = 'rejected';
    case DECLINED = 'declined';
    case CANCELLED = 'cancelled';
    case EXPIRED = 'expired';
    case COMPLETED = 'completed';
}
