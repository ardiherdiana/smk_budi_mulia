<?php

namespace Tests\Feature;

use App\Models\PortalFile;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PortalFileAccessTest extends TestCase
{
    private function file(?User $owner, string $path = 'uploads/1700000000_abc.pdf', string $content = "%PDF-1.4\nisi"): PortalFile
    {
        Storage::disk('local')->put($path, $content);

        return PortalFile::create(['user_id' => $owner?->id, 'nama_file' => 'SKL Budi.pdf', 'path_file' => $path]);
    }

    public function test_owner_can_download_their_file_as_pdf(): void
    {
        $student = $this->student();
        $file = $this->file($student);

        $response = $this->actingAs($student)->get(route('portal.download', $file));

        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('SKL Budi.pdf', $response->headers->get('content-disposition'));
        $this->assertStringContainsString('no-store', $response->headers->get('cache-control'));
    }

    public function test_another_student_cannot_download_it(): void
    {
        $file = $this->file($this->student('pemilik@test.local'));

        $this->actingAs($this->student('lain@test.local'))->get(route('portal.download', $file))->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $file = $this->file($this->student());

        $this->get(route('portal.download', $file))->assertRedirect('/login');
    }

    public function test_admin_can_download_any_file_including_warehouse_files(): void
    {
        $file = $this->file(null);

        $this->actingAs($this->admin())->get(route('portal.download', $file))->assertOk();
    }

    public function test_files_are_not_reachable_by_direct_url(): void
    {
        $this->file($this->student());

        // `/uploads/{path}` sekarang rute absensi (foto guru, lampiran izin) di disk `public`, khusus staf: tamu
        // dialihkan ke login dan staf pun tidak menemukan PDF SKL (disimpan di disk `local`, storage/app/private).
        $this->get('/uploads/1700000000_abc.pdf')->assertRedirect('/login');
        $this->actingAs($this->admin())->get('/uploads/1700000000_abc.pdf')->assertNotFound();
        $this->get('/storage/uploads/1700000000_abc.pdf')->assertNotFound();
    }

    #[DataProvider('unsafePaths')]
    public function test_database_paths_outside_uploads_or_not_pdf_are_never_served(string $path): void
    {
        $student = $this->student();
        $file = PortalFile::create(['user_id' => $student->id, 'nama_file' => 'x.pdf', 'path_file' => $path]);
        Storage::disk('local')->put('rahasia.txt', 'rahasia');
        Storage::disk('local')->put('uploads/shell.php', '<?php echo 1;');

        $this->actingAs($student)->get(route('portal.download', $file))->assertNotFound();
    }

    public static function unsafePaths(): array
    {
        return [
            'traversal' => ['uploads/../rahasia.txt'],
            'absolute' => ['/etc/passwd'],
            'other folder' => ['rahasia.txt'],
            'php upload' => ['uploads/shell.php'],
            'backslash traversal' => ['uploads\\..\\rahasia.txt'],
        ];
    }

    public function test_student_portal_lists_only_own_files(): void
    {
        $me = $this->student('saya@test.local');
        $this->file($me, 'uploads/1_a.pdf');
        $this->file($this->student('orang@test.local'), 'uploads/2_b.pdf');

        $this->actingAs($me)->get('/portal')->assertOk()->assertInertia(fn ($page) => $page
            ->component('portal-page')
            ->has('files', 1)
            ->where('files.0.available', true)
        );
    }

    public function test_missing_physical_file_is_flagged_not_a_server_error(): void
    {
        $student = $this->student();
        $file = PortalFile::create(['user_id' => $student->id, 'nama_file' => 'hilang.pdf', 'path_file' => 'uploads/hilang.pdf']);

        $this->actingAs($student)->get('/portal')->assertInertia(fn ($page) => $page->where('files.0.available', false));
        $this->actingAs($student)->get(route('portal.download', $file))->assertNotFound();
    }
}
