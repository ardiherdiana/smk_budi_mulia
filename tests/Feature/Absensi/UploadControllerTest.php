<?php

namespace Tests\Feature\Absensi;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\AbsensiTestCase;

class UploadControllerTest extends AbsensiTestCase
{
    public function test_an_uploaded_public_file_can_be_fetched(): void
    {
        Storage::fake('public');
        Storage::disk('public')->putFileAs('guru', UploadedFile::fake()->image('foto.png'), 'foto.png');

        $response = $this->actingAs(User::factory()->create())->get('/uploads/guru/foto.png');

        $response->assertOk();
    }

    public function test_uploads_require_login(): void
    {
        Storage::fake('public');
        Storage::disk('public')->putFileAs('guru', UploadedFile::fake()->image('foto.png'), 'foto.png');

        $this->get('/uploads/guru/foto.png')->assertRedirect('/login');
    }

    public function test_a_missing_upload_returns_404(): void
    {
        Storage::fake('public');

        $response = $this->actingAs(User::factory()->create())->get('/uploads/guru/does-not-exist.jpg');

        $response->assertStatus(404);
    }
}
