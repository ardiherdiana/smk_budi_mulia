<?php

namespace App\Models;

use App\Models\Classroom\Akun;
use App\Models\Classroom\Kelas;
use App\Models\Classroom\Post as ClassroomPost;
use App\Models\Sppd\PengajuanSppd;
use App\Models\Supervisi\Supervisi;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Traits\HasRoles;

/**
 * Tabel `users` produksi: email + password + role (admin|user), tanpa timestamps dan tanpa kolom remember_token.
 * Kolom `password_plain`, `nama_file`, `path_file` ada di skema lama tapi TIDAK dipakai lagi - aplikasi tidak pernah
 * menulis password mentah (lihat `users:harden`).
 *
 * Sejak absensi digabung, profil sekolah (username, nama, jabatan, status aktif, dan peran ADMIN|KEPSEK|GURU|SISWA|STAF)
 * ada di `profil_akun` (model `Akun`, `id` sama). Model ini meneruskannya lewat atribut virtual `username`, `name`,
 * `jabatan`, `is_active`, dan `peran`: membaca dari profil, dan menulis ke profil setelah baris `users` tersimpan,
 * sehingga `User::create(['username' => ..., 'peran' => 'GURU'])` tetap satu langkah. Jangan memakai atribut virtual itu
 * di query SQL (`where('username', ...)`); pakai scope `denganUsername()` / `pegawaiAktif()` atau `whereHas('akun')`.
 *
 * `role` tetap kolom produksi (admin|user) dan menentukan admin satu untuk semua modul; peran profil ADMIN selalu
 * berpasangan dengan `role` admin (disinkronkan di sini dan di `Akun::saved`).
 */
