<?php

namespace App\Http\Controllers\Sppd;

use App\Enums\Sppd\PengajuanStatus;
use App\Enums\Sppd\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sppd\IsiNomorSppdRequest;
use App\Http\Requests\Sppd\RevisiPengajuanRequest;
use App\Http\Requests\Sppd\SimpanLaporanRequest;
use App\Http\Requests\Sppd\StorePengajuanRequest;
use App\Models\Classroom\Akun;
use App\Models\Sppd\LogAudit;
use App\Models\Sppd\PengajuanSppd;
use App\Models\User;
use App\Services\Sppd\ArsipSppd;
use App\Services\Sppd\PengajuanWorkflowService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection as SupportCollection;
use Inertia\Inertia;
use Inertia\Response;

class PengajuanSppdController extends Controller
{
    public function __construct(
        private readonly PengajuanWorkflowService $workflow,
        private readonly ArsipSppd $arsip,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        $query = PengajuanSppd::query()->with('pemohon');

        if (! $user->isAdmin() && ! $user->hasAnyRole([RoleName::KepalaSekolah->value, RoleName::Tu->value, RoleName::Bendahara->value])) {
            $query->where('pemohon_id', $user->id);
        }

        $status = PengajuanStatus::tryFrom($request->string('status')->toString())?->value;

        if ($status) {
            $query->where('status', $status);
        }

        $pengajuans = $query->latest()->paginate($this->perPage($request))->withQueryString();

        return Inertia::render('sppd/pengajuan/index', [
            'pengajuans' => $pengajuans,
            'filters' => ['status' => $status],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', PengajuanSppd::class);

        return Inertia::render('sppd/pengajuan/create', [
            'pegawai' => $this->pegawaiPilihan(auth()->id()),
        ]);
    }

    public function store(StorePengajuanRequest $request): RedirectResponse
    {
        $path = $request->file('undangan')?->store('undangan', 'public');

        $pengajuan = $this->workflow->buat($request->user(), $this->dataPengajuan($request) + ['undangan_path' => $path], $request->input('pengikut_ids', []));

        return to_route('sppd.pengajuan.show', $pengajuan)
            ->with('success', 'SPPD berhasil diajukan ke Kepala Sekolah. Setelah disetujui, TU mengisi nomor surat dan SPPD bisa diunduh.');
    }

    public function edit(PengajuanSppd $pengajuan): Response
    {
        $this->authorize('revisi', $pengajuan);

        $pengajuan->load('pengikuts.akun');

        // Tanggal dan jam dikirim sebagai teks 'Y-m-d' dan 'H:i' karena itulah nilai yang dipakai DatePicker dan TimePicker.
        return Inertia::render('sppd/pengajuan/revisi', [
            'pegawai' => $this->pegawaiPilihan($pengajuan->pemohon_id),
            'pengajuan' => [
                'id' => $pengajuan->id,
                'tujuan' => $pengajuan->tujuan,
                'maksud' => $pengajuan->maksud,
                'alat_angkutan' => $pengajuan->alat_angkutan,
                'keterangan' => $pengajuan->keterangan,
                'tanggal_berangkat' => $pengajuan->tanggal_berangkat->toDateString(),
                'jam_berangkat' => substr($pengajuan->jam_berangkat, 0, 5),
                'tanggal_kembali' => $pengajuan->tanggal_kembali->toDateString(),
                'jam_kembali' => substr($pengajuan->jam_kembali, 0, 5),
                'undangan_path' => $pengajuan->undangan_path,
                'catatan_kepsek' => $pengajuan->catatan_kepsek,
                'pengikut_ids' => $pengajuan->pengikuts->pluck('id')->all(),
            ],
        ]);
    }

    public function revisi(RevisiPengajuanRequest $request, PengajuanSppd $pengajuan): RedirectResponse
    {
        $data = $this->dataPengajuan($request);

        // Tanpa berkas baru, undangan lama dipertahankan; berkas baru menggantikannya.
        if ($path = $request->file('undangan')?->store('undangan', 'public')) {
            $data['undangan_path'] = $path;
        }

        $this->workflow->revisi($pengajuan, $request->user(), $data, $request->input('pengikut_ids', []));

        return to_route('sppd.pengajuan.show', $pengajuan)->with('success', 'Pengajuan direvisi dan diajukan ulang ke Kepala Sekolah.');
    }

    public function show(Request $request, PengajuanSppd $pengajuan): Response
    {
        $this->authorize('view', $pengajuan);

        $pengajuan->load([
            'pemohon.akun', 'penyetuju.akun', 'sppd', 'pengikuts.akun',
            'pencairans.pencair.akun', 'laporan.fotos', 'laporan.penulis.akun',
        ]);

        $riwayat = LogAudit::query()
            ->where('entitas_terkait', PengajuanSppd::class)
            ->where('entitas_id', $pengajuan->id)
            ->with('user')
            ->oldest()
            ->get();

        $laporan = $pengajuan->laporan;

        return Inertia::render('sppd/pengajuan/show', [
            'pengajuan' => $pengajuan->makeHidden('laporan'),
            'diarsipkan' => $this->arsip->ada($pengajuan),
            'riwayat' => $riwayat,
            'laporan' => $laporan ? [
                'ringkasan' => $laporan->ringkasan,
                'penulis' => $laporan->penulis?->name,
                'diperbarui_at' => $laporan->updated_at->toIso8601String(),
                'fotos' => $laporan->fotos->map(fn ($foto) => [
                    'id' => $foto->id,
                    'path' => $foto->path,
                    'nama_asli' => $foto->nama_asli,
                ])->all(),
            ] : null,
            // Nomor WhatsApp hanya untuk pemohon pengajuan ini: dialah yang perlu menanyakan keputusan ke Kepsek atau TU.
            'kontak' => $request->user()->id === $pengajuan->pemohon_id ? config('sppd.kontak') : null,
            'can' => [
                'approve' => $request->user()->can('approve', $pengajuan),
                'revisi' => $request->user()->can('revisi', $pengajuan),
                'isiNomor' => $request->user()->can('isiNomor', $pengajuan),
                'isiAkunAnggaran' => $request->user()->can('isiAkunAnggaran', $pengajuan),
                'isiLaporan' => $request->user()->can('isiLaporan', $pengajuan),
                'cairkanLumsum' => $request->user()->can('cairkanLumsum', $pengajuan),
                'selesaikan' => $request->user()->can('selesaikan', $pengajuan),
            ],
        ]);
    }

    public function approve(Request $request, PengajuanSppd $pengajuan): RedirectResponse
    {
        $this->authorize('approve', $pengajuan);

        $data = $request->validate(['catatan' => ['nullable', 'string', 'max:1000']]);

        try {
            $this->workflow->setujui($pengajuan, $request->user(), $data['catatan'] ?? null);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Pengajuan disetujui. TU akan mengisi nomor surat.');
    }

    public function reject(Request $request, PengajuanSppd $pengajuan): RedirectResponse
    {
        $this->authorize('approve', $pengajuan);

        $data = $request->validate(['catatan' => ['required', 'string', 'max:1000']], [
            'catatan.required' => 'Catatan wajib diisi untuk menolak pengajuan.',
        ]);

        try {
            $this->workflow->tolak($pengajuan, $request->user(), $data['catatan']);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Pengajuan ditolak. Pemohon dapat merevisi dan mengajukan ulang.');
    }

    public function simpanNomor(IsiNomorSppdRequest $request, PengajuanSppd $pengajuan): RedirectResponse
    {
        $this->workflow->isiNomor($pengajuan, $request->user(), $request->string('nomor_sppd')->toString());

        return back()->with('success', 'Nomor SPPD disimpan.');
    }

    public function simpanAkunAnggaran(Request $request, PengajuanSppd $pengajuan): RedirectResponse
    {
        $this->authorize('isiAkunAnggaran', $pengajuan);
        abort_if(! $pengajuan->sppd, 404);

        $data = $request->validate(['akun_anggaran' => ['nullable', 'string', 'max:100']]);

        $this->workflow->isiAkunAnggaran($pengajuan, $request->user(), $data['akun_anggaran'] ?? null);

        return back()->with('success', 'Akun anggaran disimpan.');
    }

    public function simpanLaporan(SimpanLaporanRequest $request, PengajuanSppd $pengajuan): RedirectResponse
    {
        $fotoBaru = collect($request->file('fotos', []))
            ->map(fn ($file) => [
                'path' => $file->store('laporan-perjalanan', 'public'),
                'nama_asli' => $file->getClientOriginalName(),
            ])
            ->all();

        $this->workflow->simpanLaporan(
            $pengajuan,
            $request->user(),
            $request->string('ringkasan')->toString(),
            $request->input('hapus_foto_ids', []),
            $fotoBaru,
        );

        return back()->with('success', 'Laporan perjalanan berhasil disimpan.');
    }

    public function cairkanLumsum(Request $request, PengajuanSppd $pengajuan): RedirectResponse
    {
        $this->authorize('cairkanLumsum', $pengajuan);

        $data = $request->validate([
            'jumlah' => ['required', 'numeric', 'min:1'],
            'keterangan' => ['nullable', 'string', 'max:500'],
        ]);

        $this->workflow->cairkanLumsum($pengajuan, $request->user(), (float) $data['jumlah'], $data['keterangan'] ?? null);

        return back()->with('success', 'Lumsum berhasil dicairkan.');
    }

    public function selesaikan(Request $request, PengajuanSppd $pengajuan): RedirectResponse
    {
        $this->authorize('selesaikan', $pengajuan);

        try {
            $this->workflow->selesaikan($pengajuan, $request->user());
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Perjalanan dinas selesai dan SPPD bertanda tangan telah diarsipkan.');
    }

    public function downloadSppd(PengajuanSppd $pengajuan)
    {
        $this->authorize('view', $pengajuan);

        $pengajuan->load(['pemohon.akun', 'penyetuju.akun', 'sppd', 'pengikuts']);
        abort_if(! $pengajuan->sppd, 404, 'SPPD belum bisa diunduh: nomor surat belum diisi TU.');

        $nomor = str_replace('/', '-', $pengajuan->sppd->nomor_sppd);

        // Sesudah diarsipkan TU, unduhan adalah salinan beku bertanda tangan; sebelumnya (dan untuk SPPD selesai
        // sebelum ada arsip) dirender langsung dengan kolom tanda tangan kosong untuk pulpen.
        if ($this->arsip->ada($pengajuan)) {
            return $this->arsip->unduh($pengajuan, "sppd-{$nomor}.pdf");
        }

        return Pdf::loadView('pdf.sppd', ['pengajuan' => $pengajuan])
            ->setPaper('a4', 'landscape')
            ->download("sppd-{$nomor}.pdf");
    }

    /**
     * Pegawai aktif yang bisa dipilih sebagai pengikut: semua kecuali pemohon sendiri. Nama dan jabatan ada di profil.
     *
     * @return SupportCollection<int, array{id: int, name: ?string, jabatan: ?string}>
     */
    private function pegawaiPilihan(int|string $pemohonId): SupportCollection
    {
        return User::query()
            ->with('akun')
            ->pegawaiAktif()
            ->whereKeyNot($pemohonId)
            ->orderBy(Akun::select('name')->whereColumn('profil_akun.id', 'users.id'))
            ->get()
            ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name, 'jabatan' => $user->jabatan]);
    }

    /**
     * Kolom isian pemohon yang sama untuk pengajuan baru dan revisi (tanpa berkas undangan).
     *
     * @return array<string, mixed>
     */
    private function dataPengajuan(StorePengajuanRequest $request): array
    {
        return [
            'tujuan' => $request->input('tujuan'),
            'maksud' => $request->input('maksud'),
            'alat_angkutan' => $request->input('alat_angkutan'),
            'keterangan' => $request->input('keterangan'),
            'tanggal_berangkat' => $request->date('tanggal_berangkat'),
            'jam_berangkat' => $request->input('jam_berangkat'),
            'tanggal_kembali' => $request->date('tanggal_kembali'),
            'jam_kembali' => $request->input('jam_kembali'),
        ];
    }
}
