<?php

namespace App\Http\Controllers;

use App\Services\NotifikasiTerpusat;
use Illuminate\Http\Request;

/**
 * Aksi lonceng notifikasi terpusat di topbar Menu Utama (daftarnya dikirim MenuController). Menggantikan halaman
 * notifikasi absensi dan SPPD.
 */
class NotifikasiController extends Controller
{
    public function __construct(private NotifikasiTerpusat $notifikasi) {}

    /** Klik satu notifikasi: tandai dibaca, lalu buka halaman yang dibicarakannya. */
    public function baca(Request $request, string $sumber, string $id)
    {
        $tujuan = $this->notifikasi->baca($request->user(), $sumber, $id);
        abort_if($tujuan === null, 404, 'Notifikasi tidak ditemukan');

        return redirect($tujuan);
    }

    public function bacaSemua(Request $request)
    {
        $this->notifikasi->bacaSemua($request->user());

        return back();
    }
}
