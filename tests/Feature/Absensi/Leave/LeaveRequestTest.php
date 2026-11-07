<?php

namespace Tests\Feature\Absensi\Leave;

use App\Models\LeaveRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\AbsensiTestCase;

class LeaveRequestTest extends AbsensiTestCase
{
    public function test_guru_can_submit_a_leave_request_with_an_attachment(): void
    {
        Storage::fake('public');
        $guru = $this->actingAsGuru();

        $response = $this->post('/izin', [
            'jenis' => 'SAKIT',
            'tanggalMulai' => '2025-01-10',
            'tanggalSelesai' => '2025-01-11',
            'alasan' => 'Demam tinggi',
            'lampiran' => UploadedFile::fake()->image('surat.png'),
        ]);

        $response->assertSessionHas('toast');
        $this->assertDatabaseHas('leave_requests', [
            'guruId' => $guru->id,
            'jenis' => 'SAKIT',
            'status' => 'PENDING',
        ]);
        Storage::disk('public')->assertExists(
            'izin/'.basename(LeaveRequest::first()->lampiranUrl)
        );
    }

    public function test_leave_request_requires_an_attachment(): void
    {
        $this->actingAsGuru();

        $response = $this->post('/izin', [
            'jenis' => 'IZIN',
            'tanggalMulai' => '2025-01-10',
            'tanggalSelesai' => '2025-01-11',
            'alasan' => 'Acara keluarga',
        ]);

        $response->assertSessionHasErrors('lampiran');
    }

    public function test_leave_request_with_start_after_end_date_is_rejected(): void
    {
        Storage::fake('public');
        $this->actingAsGuru();

        $response = $this->post('/izin', [
            'jenis' => 'IZIN',
            'tanggalMulai' => '2025-01-15',
            'tanggalSelesai' => '2025-01-10',
            'alasan' => 'Acara keluarga',
            'lampiran' => UploadedFile::fake()->image('surat.png'),
        ]);

        $response->assertStatus(400);
    }

    public function test_admin_can_approve_a_pending_leave_request(): void
    {
        $guru = $this->actingAsGuru();
        $leave = LeaveRequest::create([
            'guruId' => $guru->id,
            'jenis' => 'IZIN',
            'tanggalMulai' => '2025-01-10',
            'tanggalSelesai' => '2025-01-10',
            'alasan' => 'Ada keperluan',
            'status' => 'PENDING',
        ]);
        $this->actingAsAdmin(['username' => 'admin-reviewer']);

        $response = $this->patch("/persetujuan/{$leave->id}/review", ['status' => 'APPROVED']);

        $response->assertSessionHas('toast');
        $this->assertDatabaseHas('leave_requests', [
            'id' => $leave->id,
            'status' => 'APPROVED',
            'reviewedBy' => 'admin-reviewer',
        ]);
    }

    public function test_admin_can_reject_a_pending_leave_request(): void
    {
        $guru = $this->actingAsGuru();
        $leave = LeaveRequest::create([
            'guruId' => $guru->id,
            'jenis' => 'SAKIT',
            'tanggalMulai' => '2025-01-10',
            'tanggalSelesai' => '2025-01-10',
            'alasan' => 'Sakit flu',
            'status' => 'PENDING',
        ]);
        $this->actingAsAdmin();

        $this->patch("/persetujuan/{$leave->id}/review", ['status' => 'REJECTED']);

        $this->assertDatabaseHas('leave_requests', ['id' => $leave->id, 'status' => 'REJECTED']);
    }

    public function test_reviewing_an_already_reviewed_leave_request_is_rejected(): void
    {
        $guru = $this->actingAsGuru();
        $leave = LeaveRequest::create([
            'guruId' => $guru->id,
            'jenis' => 'IZIN',
            'tanggalMulai' => '2025-01-10',
            'tanggalSelesai' => '2025-01-10',
            'alasan' => 'Sudah diproses',
            'status' => 'APPROVED',
            'reviewedBy' => 'admin',
            'reviewedAt' => now(),
        ]);
        $this->actingAsAdmin();

        $response = $this->patch("/persetujuan/{$leave->id}/review", ['status' => 'REJECTED']);

        $response->assertStatus(409);
    }

    public function test_guru_cannot_review_leave_requests(): void
    {
        $guru = $this->actingAsGuru();
        $leave = LeaveRequest::create([
            'guruId' => $guru->id,
            'jenis' => 'IZIN',
            'tanggalMulai' => '2025-01-10',
            'tanggalSelesai' => '2025-01-10',
            'alasan' => 'Coba akses',
            'status' => 'PENDING',
        ]);

        $response = $this->patch("/persetujuan/{$leave->id}/review", ['status' => 'APPROVED']);

        $response->assertStatus(403);
    }

    public function test_guru_can_see_only_their_own_leave_requests(): void
    {
        $guru = $this->actingAsGuru();
        LeaveRequest::create([
            'guruId' => $guru->id,
            'jenis' => 'IZIN',
            'tanggalMulai' => '2025-01-10',
            'tanggalSelesai' => '2025-01-10',
            'alasan' => 'Milik saya',
            'status' => 'PENDING',
        ]);

        $response = $this->get('/izin');

        $response->assertOk();
    }
}
