<?php

namespace App\Http\Controllers;

use App\Models\Classroom\Akun;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Menu utama setelah masuk: kartu Website, SKL, dan Classroom sesuai peran. Bila hanya ada satu modul yang boleh
 * dibuka, pengguna langsung diarahkan ke sana.
 */
class MenuController extends Controller
{
    public function index(Request $request)
    {
        $pengguna = $request->user();
        $akun = Akun::untukPengguna($pengguna);
        $akun = $akun?->is_active ? $akun : null;
        $staf = in_array($akun?->role, ['GURU', 'KEPSEK'], true);

        $modul = [];
        if ($pengguna->isAdmin()) {
            $modul[] = ['key' => 'website', 'href' => '/admin'];
            $modul[] = ['key' => 'skl', 'href' => '/skl'];
        } elseif (! $staf && config('school.skl_siswa_aktif')) {
            $modul[] = ['key' => 'skl', 'href' => '/portal'];
        }
        if ($akun) {
            $modul[] = ['key' => 'classroom', 'href' => '/classroom/kelas'];
        }

        if (count($modul) === 1) {
            return redirect($modul[0]['href']);
        }

        return Inertia::render('module-picker-page', ['modul' => $modul]);
    }
}
