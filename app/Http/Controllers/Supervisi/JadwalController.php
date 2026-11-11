<?php

namespace App\Http\Controllers\Supervisi;

use App\Http\Controllers\Controller;
use App\Models\Classroom\Akun;
use App\Models\Classroom\Kelas;
use App\Models\Classroom\Mapel;
use App\Models\Classroom\Rombel;
use App\Models\Supervisi\Supervisi;
use App\Models\User;
use App\Notifications\Supervisi\SupervisiNotifikasi;
use App\Services\Supervisi\AksesSupervisi;
use App\Services\Supervisi\JadwalSupervisi;
use App\Services\Supervisi\SupervisiPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/** Jadwal supervisi (kepsek/admin): daftar per tahun ajaran, buat, ubah, batalkan, hapus. */
class JadwalController extends Controller
{
    public function __construct(private AksesSupervisi $akses, private JadwalSupervisi $jadwal) {}

    public function index(Request $request)
    {
        $this->pastikanKelola($request);

        $tahun = SupervisiPresenter::tahunAjaranTerpilih($request->query('tahunAjaranId'));
        $status = in_array($request->query('status'), Supervisi::STATUS, true) ? $request->query('status') : null;
        $cari = trim((string) $request->query('cari', ''));

        $list = Supervisi::with(SupervisiPresenter::RELASI)
            ->where('tahunAjaranId', $tahun?->id ?? '-')
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($cari !== '', fn ($q) => $q->whereHas('guru', fn ($g) => $g->where('name', 'like', "%{$cari}%")))
            ->orderByDesc('tanggal')->orderByDesc('createdAt')
            ->get();

        $saranKelas = Kelas::with(['mapel:id,nama', 'rombel:id,nama'])
            ->where('tahunAjaranId', $tahun?->id ?? '-')->where('diarsipkan', false)->get()
            ->groupBy('guruId')
            ->map(fn ($kelas) => $kelas->map(fn (Kelas $k) => [
                'mapelId' => $k->mapelId,
                'rombelId' => $k->rombelId,
                'label' => $k->mapel->nama.' - '.$k->rombel->nama,
            ])->sortBy('label')->values());

        return Inertia::render('supervisi/jadwal-page', [
            'list' => $list->map(SupervisiPresenter::ringkas(...))->values(),
            'tahunAjaran' => SupervisiPresenter::daftarTahunAjaran(),
            'filter' => ['tahunAjaranId' => $tahun?->id, 'status' => $status, 'cari' => $cari],
            // Dari tombol "Jadwalkan" di dashboard: buka formulir dengan guru ini terpilih.
            'bukaUntukGuru' => $request->integer('guru') ?: null,
            'opsi' => [
                'guru' => $this->pilihan(['GURU']),
                'supervisor' => $this->pilihan(['GURU', 'KEPSEK']),
                'mapel' => Mapel::orderBy('nama')->get(['id', 'nama']),
                'rombel' => Rombel::orderBy('nama')->get(['id', 'nama']),
                'saranKelas' => $saranKelas,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $this->pastikanKelola($request);

        $this->jadwal->buat($this->validasi($request), $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => 'Jadwal supervisi dibuat.']);
    }

    public function update(Request $request, Supervisi $supervisi)
    {
        $this->pastikanKelola($request);
        abort_unless($supervisi->bisaDiisi(), 409, 'Hanya jadwal yang belum selesai yang bisa diubah.');

        $this->jadwal->ubah($supervisi, $this->validasi($request), $request->user());

        return back()->with('toast', ['type' => 'success', 'message' => 'Jadwal supervisi diperbarui.']);
    }

    public function batal(Request $request, Supervisi $supervisi)
    {
        $this->pastikanKelola($request);
        abort_unless($supervisi->bisaDiisi(), 409, 'Hanya jadwal yang belum selesai yang bisa dibatalkan.');

        $supervisi->update(['status' => Supervisi::DIBATALKAN]);

        $tanggal = $supervisi->tanggal->locale('id')->translatedFormat('d F Y');
        foreach (array_unique([$supervisi->guruId, $supervisi->supervisorId]) as $id) {
            if ($id !== $request->user()->id) {
                User::find($id)?->notify(new SupervisiNotifikasi($supervisi, "Supervisi tanggal {$tanggal} dibatalkan."));
            }
        }

        return back()->with('toast', ['type' => 'success', 'message' => 'Jadwal supervisi dibatalkan.']);
    }

    public function destroy(Request $request, Supervisi $supervisi)
    {
        $this->pastikanKelola($request);
        abort_if($supervisi->selesai(), 409, 'Supervisi yang sudah selesai tidak bisa dihapus. Buka kuncinya dulu bila perlu dikoreksi.');

        DB::transaction(function () use ($supervisi) {
            // Notifikasi yang menautkan jadwal ini akan membuka halaman 404; ikut dibersihkan.
            DB::table('sppd_notifications')->where('type', SupervisiNotifikasi::class)
                ->where('data', 'like', '%"supervisi_id":"'.$supervisi->id.'"%')->delete();
            $supervisi->delete();
        });

        return back()->with('toast', ['type' => 'success', 'message' => 'Jadwal supervisi dihapus.']);
    }

    private function pastikanKelola(Request $request): void
    {
        abort_unless($this->akses->kelola($request->user()), 403, 'Hanya kepala sekolah dan admin yang mengatur jadwal supervisi.');
    }

    /** @return array<string, mixed> */
    private function validasi(Request $request): array
    {
        $aktif = fn (array $peran) => Rule::exists('profil_akun', 'id')->where('is_active', true)->whereIn('role', $peran);

        return $request->validate([
            'tahunAjaranId' => ['required', 'string', Rule::exists('tahun_ajaran', 'id')],
            'guruId' => ['required', 'integer', $aktif(['GURU'])],
            'supervisorId' => ['required', 'integer', 'different:guruId', $aktif(['GURU', 'KEPSEK'])],
            'mapelId' => ['nullable', 'string', Rule::exists('mapel', 'id')],
            'rombelId' => ['nullable', 'string', Rule::exists('rombel', 'id')],
            'tanggal' => ['required', 'date_format:Y-m-d'],
        ], [
            'supervisorId.different' => 'Supervisor tidak boleh guru yang disupervisi.',
        ], [
            'tahunAjaranId' => 'tahun ajaran',
            'guruId' => 'guru',
            'supervisorId' => 'supervisor',
            'mapelId' => 'mapel',
            'rombelId' => 'kelas',
        ]);
    }

    /** @param  list<string>  $peran  @return list<array{id: int, nama: string, peran: string}> */
    private function pilihan(array $peran): array
    {
        return Akun::whereIn('role', $peran)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'username', 'role'])
            ->map(fn (Akun $a) => ['id' => $a->id, 'nama' => $a->namaTampil(), 'peran' => $a->role])
            ->values()->all();
    }
}
