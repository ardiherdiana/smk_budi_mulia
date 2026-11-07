<?php

namespace App\Services;

use App\Models\LeaveRequest;
use Illuminate\Support\Carbon;

class LeaveService
{
    public function __construct(private NotificationService $notifications) {}

    public function create(string $guruId, string $jenis, Carbon $tanggalMulai, Carbon $tanggalSelesai, string $alasan, ?string $lampiranUrl): LeaveRequest
    {
        $mulai = $tanggalMulai->copy()->startOfDay();
        $selesai = $tanggalSelesai->copy()->startOfDay();
        if ($mulai->gt($selesai)) {
            abort(400, 'Tanggal mulai tidak boleh setelah tanggal selesai');
        }

        $leave = LeaveRequest::create([
            'guruId' => $guruId,
            'jenis' => $jenis,
            'tanggalMulai' => $mulai,
            'tanggalSelesai' => $selesai,
            'alasan' => $alasan,
            'lampiranUrl' => $lampiranUrl,
            'status' => 'PENDING',
        ]);
        $leave->load('guru:id,nama');
        $leave->guru?->makeHidden('id');

        $jenisLabel = $leave->jenis === 'IZIN' ? 'izin' : 'sakit';
        $this->notifications->create(
            'LEAVE_REQUEST',
            'Pengajuan Izin/Sakit',
            "{$leave->guru->nama} mengajukan {$jenisLabel} ({$leave->tanggalMulai->toDateString()} s/d {$leave->tanggalSelesai->toDateString()})",
            $guruId
        );

        return $leave;
    }

    public function myLeaveRequests(string $guruId)
    {
        return LeaveRequest::where('guruId', $guruId)->orderByDesc('createdAt')->get();
    }

    public function list(?string $status = null)
    {
        return LeaveRequest::with('guru:id,nama')
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderByDesc('createdAt')
            ->get()
            ->each(fn (LeaveRequest $l) => $l->guru?->makeHidden('id'));
    }

    public function review(string $id, string $status, string $reviewerUsername): LeaveRequest
    {
        $leave = LeaveRequest::find($id);
        if (! $leave) {
            abort(404, 'Pengajuan tidak ditemukan');
        }
        if ($leave->status !== 'PENDING') {
            abort(409, 'Pengajuan ini sudah diproses');
        }

        $leave->update(['status' => $status, 'reviewedBy' => $reviewerUsername, 'reviewedAt' => Carbon::now()]);

        return $leave;
    }
}
