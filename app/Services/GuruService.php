<?php

namespace App\Services;

use App\Models\Classroom\Akun;
use App\Models\Guru;
use App\Models\User;
use App\Services\Classroom\GuruStaffSync;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Data Guru absensi (`guru`: QR, no. HP, mapel, status). Isinya mengikuti akun di Data Guru Classroom: setiap akun
 * GURU/KEPSEK punya tepat satu baris `guru`, dan nama serta status aktif ditulis ke keduanya dari halaman mana pun, jadi
 * kedua daftar berisi orang, username, sandi, nama, dan status yang sama. Admin tidak punya baris `guru`.
 * Fotonya satu untuk semua: foto orangnya di website (lihat Guru::fotoTampil dan gantiFoto).
 */
class GuruService
{
    /** Peran akun yang wajib punya data guru absensi. */
    public const PERAN = ['GURU', 'KEPSEK'];

    public function __construct(private GuruStaffSync $website, private MediaService $media) {}

    /** Username is auto-derived from the teacher's own name (first two
     * words, lowercased, joined with no separator - e.g. "Rio Falentino,
     * S.Pd. Gr." -> "riofalentino") rather than a manually-assigned NIP,
     * since there's no other per-teacher identifier collected at creation
     * time. Suffixed with an incrementing number on collision. */
    private function baseUsernameFromNama(string $nama): string
    {
        $core = trim(explode(',', $nama)[0] ?? $nama);
        $words = array_slice(array_values(array_filter(preg_split('/\s+/', $core))), 0, 2);
        $slug = strtolower(implode('', $words));
        $slug = preg_replace('/[^a-z0-9]/', '', $slug);

        return $slug !== '' ? $slug : 'guru';
    }

