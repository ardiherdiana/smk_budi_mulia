<?php

namespace App\Models\Sppd;

use Illuminate\Notifications\DatabaseNotification;

/**
 * Laravel's stock notifications model, pointed at `sppd_notifications` — absensi's own
 * `notifications` table has an unrelated shape (see App\Models\Notification / NotificationService).
 */
class SppdNotification extends DatabaseNotification
{
    protected $table = 'sppd_notifications';
}