#[Fillable(['email', 'password', 'role', 'username', 'name', 'jabatan', 'is_active', 'peran'])]
#[Hidden(['password', 'password_plain', 'akun'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    public const PERAN_STAF = ['ADMIN', 'KEPSEK', 'GURU', 'STAF'];

    public $timestamps = false;

    /** Ikut terserialisasi seperti kolom `users` absensi dulu (props Inertia modul SPPD membaca pemohon.name, dst.). */
    protected $appends = ['username', 'name', 'jabatan', 'is_active'];

    /** @var array<string, mixed> Isian profil yang menunggu disimpan ke `profil_akun` (kunci = kolom profil). */
    private array $profilTertunda = [];

    protected static function booted(): void
    {
        static::saving(function (User $user) {
            // Akun buatan modul absensi/SPPD tidak punya email: kolom NOT NULL produksi diisi email sintetis seperti Akun.
            if (blank($user->email) && isset($user->profilTertunda['username'])) {
                $user->email = Str::limit($user->profilTertunda['username'].'@akun.local', 100, '');
            }

            if (array_key_exists('role', $user->profilTertunda)) {
                $user->role = $user->profilTertunda['role'] === 'ADMIN' ? 'admin' : 'user';
            } elseif (blank($user->role)) {
                $user->role = 'user';
            }
        });

        static::saved(fn (User $user) => $user->simpanProfil());
    }

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    // ---- Profil (`profil_akun`) -------------------------------------------------------------------------------

    public function akun(): HasOne
    {
        return $this->hasOne(Akun::class, 'id', 'id');
    }

    protected function username(): Attribute
    {
        return $this->atributProfil('username');
    }

    protected function name(): Attribute
    {
        return $this->atributProfil('name');
    }

    protected function jabatan(): Attribute
    {
        return $this->atributProfil('jabatan');
    }

    /** Status aktif profil. Akun tanpa profil (admin/portal lama) dianggap aktif. */
    protected function isActive(): Attribute
    {
        return Attribute::make(
            get: fn () => (bool) ($this->nilaiProfil('is_active') ?? true),
            set: fn ($value) => $this->tunda('is_active', (bool) $value),
        );
    }

    /** Peran sekolah ADMIN|KEPSEK|GURU|SISWA|STAF dari profil; null bila akun tidak punya profil. NULL ditulis sebagai STAF. */
    protected function peran(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->nilaiProfil('role'),
            set: fn ($value) => $this->tunda('role', $value ?: 'STAF'),
        );
    }

    /** Staf sekolah (bukan siswa dan bukan akun portal tanpa profil). Admin selalu dihitung staf. */
    public function isStaf(): bool
    {
        return $this->isAdmin() || in_array($this->peran, self::PERAN_STAF, true);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Modul yang masih menyimpan data milik akun ini. Menghapus baris `users` men-cascade data di modul lain
     * (riwayat kehadiran lewat `guru.userId`, pengajuan SPPD, profil Classroom), jadi tiap halaman hapus akun menolak
     * bila ada modul LAIN di daftar ini dan menyarankan nonaktifkan.
     *
     * @return list<'Absensi'|'SPPD'|'Classroom'|'Supervisi'>
     */
    public function modulTerpakai(): array
    {
        $id = $this->getKey();
        $modul = [];

        // Setiap akun guru/kepsek punya baris `guru` (lihat GuruService), jadi yang dihitung riwayatnya, bukan barisnya.
        $riwayatAbsensi = Guru::where('userId', $id)
            ->where(fn (Builder $q) => $q->has('attendances')->orHas('leaveRequests')->orHas('briefingAttendances'))
            ->exists();
        if ($riwayatAbsensi) {
            $modul[] = 'Absensi';
        }

        $sppd = PengajuanSppd::where('pemohon_id', $id)->orWhere('disetujui_oleh', $id)->exists()
            || DB::table('pengajuan_pengikut')->where('user_id', $id)->exists()
            || DB::table('sppds')->where('diterbitkan_oleh', $id)->exists()
            || DB::table('pencairans')->where('dicairkan_oleh', $id)->exists()
            || DB::table('laporan_perjalanans')->where('ditulis_oleh', $id)->exists();
        if ($sppd) {
            $modul[] = 'SPPD';
        }

        if (Kelas::where('guruId', $id)->exists() || ClassroomPost::where('dibuatOleh', $id)->exists()) {
            $modul[] = 'Classroom';
        }

        // `supervisi.guruId`/`supervisorId` RESTRICT: riwayat supervisi tidak ikut terhapus bersama akunnya.
        if (Supervisi::where('guruId', $id)->orWhere('supervisorId', $id)->exists()) {
            $modul[] = 'Supervisi';
        }

        return $modul;
    }

    /** Hentikan penghapusan (409) bila akun masih dipakai modul selain $modulIni. */
    public function cegahHapusLintasModul(string $modulIni): void
    {
        $lain = array_values(array_diff($this->modulTerpakai(), [$modulIni]));

        abort_if($lain !== [], 409, 'Akun ini masih punya data di modul '.implode(' dan ', $lain).'. Nonaktifkan saja, jangan dihapus.');
    }

    /** @param  Builder<User>  $query */
    #[Scope]
    protected function denganUsername(Builder $query, string $username): void
    {
        $query->whereHas('akun', fn (Builder $q) => $q->where('username', $username));
    }

    /**
     * Akun siswa/alumni untuk modul SKL: `role` user dan BUKAN staf sekolah. Guru, kepsek, dan pegawai SPPD juga
     * ber-`role` user di tabel produksi, jadi tanpa saringan ini mereka ikut muncul (dan bisa di-reset sandinya) di SKL.
     *
     * @param  Builder<User>  $query
     */
    #[Scope]
    protected function akunSkl(Builder $query): void
    {
        $query->where('role', 'user')
            ->whereDoesntHave('akun', fn (Builder $q) => $q->whereIn('role', self::PERAN_STAF));
    }

    /**
     * Pegawai yang dapat dipilih (mis. sebagai pengikut SPPD): profil aktif dengan minimal satu role SPPD.
     *
     * @param  Builder<User>  $query
     */
    #[Scope]
    protected function pegawaiAktif(Builder $query): void
    {
        $query->whereHas('akun', fn (Builder $q) => $q->where('is_active', true))->has('roles');
    }

    /**
     * Penerima notifikasi absensi (absen masuk/pulang, pengajuan izin): admin dan kepala sekolah yang aktif, sama dengan
     * NotifikasiTerpusat::bolehAbsensi(). Akun admin tanpa profil dianggap aktif.
     */
    #[Scope]
    protected function penerimaNotifikasiAbsensi(Builder $query): void
    {
        $query->where(fn (Builder $q) => $q->where('role', 'admin')
            ->orWhereHas('akun', fn (Builder $a) => $a->where('role', 'KEPSEK')))
            ->whereDoesntHave('akun', fn (Builder $a) => $a->where('is_active', false));
    }

    /**
     * Sengaja tanpa tipe kembalian `Attribute`: Eloquent menganggap setiap method bertipe kembalian Attribute sebagai
     * accessor (di sini "atribut_profil") dan memanggilnya tanpa argumen saat serialisasi.
     *
     * @return Attribute
     */
    private function atributProfil(string $kolom)
    {
        return Attribute::make(
            get: fn () => $this->nilaiProfil($kolom),
            set: fn ($value) => $this->tunda($kolom, $value),
        );
    }

    private function nilaiProfil(string $kolom): mixed
    {
        if (array_key_exists($kolom, $this->profilTertunda)) {
            return $this->profilTertunda[$kolom];
        }

        return $this->exists ? $this->akun?->getAttribute($kolom) : null;
    }

    /** @return array<string, never> Tidak ada yang ditulis ke kolom `users`. */
    private function tunda(string $kolom, mixed $nilai): array
    {
        $this->profilTertunda[$kolom] = $nilai;

        return [];
    }

    /** Tulis isian profil yang tertunda. Akun tanpa profil hanya dibuatkan profil bila username-nya diisi. */
    private function simpanProfil(): void
    {
        if ($this->profilTertunda === []) {
            return;
        }

        $isian = $this->profilTertunda;
        $this->profilTertunda = [];

        $akun = Akun::find($this->id);
        if (! $akun) {
            if (blank($isian['username'] ?? null)) {
                return;
            }

            $akun = new Akun;
            $akun->forceFill(['id' => $this->id, 'role' => $this->isAdmin() ? 'ADMIN' : 'STAF', 'is_active' => true]);
        }

        $akun->forceFill($isian)->save();
        $this->setRelation('akun', $akun);
    }

    // ---- Ingat saya: token di `profil_akun` (tabel `users` produksi tidak punya kolomnya) ----------------------

    /** Kosong supaya trait bawaan tidak pernah menulis kolom `users.remember_token` yang tidak ada. */
    public function getRememberTokenName(): string
    {
        return '';
    }

    public function getRememberToken(): ?string
    {
        return $this->akun?->remember_token;
    }

    public function setRememberToken($value): void
    {
        $this->akun?->forceFill(['remember_token' => $value])->saveQuietly();
    }

    // ---- Relasi modul ------------------------------------------------------------------------------------------

    public function files(): HasMany
    {
        return $this->hasMany(PortalFile::class, 'user_id');
    }

    public function guru(): HasOne
    {
        return $this->hasOne(Guru::class, 'userId');
    }

    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushSubscription::class, 'userId');
    }

    /**
     * Notifikasi semua modul (absensi, SPPD, Supervisi) di tabel bersama `notifikasi` (lihat App\Models\Notifikasi).
     *
     * @return MorphMany<Notifikasi, $this>
     */
    public function notifications(): MorphMany
    {
        return $this->morphMany(Notifikasi::class, 'notifiable')
            ->orderBy('created_at', 'desc');
    }

    /** @return HasMany<PengajuanSppd, $this> */
    public function pengajuanSppds(): HasMany
    {
        return $this->hasMany(PengajuanSppd::class, 'pemohon_id');
    }
}
