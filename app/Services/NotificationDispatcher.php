<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\GeneralNotification;
use Illuminate\Support\Facades\DB;

class NotificationDispatcher
{
    /** A user row serializes concurrent sends; read notifications still count for deduplication. */
    public static function once(User $recipient, string $key, array $payload): bool
    {
        return DB::transaction(function () use ($recipient, $key, $payload) {
            $recipient = User::whereKey($recipient->id)->lockForUpdate()->firstOrFail();
            if ($recipient->notifications()->where('data->dedup_key', $key)->exists()) {
                return false;
            }
            $recipient->notify(new GeneralNotification($payload + ['dedup_key' => $key]));

            return true;
        }, 3);
    }
}
