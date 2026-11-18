<?php

namespace App\Models;

use Database\Factories\PrivateTrainingPaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['private_training_participant_id', 'amount', 'paid_at', 'recorded_by'])]
class PrivateTrainingPayment extends Model
{
    /** @use HasFactory<PrivateTrainingPaymentFactory> */
    use HasFactory;

    /** @return BelongsTo<PrivateTrainingParticipant, $this> */
    public function participant(): BelongsTo
    {
        return $this->belongsTo(PrivateTrainingParticipant::class, 'private_training_participant_id');
    }

    /** @return BelongsTo<User, $this> */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'paid_at' => 'datetime',
        ];
    }
}
