<?php

namespace App\Models;

use Database\Factories\TrainingSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['opened_by', 'training_date', 'title', 'notes', 'status', 'opened_at', 'closed_at', 'contribution_amount', 'evaluation', 'training_focus', 'training_program'])]
class TrainingSession extends Model
{
    /** @use HasFactory<TrainingSessionFactory> */
    use HasFactory;

    /** @return BelongsTo<User, $this> */
    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    /** @return HasMany<Attendance, $this> */
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'training_date' => 'date',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'contribution_amount' => 'integer',
        ];
    }
}
