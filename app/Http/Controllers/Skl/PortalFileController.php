<?php

namespace App\Http\Controllers\Skl;

use App\Http\Controllers\Controller;
use App\Models\LogAktivitas;
use App\Models\PortalFile;
use App\Models\User;
use App\Services\PortalFileStorage;
use App\Support\LogAktivitas\Perekam;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/** Berkas SKL dsb. Berkas tanpa pemilik = "gudang" (belum dibagikan ke siswa). */
class PortalFileController extends Controller
{
    public function __construct(private PortalFileStorage $storage, private Perekam $perekam) {}

    public function index(Request $request)
    {
        $scope = $request->query('scope') === 'gudang' ? 'gudang' : 'all';
        $search = trim((string) $request->query('q', ''));
        $perPage = in_array((int) $request->query('per_page'), [25, 50, 100], true) ? (int) $request->query('per_page') : 25;

        $files = PortalFile::query()
            ->with('owner:id,email')
            ->when($scope === 'gudang', fn ($q) => $q->unassigned())
            ->when($search, function ($q) use ($search) {
                $like = '%'.addcslashes($search, '\\%_').'%';
                $q->where(fn ($w) => $w->where('nama_file', 'like', $like)
                    ->orWhereHas('owner', fn ($o) => $o->where('email', 'like', $like)));
            })
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('skl/berkas-page', [
            'files' => [
                'data' => $files->getCollection()->map(fn (PortalFile $f) => [
                    'id' => $f->id,
                    'nama_file' => $f->nama_file,
                    'uploaded_at' => $f->uploaded_at?->toIso8601String(),
                    'owner_email' => $f->owner && $f->owner->email !== 'gudang@system.internal' ? $f->owner->email : null,
                    'available' => $this->storage->safePath($f) !== null,
                    'download_url' => route('portal.download', $f),
                ])->all(),
                'current_page' => $files->currentPage(),
                'per_page' => $files->perPage(),
                'total' => $files->total(),
            ],
            'students' => User::akunSkl()->where('email', '<>', 'gudang@system.internal')
                ->orderBy('email')->get(['id', 'email'])->map(fn ($u) => ['id' => $u->id, 'email' => $u->email])->all(),
            'filters' => ['q' => $search, 'scope' => $scope],
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'files' => ['required', 'array', 'min:1', 'max:200'],
            'files.*' => ['file', 'mimetypes:application/pdf', 'max:10240'],
            'user_id' => ['nullable', $this->aturanSiswa()],
        ], [
            'files.*.mimetypes' => 'Hanya file PDF yang diizinkan.',
            'files.*.max' => 'Ukuran tiap PDF maksimal 10 MB.',
        ]);

        $count = 0;
        foreach ($request->file('files') as $upload) {
            // Tolak file yang hanya berganti ekstensi: header PDF wajib ada.
            if (! str_starts_with((string) file_get_contents($upload->getRealPath(), false, null, 0, 5), '%PDF-')) {
                throw ValidationException::withMessages(['files' => 'Berkas '.$upload->getClientOriginalName().' bukan PDF yang valid.']);
            }
            $this->storage->store($upload, $request->integer('user_id') ?: null);
            $count++;
        }

        return back()->with('toast', ['type' => 'success', 'message' => "{$count} berkas berhasil diunggah"]);
    }

    /** Bagikan berkas terpilih ke satu siswa (user_id kosong = kembalikan ke gudang). */
    public function assign(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['integer'],
            'user_id' => ['nullable', $this->aturanSiswa()],
        ]);

        $jumlah = PortalFile::whereIn('id', $validated['ids'])->update(['user_id' => $validated['user_id'] ?? null]);

        // Update massal tidak memicu event model, jadi dicatat langsung.
        $tujuan = ($validated['user_id'] ?? null) ? User::find($validated['user_id']) : null;
        $this->perekam->catat(
            LogAktivitas::UBAH,
            'SKL',
            Perekam::namaPengguna($request->user()).($tujuan
                ? " membagikan {$jumlah} berkas SKL ke \"".Perekam::namaPengguna($tujuan).'"'
                : " mengembalikan {$jumlah} berkas SKL ke gudang"),
            'Berkas SKL',
        );

        return back()->with('toast', ['type' => 'success', 'message' => 'Berkas berhasil dibagikan']);
    }

    public function destroy(PortalFile $file)
    {
        $this->storage->delete($file);

        return back()->with('toast', ['type' => 'success', 'message' => 'Berkas berhasil dihapus']);
    }

    /** Pemilik berkas SKL harus akun siswa/alumni (lihat User::akunSkl), bukan guru atau pegawai yang juga ber-role user. */
    private function aturanSiswa(): Exists
    {
        return Rule::exists('users', 'id')
            ->where('role', 'user')
            ->where(fn ($q) => $q->whereNotIn('id', fn ($s) => $s->select('id')->from('profil_akun')->whereIn('role', User::PERAN_STAF)));
    }
}
