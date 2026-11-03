<?php

namespace App\Http\Controllers\Skl;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PortalFileStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Akun siswa portal (users.role = 'user'). Password TIDAK pernah disimpan mentah. */
class PortalUserController extends Controller
{
    private const MIN_PASSWORD = 8;

    public function __construct(private PortalFileStorage $files) {}

    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $perPage = in_array((int) $request->query('per_page'), [25, 50, 100], true) ? (int) $request->query('per_page') : 25;

        $users = User::query()
            ->where('role', 'user')
            ->where('email', '<>', 'gudang@system.internal')
            ->when($search, fn ($q) => $q->where('email', 'like', '%'.addcslashes($search, '\\%_').'%'))
            ->withCount('files')
            ->orderBy('files_count')
            ->orderBy('email')
            ->paginate($perPage, ['id', 'email'])
            ->withQueryString();

        return Inertia::render('skl/siswa-page', [
            'users' => [
                'data' => $users->getCollection()->map(fn (User $u) => [
                    'id' => $u->id,
                    'email' => $u->email,
                    'files_count' => $u->files_count,
                ])->all(),
                'current_page' => $users->currentPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
            'filters' => ['q' => $search],
        ]);
    }

    /** Registrasi massal: satu email per baris. Password kosong = acak per akun. */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'emails' => ['required', 'string', 'max:50000'],
            'password' => ['nullable', 'string', 'min:'.self::MIN_PASSWORD, 'max:100'],
        ]);

        $emails = collect(preg_split('/\R/', $validated['emails']))
            ->map(fn ($e) => Str::lower(trim($e)))
            ->filter()->unique()->values();

        $created = [];
        $skipped = 0;
        $existing = User::whereIn('email', $emails)->pluck('email')->all();

        DB::transaction(function () use ($emails, $existing, $validated, &$created, &$skipped) {
            foreach ($emails as $email) {
                if (in_array($email, $existing, true) || ! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
                    $skipped++;

                    continue;
                }

                $plain = $validated['password'] ?? $this->randomPassword();
                $user = new User(['email' => $email, 'password' => $plain, 'role' => 'user']);
                $user->save();
                $created[] = ['email' => $email, 'password' => $plain];
            }
        });

        return back()
            ->with('credentials', $created)
            ->with('toast', [
                'type' => $created ? 'success' : 'warning',
                'message' => count($created).' akun dibuat, '.$skipped.' dilewati (sudah ada / email tidak valid)',
            ]);
    }

    public function updatePassword(Request $request, User $user)
    {
        abort_unless($user->role === 'user', 404);
        $validated = $request->validate(['password' => ['required', 'string', 'min:'.self::MIN_PASSWORD, 'max:100']]);

        $user->update(['password' => $validated['password']]);

        return back()->with('toast', ['type' => 'success', 'message' => 'Password siswa berhasil diperbarui']);
    }

    /** Ubah password beberapa akun sekaligus. Password kosong = acak per akun (ditampilkan sekali). */
    public function bulkPassword(Request $request)
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['integer'],
            'password' => ['nullable', 'string', 'min:'.self::MIN_PASSWORD, 'max:100'],
        ]);

        $credentials = [];
        User::whereIn('id', $validated['ids'])->where('role', 'user')->get()
            ->each(function (User $user) use ($validated, &$credentials) {
                $plain = $validated['password'] ?? $this->randomPassword();
                $user->update(['password' => $plain]);
                $credentials[] = ['email' => $user->email, 'password' => $plain];
            });

        return back()
            ->with('credentials', $validated['password'] ? null : $credentials)
            ->with('toast', ['type' => 'success', 'message' => count($credentials).' akun diperbarui']);
    }

    /** Impor CSV (kolom: ID, Email, Password Baru) - format sama dengan ekspor sistem lama. */
    public function import(Request $request)
    {
        $request->validate(['csv' => ['required', 'file', 'mimes:csv,txt', 'max:2048']]);

        $handle = fopen($request->file('csv')->getRealPath(), 'r');
        $updated = 0;
        $skipped = 0;
        $row = 0;

        while (($cols = fgetcsv($handle, 2000, ',', '"', '')) !== false) {
            $row++;
            if ($row === 1 && ! ctype_digit(trim((string) ($cols[0] ?? '')))) {
                continue; // baris header
            }

            $id = (int) preg_replace('/\D/', '', (string) ($cols[0] ?? ''));
            $password = trim((string) ($cols[2] ?? ''));

            if (! $id || strlen($password) < self::MIN_PASSWORD) {
                $skipped++;

                continue;
            }

            $user = User::where('id', $id)->where('role', 'user')->first();
            if (! $user) {
                $skipped++;

                continue;
            }

            $user->update(['password' => $password]);
            $updated++;
        }
        fclose($handle);

        return back()->with('toast', [
            'type' => $updated ? 'success' : 'warning',
            'message' => "{$updated} akun diperbarui, {$skipped} baris dilewati (password kosong/kurang dari ".self::MIN_PASSWORD.' karakter atau ID tidak ditemukan)',
        ]);
    }

    /** Ekspor daftar akun (tanpa password) sebagai templat impor. */
    public function export(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['ID', 'Email Siswa', 'Password Baru (isi lalu impor kembali)'], ',', '"', '');
            User::where('role', 'user')->where('email', '<>', 'gudang@system.internal')
                ->orderBy('email')->each(fn (User $u) => fputcsv($out, [$u->id, $u->email, ''], ',', '"', ''));
            fclose($out);
        }, 'akun-siswa-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function destroy(User $user)
    {
        abort_unless($user->role === 'user', 404);

        $this->files->deleteAllFor($user);
        $user->delete();

        return back()->with('toast', ['type' => 'success', 'message' => 'Akun siswa berhasil dihapus']);
    }

    private function randomPassword(): string
    {
        // Tanpa karakter yang mudah tertukar (0/O, 1/l/I) supaya nyaman dibagikan.
        $alphabet = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $password = '';
        for ($i = 0; $i < 10; $i++) {
            $password .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $password;
    }
}
