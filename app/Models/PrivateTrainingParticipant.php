<?php

namespace App\Models;

use Database\Factories\PrivateTrainingParticipantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['private_training_session_id', 'member_id', 'attended_at'])]
class PrivateTrainingParticipant extends Model
{
    /** @use HasFactory<PrivateTrainingParticipantFactory> */
    use HasFactory;

    /** @return BelongsTo<PrivateTrainingSession, $this> */
    public function privateTrainingSession(): BelongsTo
    {
        return $this->belongsTo(PrivateTrainingSession::class);
    }

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /** @return HasMany<PrivateTrainingPayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(PrivateTrainingPayment::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['attended_at' => 'datetime'];
    }
}
