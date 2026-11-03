<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Tabel `users` produksi: email + password + role (admin|user), tanpa
 * timestamps dan tanpa kolom remember_token. Kolom `password_plain`,
 * `nama_file`, `path_file` ada di skema lama tapi TIDAK dipakai lagi -
 * aplikasi tidak pernah menulis password mentah (lihat `users:scrub-plain-passwords`).
 */
#[Fillable(['email', 'password', 'role'])]
#[Hidden(['password', 'password_plain'])]
class User extends Authenticatable
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    /** Skema produksi tidak punya remember_token; nonaktifkan fitur "ingat saya". */
    public function getRememberTokenName(): string
    {
        return '';
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function files(): HasMany
    {
        return $this->hasMany(PortalFile::class, 'user_id');
    }
}
