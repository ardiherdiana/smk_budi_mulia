<?php

namespace App\Http\Controllers;

use App\Models\Classroom\Akun;
use App\Services\GuruService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

/**
 * Modul Profil: SATU halaman profil untuk semua akun dan semua modul (Website, SKL, Classroom, Absensi, SPPD).
 * Menggantikan profil absensi, profil SPPD, profil Classroom, dan dialog "Ganti password" panel Website/SKL.
 * Isi menyesuaikan akun: data guru (punya baris `guru`), data siswa (profil SISWA), role SPPD. Rekap kehadiran dan
 * jumlah ruang kelas tidak di sini (ada di modulnya). Ganti sandi berlaku untuk semua akun karena sandinya satu:
 * `users.password`.
 */
class ProfilController extends Controller
{
    public function __construct(private GuruService $guruService) {}

    public function edit(Request $request)
    {
        $user = $request->user();
        // Admin lama tanpa profil dibuatkan profil ADMIN, sama seperti saat membuka menu utama atau Classroom.
        $akun = Akun::untukPengguna($user);
        $akun?->loadMissing('siswa.rombel:id,nama', 'staff:id,image');
        $guru = $user->guru;

        return Inertia::render('profil-page', [
            'akun' => [
                'nama' => $guru?->nama ?? $akun?->namaTampil() ?? $user->email,
                'username' => $akun?->username,
                // Akun portal/SKL lama (tanpa profil) dikenali lewat email; email sintetis @akun.local tidak ditampilkan.
                'email' => $akun ? null : $user->email,
                'peran' => $akun?->role ?? ($user->isAdmin() ? 'ADMIN' : null),
                'jabatan' => $akun?->jabatan,
                'peranSppd' => $user->getRoleNames()->values()->all(),
                'bergabung' => $akun?->createdAt?->toDateString(),
                // Foto website (sama dengan Data Guru Classroom dan absensi), foto absen lama bila di website belum ada.
                'fotoUrl' => $akun?->fotoUrl() ?? $guru?->fotoUrl,
            ],
            'guru' => $guru ? [
                'nama' => $guru->nama,
                'mapel' => $guru->mapel,
                'noHp' => $guru->noHp,
                'aktif' => (bool) $guru->aktif,
            ] : null,
            'siswa' => $akun?->siswa ? [
                'nis' => $akun->siswa->nis,
                'rombel' => $akun->siswa->rombel?->nama,
            ] : null,
        ]);
    }

    /** Foto guru/kepsek (satu foto untuk website, Classroom, dan absensi; lihat GuruService::gantiFoto). Hanya akun yang punya baris `guru`. */
    public function unggahFoto(Request $request)
    {
        $guru = $request->user()->guru;
        abort_if(! $guru, 403, 'Akun ini bukan akun guru');

        $request->validate([
            'foto' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ], [
            'foto.mimes' => 'Format foto harus JPG, PNG, atau WEBP',
            'foto.max' => 'Ukuran foto maksimal 2MB',
        ]);

        $this->guruService->gantiFoto($guru, $request->file('foto'));

        return back()->with('toast', ['type' => 'success', 'message' => 'Foto profil berhasil diperbarui']);
    }

    /** Satu aturan sandi untuk semua akun (aturan panel Website sebelumnya): minimal 8 karakter, huruf dan angka. */
    public function gantiPassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string', 'current_password'],
            'password' => ['required', 'string', 'confirmed', Password::min(8)->letters()->numbers(), 'different:current_password', 'max:100'],
        ], [
            'current_password.required' => 'Password saat ini wajib diisi.',
            'current_password.current_password' => 'Password saat ini salah.',
            'password.required' => 'Password baru wajib diisi.',
            'password.confirmed' => 'Konfirmasi password tidak sama.',
            'password.different' => 'Password baru harus berbeda dari password saat ini.',
        ]);

        $request->user()->update(['password' => $validated['password']]);
        $request->session()->regenerate();

        return back()->with('toast', ['type' => 'success', 'message' => 'Password berhasil diubah']);
    }
}
