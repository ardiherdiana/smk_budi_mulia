<?php

namespace App\Services;

use App\Models\PushSubscription;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class WebPushService
{
    private ?WebPush $webPush = null;

    private bool $configured;

    public function __construct()
    {
        $this->configured = filled(config('services.vapid.public_key')) && filled(config('services.vapid.private_key'));

        if ($this->configured) {
            $this->webPush = new WebPush([
                'VAPID' => [
                    'subject' => config('services.vapid.subject'),
                    'publicKey' => config('services.vapid.public_key'),
                    'privateKey' => config('services.vapid.private_key'),
                ],
            ]);
        }
    }

    /** Sends a push notification to every subscription a user has (they may
     * have several - one per browser/device). A subscription the push
     * service reports as gone (410) or not found (404) is deleted so it
     * stops being retried on the next reminder tick. */
    public function sendPushToUser(string $userId, string $title, string $body, ?string $url = null): void
    {
        if (! $this->configured) {
            return;
        }

        $subscriptions = PushSubscription::where('userId', $userId)->get();
        if ($subscriptions->isEmpty()) {
            return;
        }

        $payload = json_encode(['title' => $title, 'body' => $body, 'url' => $url]);

        foreach ($subscriptions as $sub) {
            try {
                $report = $this->webPush->sendOneNotification(
                    Subscription::create(['endpoint' => $sub->endpoint, 'keys' => ['p256dh' => $sub->p256dh, 'auth' => $sub->auth]]),
                    $payload,
                );

                if (! $report->isSuccess() && $report->isSubscriptionExpired()) {
                    $sub->delete();
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }
}