    public function generateUniqueUsername(string $nama): string
    {
        $base = $this->baseUsernameFromNama($nama);
        $candidate = $base;
        $suffix = 2;
        // Username unik di seluruh profil sekolah (guru, pegawai SPPD, siswa Classroom).
        while (Akun::where('username', $candidate)->exists()) {
            $candidate = $base.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    /** Mirrors guru.service.ts's `guruSelect` exactly - {id, nama, noHp,
     * mapel, fotoUrl, qrToken, aktif, createdAt, user: {username, role}} -
     * notably WITHOUT userId or updatedAt (guru) or id (nested user). */
    private function present(Guru $guru): array
    {
        return [
            'id' => $guru->id,
            'nama' => $guru->nama,
            'noHp' => $guru->noHp,
            'mapel' => $guru->mapel,
            // Foto website (sama dengan Classroom), foto absen lama bila di website belum ada.
            'fotoUrl' => $guru->fotoTampil(),
            'qrToken' => $guru->qrToken,
            'aktif' => $guru->aktif,
            'createdAt' => $guru->createdAt,
            'user' => [
                'username' => $guru->user->username,
                'role' => $guru->user->peran,
            ],
        ];
    }

    public function list(?string $search = null): array
    {
        return Guru::with('user.akun.staff')
            ->when($search, fn ($q) => $q->where('nama', 'like', "%{$search}%"))
            ->orderBy('nama')
            ->get()
            ->map(fn (Guru $guru) => $this->present($guru))
            ->all();
    }

    public function find(string $id): array
    {
        $guru = Guru::with('user.akun')->find($id);
        if (! $guru) {
            abort(404, 'Guru tidak ditemukan');
        }

        return $this->present($guru);
    }

    public function create(array $input): array
    {
        $username = $this->generateUniqueUsername($input['nama']);

        // Nama profil = nama guru, supaya tampil benar di Classroom dan menu utama.
        $user = User::create([
            'username' => $username,
            'name' => $input['nama'],
            'password' => Hash::make($input['password']),
            'peran' => $input['role'] ?? 'GURU',
        ]);

        $guru = Guru::create([
            'userId' => $user->id,
            'nama' => $input['nama'],
            'noHp' => $input['noHp'] ?? null,
            'mapel' => $input['mapel'] ?? null,
            'fotoUrl' => $input['fotoUrl'] ?? null,
            'aktif' => true,
        ])->load('user.akun');

        // Sama seperti guru yang ditambahkan dari Data Guru Classroom: guru baru otomatis tampil di #guru website.
        $this->website->buat($guru->user->akun);

        return $this->present($guru);
    }

    public function update(string $id, array $input): array
    {
        $guru = Guru::find($id);
        if (! $guru) {
            abort(404, 'Guru tidak ditemukan');
        }

        $namaLama = $guru->user->name;
        $aktif = isset($input['aktif']) ? (bool) $input['aktif'] : null;

        // Nama dan status aktif juga ditulis ke profil, supaya Data Guru Classroom dan login ikut berubah.
        $akun = array_filter([
            'password' => ! empty($input['password']) ? Hash::make($input['password']) : null,
            'peran' => $input['role'] ?? null,
            'name' => $input['nama'] ?? null,
            'is_active' => $aktif,
        ], fn ($v) => $v !== null);
        if ($akun !== []) {
            $guru->user->update($akun);
        }

        $guru->update(array_filter([
            'nama' => $input['nama'] ?? null,
            'noHp' => $input['noHp'] ?? null,
            'mapel' => $input['mapel'] ?? null,
            'fotoUrl' => $input['fotoUrl'] ?? null,
            'aktif' => $aktif,
        ], fn ($v) => $v !== null));

        if ($profil = $guru->user->akun) {
            $this->website->sinkron($profil, $namaLama);
        }

        return $this->present($guru->load('user.akun'));
    }

    /**
     * Ganti foto dari Data Guru absensi atau Profil. Satu orang satu foto: yang diganti foto orangnya di website, supaya
     * sama di website, Data Guru Classroom, dan absensi. Guru: `guru_staff` (dibuatkan bila belum tertaut, seperti dari
     * Classroom). Kepala sekolah: baris `principals` yang namanya sama. Hanya bila tidak ada tempatnya di website, foto
     * disimpan sebagai foto absen. Foto absen lama dihapus karena sudah tergantikan.
     */
    public function gantiFoto(Guru $guru, UploadedFile $foto): void
    {
        $akun = $guru->user->akun;
        $kepsek = $akun?->role === 'KEPSEK' ? $akun->kepalaSekolahDiWebsite() : null;

        if ($akun?->role === 'GURU') {
            $this->website->sinkron($akun, null, $foto);
            $this->gantiFotoAbsen($guru, null);
        } elseif ($kepsek) {
            $lama = $kepsek->foto;
            $kepsek->foto = $this->media->storeImage($foto, 'principal-photo', 'foto');
            $kepsek->save();
            $this->media->delete($lama);
            $this->gantiFotoAbsen($guru, null);
        } else {
            $filename = Str::ulid().'.'.$foto->extension();
            $foto->storeAs('guru', $filename, 'public');
            $this->gantiFotoAbsen($guru, "/uploads/guru/{$filename}");
        }
    }

    private function gantiFotoAbsen(Guru $guru, ?string $baru): void
    {
        $lama = $guru->fotoUrl;
        $guru->forceFill(['fotoUrl' => $baru])->save();

        if ($lama && $lama !== $baru && str_contains($lama, '/uploads/guru/')) {
            Storage::disk('public')->delete('guru/'.basename($lama));
        }
    }

    /**
     * Pastikan akun GURU/KEPSEK punya baris `guru` (dipanggil Data Guru Classroom sesudah menambah/mengubah akun). Nama
     * dan status aktif baris yang sudah ada disamakan dengan akunnya. Akun berperan lain dibiarkan.
     */
    public function ikutiAkun(Akun $akun): ?Guru
    {
        if (! in_array($akun->role, self::PERAN, true)) {
            return null;
        }

        $guru = Guru::firstOrNew(['userId' => $akun->id]);
        $guru->nama = $akun->namaTampil();
        $guru->aktif = (bool) $akun->is_active;
        $guru->save();

        return $guru;
    }

    /** Menghapus akun beserta riwayat absensinya; ditolak bila akun masih dipakai di SPPD atau Classroom. */
    public function delete(string $id): void
    {
        $guru = Guru::find($id);
        if (! $guru) {
            abort(404, 'Guru tidak ditemukan');
        }
        $guru->user->cegahHapusLintasModul('Absensi');
        $guru->user->delete();
    }

    /** Issues a fresh static QR token for a guru, invalidating whatever QR
     * (card/phone) they had before - use when a card is lost or a token may
     * have been copied by someone else. */
    public function regenerateQrToken(string $id): array
    {
        $guru = Guru::find($id);
        if (! $guru) {
            abort(404, 'Guru tidak ditemukan');
        }
        $guru->update(['qrToken' => bin2hex(random_bytes(16))]);

        return $this->present($guru->load('user.akun'));
    }
}
