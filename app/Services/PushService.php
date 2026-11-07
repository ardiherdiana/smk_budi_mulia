<?php

namespace App\Services;

use App\Models\PushSubscription;

class PushService
{
    public function subscribe(string $userId, string $endpoint, string $p256dh, string $auth): PushSubscription
    {
        $existing = PushSubscription::where('userId', $userId)->where('endpoint', $endpoint)->first();
        if ($existing) {
            return $existing;
        }

        return PushSubscription::create([
            'userId' => $userId,
            'endpoint' => $endpoint,
            'p256dh' => $p256dh,
            'auth' => $auth,
        ]);
    }

    public function unsubscribe(string $userId, string $endpoint): void
    {
        PushSubscription::where('userId', $userId)->where('endpoint', $endpoint)->delete();
    }
}
