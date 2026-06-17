<?php

namespace App\Jobs;

use App\Enums\ChallengeStatus;
use App\Models\Challenge;
use App\Notifications\ChallengeExpiringNotification;
use App\Services\ChallengeEscrowService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class ChallengeOfferExpiredJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $challengeId) {}

    /**
     * Expire an unaccepted offer past its window and refund the challenger.
     */
    public function handle(ChallengeEscrowService $escrow): void
    {
        $expired = DB::transaction(function () use ($escrow) {

            $challenge = Challenge::lockForUpdate()->find($this->challengeId);

            if (! $challenge) {
                return null;
            }

            $stillOpen = in_array($challenge->status, [
                ChallengeStatus::PENDING,
                ChallengeStatus::OFFERED,
            ], true);

            if (! $stillOpen) {
                return null;
            }

            if ($challenge->offer_expires_at && $challenge->offer_expires_at->isFuture()) {
                return null;
            }

            $escrow->refund($challenge->challenger_id, (float) $challenge->amount, $challenge);

            $challenge->update(['status' => ChallengeStatus::EXPIRED]);

            return $challenge;
        });

        if ($expired) {
            $expired->challenger?->notify(new ChallengeExpiringNotification($expired));
        }
    }
}
