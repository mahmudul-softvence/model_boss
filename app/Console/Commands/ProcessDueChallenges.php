<?php

namespace App\Console\Commands;

use App\Enums\ChallengeStatus;
use App\Jobs\ChallengeOfferExpiredJob;
use App\Models\Challenge;
use Illuminate\Console\Command;

class ProcessDueChallenges extends Command
{
    protected $signature = 'challenges:process-due';

    protected $description = 'Expire and refund unaccepted challenge offers past their window.';

    public function handle(): int
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

        return self::SUCCESS;
    }
}
