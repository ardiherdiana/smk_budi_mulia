<?php

namespace App\Models\Classroom;

use App\Models\Guru;
use App\Models\Principal;
use App\Models\Staff;
use App\Models\User;
use App\Services\Classroom\GuruStaffSync;
use App\Services\MediaService;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

/**
 * Profil sekolah untuk satu baris `users` (tabel produksi yang skemanya dikunci). `id` SAMA dengan `users.id`.
 * Username, nama, jabatan, dan peran (ADMIN|KEPSEK|GURU|SISWA, plus STAF untuk pegawai yang hanya memakai SPPD) ada
 * di `profil_akun`; sandi tetap di `users.password`, dan atribut `password` di model ini meneruskannya ke sana, jadi
 * `forceFill([... 'password' => ...])->save()` tetap bekerja. Peran ADMIN selalu berpasangan dengan `users.role` admin.
 */
#[Fillable(['username', 'name', 'role', 'is_active'])]
#[Hidden(['remember_token'])]
class Akun extends Model
{
    protected $table = 'profil_akun';

    public $incrementing = false;

    protected $keyType = 'int';

    private ?string $sandiBaru = null;

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'guruStaffId' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function getCreatedAtColumn(): string
    {
        return 'createdAt';
    }

    public function getUpdatedAtColumn(): string
    {
        return 'updatedAt';
    }

    protected static function booted(): void
    {
        // Baris `users` dibuat lebih dulu (id-nya dipakai profil). Email sintetis hanya memenuhi kolom NOT NULL
        // tabel produksi; login staf/siswa memakai username.
        static::creating(function (Akun $akun) {
            if ($akun->id) {
                return;
            }

            $pengguna = new User;
            $pengguna->forceFill([
                'email' => Str::limit($akun->username.'@akun.local', 100, ''),
                'password' => $akun->sandiBaru ?? Str::random(40),
                'role' => $akun->role === 'ADMIN' ? 'admin' : 'user',
            ])->save();

            $akun->sandiBaru = null;
            $akun->id = $pengguna->id;
        });

        static::saved(function (Akun $akun) {
            $pengguna = User::find($akun->id);
            if (! $pengguna) {
                return;
            }

            if ($akun->sandiBaru !== null) {
                $pengguna->password = $akun->sandiBaru;
                $akun->sandiBaru = null;
            }
            if (str_ends_with((string) $pengguna->email, '@akun.local')) {
                $pengguna->email = Str::limit($akun->username.'@akun.local', 100, '');
            }
            // Satu admin untuk semua modul: peran profil ADMIN <=> users.role admin.
            if ($akun->wasRecentlyCreated || $akun->wasChanged('role')) {
                $pengguna->role = $akun->role === 'ADMIN' ? 'admin' : 'user';
            }
            if ($pengguna->isDirty()) {
                $pengguna->save();
            }
        });

        static::deleted(fn (Akun $akun) => User::whereKey($akun->id)->delete());
    }

    /** Hash sandi dari `users` (untuk Hash::check). */
    public function getPasswordAttribute(): ?string
    {
        return $this->pengguna?->password;
    }

    public function setPasswordAttribute(?string $value): void
    {
        $this->sandiBaru = $value;
    }

    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id');
    }

    /** Data guru absensi (QR, no. HP, mapel); hanya akun GURU/KEPSEK. */
    public function guru(): HasOne
    {
        return $this->hasOne(Guru::class, 'userId');
    }

    /** Orang ini di `guru_staff` (yang tampil di #guru di website); null bila tidak ditautkan. */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'guruStaffId');
    }

    /**
     * URL foto seperti yang tampil di website: `guru_staff.image` untuk guru yang ditautkan. Kepala sekolah tidak punya
     * baris `guru_staff` (tampil di bagian Kepala Sekolah), jadi memakai fotonya di `principals` yang namanya sama.
     * Null bila tidak ada foto.
     */
    public function fotoUrl(): ?string
    {
        $path = $this->staff?->image ?: ($this->role === 'KEPSEK' ? $this->kepalaSekolahDiWebsite()?->foto : null);

        return $path ? app(MediaService::class)->url($path) : null;
    }

    /** Baris kepala sekolah di website (`principals`) yang namanya sama dengan akun ini (gelar diabaikan); yang terbaru. */
    public function kepalaSekolahDiWebsite(): ?Principal
    {
        if (! $this->name) {
            return null;
        }

        $kunci = GuruStaffSync::kunciNama($this->name);

        return Principal::orderByDesc('id')->get(['id', 'nama', 'foto'])
            ->first(fn (Principal $p) => GuruStaffSync::kunciNama($p->nama) === $kunci);
    }

    /**
     * Profil Classroom milik pengguna yang sedang masuk. Admin lama (users.role = admin) yang belum punya profil
     * dibuatkan otomatis; pengguna portal lain (siswa/alumni tanpa data Classroom) tidak punya akses, hasilnya null.
     */
    public static function untukPengguna(User $pengguna): ?self
    {
        $akun = static::find($pengguna->id);
        if ($akun || ! $pengguna->isAdmin()) {
            return $akun;
        }

        // Username dari awalan email; bila sudah dipakai profil lain (mis. admin hasil sinkron absensi), tambahkan id.
        $username = Str::limit(Str::before((string) $pengguna->email, '@') ?: 'admin', 90, '');
        if (static::where('username', $username)->exists()) {
            $username .= '-'.$pengguna->id;
        }

        $akun = new static;
        $akun->forceFill([
            'id' => $pengguna->id,
            'username' => $username,
            'name' => 'Admin',
            'role' => 'ADMIN',
            'is_active' => true,
        ]);
        $akun->saveQuietly();

        return $akun;
    }

    public function siswa(): HasOne
    {
        return $this->hasOne(Siswa::class, 'userId');
    }

    /** Rombel yang dia walikelasi. */
    public function rombelWali(): HasMany
    {
        return $this->hasMany(Rombel::class, 'waliKelasId');
    }

    /** Ruang kelas yang dia ajar. */
    public function kelasDiajar(): HasMany
    {
        return $this->hasMany(Kelas::class, 'guruId');
    }

    public function isStaf(): bool
    {
        return in_array($this->role, ['ADMIN', 'KEPSEK', 'GURU'], true);
    }

    public function isGuru(): bool
    {
        return $this->role === 'GURU';
    }

    public function isSiswa(): bool
    {
        return $this->role === 'SISWA';
    }

    public function isAdmin(): bool
    {
        return $this->role === 'ADMIN';
    }

    /** Nama yang tampil di antarmuka. */
    public function namaTampil(): string
    {
        return $this->name ?: ($this->role === 'ADMIN' ? 'Admin' : $this->username);
    }
}
