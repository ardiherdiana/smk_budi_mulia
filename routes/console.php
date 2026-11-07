<?php

use App\Models\Notification;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Modul absensi. Butuh cron hosting `* * * * * php artisan schedule:run`.
Schedule::command('attendance:send-reminders')->everyMinute();

// Notifikasi absensi dihapus 7 hari setelah dibuat (lihat Notification::prunable()). Tiap menit supaya
// waktu hapusnya tepat (dibuat 25 Sep 13.39 -> hilang 2 Okt 13.39), bukan menunggu batch harian.
Schedule::command('model:prune', ['--model' => Notification::class])->everyMinute();
