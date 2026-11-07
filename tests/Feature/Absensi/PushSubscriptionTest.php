<?php

namespace Tests\Feature\Absensi;

use Tests\AbsensiTestCase;

class PushSubscriptionTest extends AbsensiTestCase
{
    public function test_user_can_subscribe_to_push_notifications(): void
    {
        $user = $this->actingAsAdmin();

        $response = $this->postJson('/push/subscribe', [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/example',
            'keys' => ['p256dh' => 'p256dh-key', 'auth' => 'auth-key'],
        ]);

        $response->assertNoContent();
        $this->assertDatabaseHas('push_subscriptions', [
            'userId' => $user->id,
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/example',
        ]);
    }

    public function test_user_can_unsubscribe_from_push_notifications(): void
    {
        $user = $this->actingAsAdmin();
        $this->postJson('/push/subscribe', [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/example',
            'keys' => ['p256dh' => 'p256dh-key', 'auth' => 'auth-key'],
        ])->assertNoContent();

        $response = $this->postJson('/push/unsubscribe', [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/example',
        ]);

        $response->assertNoContent();
        $this->assertDatabaseMissing('push_subscriptions', [
            'userId' => $user->id,
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/example',
        ]);
    }

    public function test_vapid_public_key_endpoint_is_reachable_without_auth(): void
    {
        $response = $this->getJson('/push/vapid-public-key');

        $response->assertOk();
        $response->assertJsonStructure(['publicKey']);
    }

    public function test_guest_cannot_subscribe(): void
    {
        $response = $this->postJson('/push/subscribe', [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/example',
            'keys' => ['p256dh' => 'p256dh-key', 'auth' => 'auth-key'],
        ]);

        $response->assertStatus(401);
    }
}
