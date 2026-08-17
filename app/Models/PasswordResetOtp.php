<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PasswordResetOtp extends Model
{
    use HasUuids;

    protected $fillable = [
        'email',
        'otp_hash',
        'expires_at',
        'attempts',
        'consumed_at',
        'reset_token_hash',
        'reset_token_expires_at',
        'reset_token_consumed_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'attempts' => 'integer',
            'consumed_at' => 'datetime',
            'reset_token_expires_at' => 'datetime',
            'reset_token_consumed_at' => 'datetime',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isConsumed(): bool
    {
        return $this->consumed_at !== null;
    }

    public function hasExceededAttempts(): bool
    {
        return $this->attempts >= 5;
    }

    public function isResetTokenValid(): bool
    {
        return $this->reset_token_hash !== null
            && $this->reset_token_consumed_at === null
            && $this->reset_token_expires_at !== null
            && $this->reset_token_expires_at->isFuture();
    }
}
