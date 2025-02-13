<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserNotificationRecipient extends Model
{
    protected $fillable = ['name', 'email', 'type', 'active'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function getActiveRecipients(int $userId, string $type = 'settlement_report'): array
    {
        return static::where('user_id', $userId)
            ->where('type', $type)
            ->where('active', true)
            ->pluck('email')
            ->toArray();
    }
}
