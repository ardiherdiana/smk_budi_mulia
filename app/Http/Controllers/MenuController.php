<?php

namespace App\Http\Controllers;

use App\Models\Classroom\Akun;
use App\Services\NotifikasiTerpusat;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Menu utama (dashboard internal) setelah masuk: kartu Website, SKL, Classroom, Absensi, SPPD, Supervisi, Data Guru, dan
 * Profil sesuai peran (admin melihat kedelapannya). Bila hanya ada satu modul yang boleh dibuka, pengguna langsung diarahkan ke sana.
 *
 * - Website + SKL admin: admin (satu admin untuk semua modul).
 * - SKL portal: siswa/alumni (bukan staf) selama `school.skl_siswa_aktif`.
 * - Classroom: profil ADMIN|KEPSEK|GURU|SISWA.
 * - Absensi: admin, KEPSEK, GURU.
 * - SPPD: admin dan semua staf (KEPSEK, GURU, STAF); detail akses diatur role SPPD.
 * - Supervisi: admin, KEPSEK, GURU (kepsek/admin mengelola, guru melihat supervisinya dan tugas sebagai supervisor).
 * - Data Guru (`/data-guru`, data master guru untuk semua modul): admin mengelola, KEPSEK melihat.
 * - Profil: semua akun (satu profil untuk semua modul).
 * Kartu harus sejalan dengan middleware rute (`peran:` Classroom, `staf:` absensi/SPPD/Supervisi).
 */
class MenuController extends Controller
{
    public function index(Request $request)
    {
        $pengguna = $request->user();
        $akun = Akun::untukPengguna($pengguna);
        $akun = $akun?->is_active ? $akun : null;
        $peran = $akun?->role;
        $admin = $pengguna->isAdmin();
        $staf = in_array($peran, ['GURU', 'KEPSEK', 'STAF'], true);

        $modul = [];
        if ($admin) {
            $modul[] = ['key' => 'website', 'href' => '/admin'];
            $modul[] = ['key' => 'skl', 'href' => '/skl'];
        } elseif (! $staf && config('school.skl_siswa_aktif')) {
            $modul[] = ['key' => 'skl', 'href' => '/portal'];
        }
        if (in_array($peran, ['ADMIN', 'KEPSEK', 'GURU', 'SISWA'], true)) {
            $modul[] = ['key' => 'classroom', 'href' => '/classroom/kelas'];
        }
        if ($admin || in_array($peran, ['KEPSEK', 'GURU'], true)) {
            $modul[] = ['key' => 'absensi', 'href' => '/absen/dashboard'];
        }
        if ($admin || $staf) {
            $modul[] = ['key' => 'sppd', 'href' => '/sppd/dashboard'];
        }
        if ($admin || in_array($peran, ['KEPSEK', 'GURU'], true)) {
            $modul[] = ['key' => 'supervisi', 'href' => '/supervisi/dashboard'];
        }
        if ($admin || $peran === 'KEPSEK') {
            $modul[] = ['key' => 'dataguru', 'href' => '/data-guru'];
        }
        $modul[] = ['key' => 'profil', 'href' => '/profil'];

        if (count($modul) === 1) {
            return redirect($modul[0]['href']);
        }

        return Inertia::render('module-picker-page', [
            'modul' => $modul,
            // Lonceng notifikasi terpusat di topbar (absensi untuk admin/kepsek + SPPD milik akun ini).
            'notifikasi' => app(NotifikasiTerpusat::class)->untuk($pengguna),
        ]);
    }
}
