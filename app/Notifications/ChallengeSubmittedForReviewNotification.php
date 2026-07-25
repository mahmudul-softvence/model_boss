<?php

namespace App\Notifications;

use App\Models\Challenge;
use App\Models\ChallengeSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class ChallengeSubmittedForReviewNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected Challenge $challenge,
        protected ChallengeSubmission $submission,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toDatabase(object $notifiable): array
    {
        return $this->payload();
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->payload());
    }

    public function broadcastType(): string
    {
        return 'admin.challenge.submitted';
    }

    protected function payload(): array
    {
        $player = $this->submission->user;

        return [
            'type' => 'admin.challenge.submitted',
            'challenge_id' => $this->challenge->id,
            'challenge_no' => $this->challenge->challenge_no,
            'submission_id' => $this->submission->id,
            'submission_type' => $this->submission->submission_type,
            'player_id' => $player?->id,
            'player_name' => $player?->artist_name ?: $player?->name,
            'has_evidence_image' => $this->submission->getRawOriginal('evidence_image') !== null,
            'has_evidence_video' => $this->submission->getRawOriginal('evidence_video') !== null,
            'message' => 'A challenge result has been submitted for your review.',
        ];
    }
}
