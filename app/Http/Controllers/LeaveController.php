<?php

namespace App\Http\Controllers;

use App\Models\LeaveRequest;
use App\Services\LeaveService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Inertia\Inertia;

class LeaveController extends Controller
{
    public function __construct(private LeaveService $leave) {}

    public function index(Request $request)
    {
        $guru = $request->user()->guru;
        abort_if(! $guru, 403, 'Akun ini bukan akun guru');

        return Inertia::render('absensi/guru/izin-page', [
            'list' => fn () => $this->leave->myLeaveRequests($guru->id),
        ]);
    }

    public function store(Request $request)
    {
        $guru = $request->user()->guru;
        abort_if(! $guru, 403, 'Akun ini bukan akun guru');

        $validated = $request->validate([
            'jenis' => ['required', 'in:IZIN,SAKIT'],
            'tanggalMulai' => ['required', 'string'],
            'tanggalSelesai' => ['required', 'string'],
            'alasan' => ['required', 'string'],
            'lampiran' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ], [
            'lampiran.required' => 'Lampiran bukti wajib dilampirkan',
            'lampiran.mimes' => 'Format lampiran harus JPG, PNG, WEBP, atau PDF',
            'lampiran.max' => 'Format lampiran harus JPG, PNG, WEBP, atau PDF',
        ]);

        $filename = Str::ulid().'.'.$request->file('lampiran')->extension();
        $request->file('lampiran')->storeAs('izin', $filename, 'public');

        $this->leave->create(
            $guru->id,
            $validated['jenis'],
            Carbon::parse($validated['tanggalMulai']),
            Carbon::parse($validated['tanggalSelesai']),
            $validated['alasan'],
            "/uploads/izin/{$filename}",
        );

        return back()->with('toast', ['type' => 'success', 'message' => 'Pengajuan izin/sakit berhasil dikirim']);
    }

    public function adminIndex(Request $request)
    {
        $status = $request->query('status', 'PENDING');

        return Inertia::render('absensi/admin/izin-page', [
            'list' => fn () => $this->leave->list($status === 'all' ? null : $status),
            'filter' => $status,
        ]);
    }

    public function review(Request $request, LeaveRequest $leaveRequest)
    {
        $validated = $request->validate([
            'status' => ['required', 'in:APPROVED,REJECTED'],
        ]);

        $this->leave->review($leaveRequest->id, $validated['status'], $request->user()->username);

        return back()->with('toast', [
            'type' => 'success',
            'message' => $validated['status'] === 'APPROVED' ? 'Pengajuan disetujui' : 'Pengajuan ditolak',
        ]);
    }
}
