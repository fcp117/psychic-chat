<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'username', 'email', 'password', 'birthdate'])]
#[Hidden(['password', 'remember_token', 'profile_photo_path', 'birthdate'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $appends = ['profile_photo_url'];

    public function getProfilePhotoUrlAttribute(): ?string
    {
        return $this->profile_photo_path ? '/members/'.$this->id.'/photo?v='.substr(hash('sha256', $this->profile_photo_path), 0, 16) : null;
    }

    public function sendEmailVerificationNotification() { app(\App\Services\EmailVerificationCodes::class)->send($this); }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    // One whole credit is 3600 integer units: hourly billing stays exact per second.
    public function getAvailableCreditsAttribute($value) { return ($this->attributes['credit_units'] ?? 0) / 3600; }
    public function setAvailableCreditsAttribute($value) {
        $this->attributes['credit_units'] = (int) round($value * 3600);
        $this->attributes['available_credits'] = (int) floor($value);
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'birthdate' => 'date:Y-m-d',
            'password' => 'hashed',
            'credit_units' => 'integer', 'rate_per_hour' => 'integer',
            'is_approved' => 'boolean', 'is_suspended' => 'boolean',
        ];
    }
}
