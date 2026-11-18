<?php

namespace App\Models;

use Database\Factories\MemberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['member_code', 'name', 'birth_date', 'phone', 'has_jersey', 'status'])]
class Member extends Model
{
    /** @use HasFactory<MemberFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'birth_date' => 'date:Y-m-d',
            'has_jersey' => 'boolean',
        ];
    }

    /** @return HasMany<Attendance, $this> */
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    /** @return HasMany<RegistrationFeePayment, $this> */
    public function registrationFeePayments(): HasMany
    {
        return $this->hasMany(RegistrationFeePayment::class);
    }

    /** @return HasMany<PrivateTrainingParticipant, $this> */
    public function privateTrainingParticipations(): HasMany
    {
        return $this->hasMany(PrivateTrainingParticipant::class);
    }
}
