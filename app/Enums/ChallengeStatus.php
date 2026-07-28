<?php

namespace App\Enums;

enum ChallengeStatus: string
{
    case PENDING = 'pending';
    case OFFERED = 'offered';
    case ACCEPTED = 'accepted';
    case UNDER_REVIEW = 'under_review';
    case REJECTED = 'rejected';
    case DECLINED = 'declined';
    case CANCELLED = 'cancelled';
    case EXPIRED = 'expired';
    case WINNER_PENDING = 'winner_pending';
    case COMPLETED = 'completed';
}
