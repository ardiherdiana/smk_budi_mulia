<?php

use App\Models\LogAktivitas;
use App\Models\Notifikasi;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Modul absensi. Butuh cron hosting `* * * * * php artisan schedule:run`.
Schedule::command('attendance:send-reminders')->everyMinute();

// Notifikasi semua modul dan log aktivitas dihapus 30 hari setelah dibuat (Notifikasi::prunable,
// LogAktivitas::prunable). Tiap menit supaya waktu hapusnya tepat (dibuat 1 Nov 13.39 -> hilang 1 Des 13.39), bukan
// menunggu batch harian. Riwayat persetujuan SPPD (`log_audits`) tidak ikut dihapus.
Schedule::command('model:prune', ['--model' => [Notifikasi::class, LogAktivitas::class]])->everyMinute();
