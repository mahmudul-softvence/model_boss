<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ChallengeSubmission extends Model
{
    protected $fillable = [
        'challenge_id',
        'user_id',
        'submission_type',
        'notes',
        'evidence_image',
        'evidence_video',
    ];

    public function challenge(): BelongsTo
    {
        return $this->belongsTo(Challenge::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getEvidenceImageAttribute($value): ?string
    {
        return $value ? Storage::url($value) : null;
    }

    public function getEvidenceVideoAttribute($value): ?string
    {
        return $value ? Storage::url($value) : null;
    }
}
