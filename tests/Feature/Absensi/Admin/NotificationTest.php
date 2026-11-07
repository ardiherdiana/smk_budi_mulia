<?php

namespace Tests\Feature\Absensi\Admin;

use App\Models\Notification;
use Tests\AbsensiTestCase;

class NotificationTest extends AbsensiTestCase
{
    public function test_prune_deletes_a_notification_exactly_seven_days_after_it_was_created(): void
    {
        $this->freezeAt('2025-09-25 13:39:00');
        $notification = Notification::create(['type' => 'CHECKIN', 'judul' => 'Absen Masuk', 'pesan' => 'Test']);

        $this->freezeAt('2025-10-02 13:38:59');
        $this->artisan('model:prune', ['--model' => Notification::class])->assertSuccessful();
        $this->assertDatabaseHas('notifications', ['id' => $notification->id]);

        $this->freezeAt('2025-10-02 13:39:00');
        $this->artisan('model:prune', ['--model' => Notification::class])->assertSuccessful();
        $this->assertDatabaseMissing('notifications', ['id' => $notification->id]);
    }

    public function test_prune_removes_old_notifications_read_or_not_and_keeps_recent_ones(): void
    {
        $this->freezeAt('2025-09-20 08:00:00');
        $oldUnread = Notification::create(['type' => 'CHECKIN', 'judul' => 'A', 'pesan' => 'Test', 'isRead' => false]);
        $oldRead = Notification::create(['type' => 'CHECKOUT', 'judul' => 'B', 'pesan' => 'Test', 'isRead' => true]);

        $this->freezeAt('2025-09-27 08:00:00');
        $recent = Notification::create(['type' => 'CHECKIN', 'judul' => 'C', 'pesan' => 'Test']);

        $this->freezeAt('2025-09-28 08:00:00');
        $this->artisan('model:prune', ['--model' => Notification::class])->assertSuccessful();

        $this->assertDatabaseMissing('notifications', ['id' => $oldUnread->id]);
        $this->assertDatabaseMissing('notifications', ['id' => $oldRead->id]);
        $this->assertDatabaseHas('notifications', ['id' => $recent->id]);
    }

    public function test_prune_runs_every_minute_from_the_scheduler(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('model:prune')
            ->assertSuccessful();
    }
}
