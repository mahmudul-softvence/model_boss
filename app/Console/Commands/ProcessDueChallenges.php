<?php

namespace App\Console\Commands;

use App\Enums\ChallengeStatus;
use App\Jobs\ChallengeOfferExpiredJob;
use App\Models\Challenge;
use App\Models\User;
use App\Notifications\ChallengeWonNotification;
use App\Services\ChallengeSettlementService;
use Illuminate\Console\Command;

class ProcessDueChallenges extends Command
{
    protected $signature = 'challenges:process-due';

    protected $description = 'Expire unaccepted offers and auto-release payouts after the 2-day hold window.';

    public function handle(ChallengeSettlementService $settlement): int
    {
        $due = Challenge::query()
            ->whereIn('status', [
                ChallengeStatus::PENDING->value,
                ChallengeStatus::OFFERED->value,
            ])
            ->whereNotNull('offer_expires_at')
            ->where('offer_expires_at', '<=', now())
            ->pluck('id');

        foreach ($due as $challengeId) {
            ChallengeOfferExpiredJob::dispatch($challengeId);
        }

        $this->info("Dispatched {$due->count()} due challenge(s) for expiry.");

        $released = 0;

        $pendingPayouts = Challenge::query()
            ->where('status', ChallengeStatus::WINNER_PENDING)
            ->where('admin_reviewed_at', '<=', now()->subDays(2))
            ->get();

        foreach ($pendingPayouts as $challenge) {
            try {
                $result = $settlement->settle($challenge, $challenge->winner_id);

                $winner = User::find($challenge->winner_id);
                if ($winner) {
                    $winner->notify(new ChallengeWonNotification($challenge, (float) $result['winner_payout']));
                }

                $released++;
            } catch (\Throwable $e) {
                $this->error("Failed to release payout for challenge #{$challenge->id}: {$e->getMessage()}");
            }
        }

        if ($released > 0) {
            $this->info("Auto-released {$released} challenge payout(s) after 2-day hold.");
        }

        return self::SUCCESS;
    }
}
