<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class HardenUsers extends Command
{
    protected $signature = 'users:harden {--dry-run : Hanya tampilkan yang akan diubah}';

    protected $description = 'Kosongkan users.password_plain dan hash ulang password yang tersimpan mentah di kolom password';

    public function handle(): int
    {
        $plainColumn = DB::table('users')->whereNotNull('password_plain')->where('password_plain', '<>', '')->count();

        $legacy = User::query()
            ->where('password', 'not like', '$2y$%')
            ->where('password', 'not like', '$2a$%')
            ->where('password', 'not like', '$2b$%')
            ->get();

        $this->line("password_plain terisi : {$plainColumn} baris");
        $this->line("password belum di-hash : {$legacy->count()} akun");

        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }

        foreach ($legacy as $user) {
            $user->forceFill(['password' => $user->password])->save(); // cast 'hashed'
        }
        DB::table('users')->update(['password_plain' => null]);

        $this->info('Selesai: tidak ada lagi password mentah di database.');

        return self::SUCCESS;
    }
}
