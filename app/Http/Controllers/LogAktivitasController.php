<?php

namespace App\Http\Controllers;

use App\Models\LogAktivitas;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Halaman Log Aktivitas bersama semua modul (admin saja). Data lebih dari 30 hari sudah terhapus otomatis. */
class LogAktivitasController extends Controller
{
    public const MODUL = ['Website', 'SKL', 'Classroom', 'Absensi', 'SPPD', 'Supervisi', 'Data Guru', 'Akun'];

    public function index(Request $request)
    {
        $filter = $request->validate([
            'modul' => ['nullable', 'string', 'in:'.implode(',', self::MODUL)],
            'aksi' => ['nullable', 'string', 'in:'.implode(',', LogAktivitas::AKSI)],
            'cari' => ['nullable', 'string', 'max:100'],
            'dari' => ['nullable', 'date_format:Y-m-d'],
            'sampai' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $cari = trim((string) ($filter['cari'] ?? ''));

        $log = LogAktivitas::query()
            ->when($filter['modul'] ?? null, fn (Builder $q, string $modul) => $q->where('modul', $modul))
            ->when($filter['aksi'] ?? null, fn (Builder $q, string $aksi) => $q->where('aksi', $aksi))
            ->when($filter['dari'] ?? null, fn (Builder $q, string $dari) => $q->where('createdAt', '>=', $dari.' 00:00:00'))
            ->when($filter['sampai'] ?? null, fn (Builder $q, string $sampai) => $q->where('createdAt', '<=', $sampai.' 23:59:59.999'))
            ->when($cari !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->where('namaPengguna', 'like', "%{$cari}%")
                ->orWhere('deskripsi', 'like', "%{$cari}%")
                ->orWhere('ip', 'like', "%{$cari}%")))
            ->orderByDesc('createdAt')->orderByDesc('id')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return Inertia::render('log-aktivitas-page', [
            'log' => $log->through(fn (LogAktivitas $l) => [
                'id' => $l->id,
                'waktu' => $l->createdAt->toIso8601String(),
                'pengguna' => $l->namaPengguna,
                'modul' => $l->modul,
                'aksi' => $l->aksi,
                'entitas' => $l->entitas,
                'deskripsi' => $l->deskripsi,
                'perubahan' => $l->perubahan,
                'ip' => $l->ip,
                'userAgent' => $l->userAgent,
            ]),
            'filter' => [
                'modul' => $filter['modul'] ?? null,
                'aksi' => $filter['aksi'] ?? null,
                'cari' => $cari,
                'dari' => $filter['dari'] ?? null,
                'sampai' => $filter['sampai'] ?? null,
            ],
            'opsi' => ['modul' => self::MODUL, 'aksi' => LogAktivitas::AKSI],
            'masaSimpanHari' => LogAktivitas::MASA_SIMPAN_HARI,
        ]);
    }
}
