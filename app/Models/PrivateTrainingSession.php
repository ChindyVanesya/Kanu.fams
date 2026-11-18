<?php

namespace App\Models;

use Database\Factories\PrivateTrainingSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['opened_by', 'training_date', 'title', 'notes', 'price_per_member', 'status', 'opened_at', 'closed_at'])]
class PrivateTrainingSession extends Model
{
    /** @use HasFactory<PrivateTrainingSessionFactory> */
    use HasFactory;

    /** @return BelongsTo<User, $this> */
    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    /** @return HasMany<PrivateTrainingParticipant, $this> */
    public function participants(): HasMany
    {
        return $this->hasMany(PrivateTrainingParticipant::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'training_date' => 'date:Y-m-d',
            'price_per_member' => 'integer',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }
}
