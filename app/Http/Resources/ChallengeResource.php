<?php

namespace App\Http\Resources;

use App\Enums\ChallengeStatus;
use App\Models\Challenge;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Challenge
 */
class ChallengeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'challenge_no' => $this->challenge_no,
            'rank' => $this->rank ?? null,
            'mode' => $this->mode?->value,
            'status' => $this->status?->value,
            'amount' => $this->amount,
            'matched_points' => $this->amount,
            'logo' => $this->logo,
            'memo' => $this->memo,
            'show_real_name' => $this->show_real_name,
            'duration_hours' => $this->duration_hours,
            'duration_label' => $this->duration_hours.' Hours',
            'match_date' => $this->match_date?->toDateString(),
            'match_time' => $this->match_time,
            'offer_expires_at' => $this->offer_expires_at?->toIso8601String(),
            'is_expired' => $this->isExpired(),
            'can_accept' => $this->status === ChallengeStatus::OFFERED && ! $this->isExpired(),
            'expiry_message' => $this->isExpired() ? 'This challenge offer has expired.' : null,
            'game' => $this->whenLoaded('game', fn () => [
                'id' => $this->game?->id,
                'name' => $this->game?->name,
                'image' => $this->game?->image,
            ]),
            'challenger' => $this->playerPayload($this->whenLoaded('challenger') ? $this->challenger : null),
            'target_player' => $this->playerPayload($this->whenLoaded('targetPlayer') ? $this->targetPlayer : null),
            'acceptor' => $this->playerPayload($this->whenLoaded('acceptor') ? $this->acceptor : null),
            'winner_id' => $this->winner_id,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function playerPayload(?User $user): ?array
    {
        if (! $user) {
            return null;
        }

        $name = $user->artist_name ?: $user->name;

        return [
            'id' => $user->id,
            'name' => $name,
            'image' => $user->image_url,
        ];
    }
}
