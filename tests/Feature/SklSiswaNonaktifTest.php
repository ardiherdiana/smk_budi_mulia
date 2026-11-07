<?php

namespace Tests\Feature;

use App\Models\PortalFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SklSiswaNonaktifTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['school.skl_siswa_aktif' => false]);
    }

    public function test_siswa_dialihkan_dari_portal_dan_unduhan(): void
    {
        $siswa = $this->student();
        Storage::disk('local')->put('uploads/skl.pdf', "%PDF-1.4\n%%EOF");
        $file = PortalFile::create(['user_id' => $siswa->id, 'nama_file' => 'SKL.pdf', 'path_file' => 'uploads/skl.pdf']);

        $this->actingAs($siswa)->get('/portal')->assertRedirect(route('menu'));
        $this->actingAs($siswa)->get(route('portal.download', $file))->assertRedirect(route('menu'));
    }

    public function test_kartu_skl_tidak_muncul_untuk_siswa_tetapi_tetap_untuk_admin(): void
    {
        // Tanpa kartu SKL alumni hanya punya Profil, jadi langsung diarahkan ke sana.
        $this->actingAs($this->student())->get('/menu-utama')->assertRedirect('/profil');

        $this->actingAs($this->admin())->get('/menu-utama')->assertInertia(fn (Assert $page) => $page
            ->component('module-picker-page')
            ->where('modul.1.key', 'skl'));
    }

    public function test_admin_tetap_bisa_mengunduh_berkas(): void
    {
        Storage::disk('local')->put('uploads/skl.pdf', "%PDF-1.4\n%%EOF");
        $file = PortalFile::create(['user_id' => null, 'nama_file' => 'SKL.pdf', 'path_file' => 'uploads/skl.pdf']);

        $this->actingAs($this->admin())->get(route('portal.download', $file))->assertOk();
    }
}
