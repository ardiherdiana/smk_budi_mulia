<?php

namespace App\Http\Controllers\Classroom;

use App\Models\Classroom\Akun;
use App\Models\Classroom\Kelas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class ProfileController extends Controller
{
    public function edit(Request $request)
    {
        $user = $request->user();
        $user->loadMissing('siswa.rombel', 'staff:id,image');

        return Inertia::render('classroom/profil-page', [
            'akun' => [
                'username' => $user->username,
                'nama' => $user->namaTampil(),
                'fotoUrl' => $user->fotoUrl(),
                'peran' => $user->role,
                'nis' => $user->siswa?->nis,
                'rombel' => $user->siswa?->rombel?->nama,
                'jumlahKelas' => $user->isGuru() ? Kelas::where('guruId', $user->id)->where('diarsipkan', false)->count() : null,
                'dapatGantiPassword' => $this->sandiLokal($user),
            ],
        ]);
    }

    public function changePassword(Request $request)
    {
        $user = $request->user();
        abort_unless($this->sandiLokal($user), 403, 'Password akun ini diganti lewat aplikasi absensi');

        $validated = $request->validate([
            'oldPassword' => ['required', 'string'],
            'newPassword' => ['required', 'string', 'min:6', 'max:100'],
        ], [
            'oldPassword.required' => 'Password lama wajib diisi',
            'newPassword.required' => 'Password baru wajib diisi',
            'newPassword.min' => 'Password baru minimal 6 karakter',
        ]);

        if (! Hash::check($validated['oldPassword'], $user->password)) {
            return back()->withErrors(['oldPassword' => 'Password lama salah']);
        }

        // `password` bukan kolom fillable di Akun (sandinya di `users`), jadi update([...]) akan membuangnya diam-diam.
        $user->password = $validated['newPassword'];
        $user->save();

        return back()->with('toast', ['type' => 'success', 'message' => 'Password berhasil diganti']);
    }

    /** Siswa dan akun staf buatan admin memakai sandi di sini; staf hasil sinkron absensi diverifikasi ke absensi. */
    private function sandiLokal(Akun $akun): bool
    {
        return $akun->absensi_user_id === null;
    }
}
