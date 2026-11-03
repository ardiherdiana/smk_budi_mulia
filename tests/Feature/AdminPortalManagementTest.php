<?php

namespace Tests\Feature;

use App\Models\PortalFile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminPortalManagementTest extends TestCase
{
    public function test_bulk_registration_hashes_passwords_and_never_stores_plaintext(): void
    {
        $this->actingAsAdmin();

        $response = $this->post('/skl/siswa', ['emails' => "a@x.com\nB@x.com\na@x.com\nbukan-email\n\n", 'password' => '']);

        $response->assertSessionHas('credentials', fn ($c) => count($c) === 2 && strlen($c[0]['password']) === 10);
        $credentials = session('credentials');

        foreach ($credentials as $credential) {
            $row = DB::table('users')->where('email', $credential['email'])->first();
            $this->assertSame('user', $row->role);
            $this->assertNull($row->password_plain);
            $this->assertTrue(Hash::check($credential['password'], $row->password));
        }
        $this->assertDatabaseHas('users', ['email' => 'b@x.com']); // huruf kecil
    }

    public function test_bulk_registration_with_shared_password_and_existing_emails(): void
    {
        $this->actingAsAdmin();
        $this->student('ada@x.com');

        $this->post('/skl/siswa', ['emails' => "ada@x.com\nbaru@x.com", 'password' => 'Sama12345']);

        $this->assertCount(1, session('credentials'));
        $this->assertTrue(Hash::check('Sama12345', User::where('email', 'baru@x.com')->value('password')));
    }

    public function test_short_shared_password_is_rejected(): void
    {
        $this->actingAsAdmin();

        $this->post('/skl/siswa', ['emails' => 'a@x.com', 'password' => 'pendek'])->assertSessionHasErrors('password');
        $this->assertDatabaseMissing('users', ['email' => 'a@x.com']);
    }

    public function test_admin_can_reset_password_for_one_or_many_but_not_for_admin_accounts(): void
    {
        $admin = $this->actingAsAdmin();
        $a = $this->student('a@x.com', 'Lama12345');
        $b = $this->student('b@x.com', 'Lama12345');

        $this->patch("/skl/siswa/{$a->id}/password", ['password' => 'BaruBanget1'])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('BaruBanget1', User::find($a->id)->password));

        $this->patch('/skl/siswa/password', ['ids' => [$a->id, $b->id, $admin->id], 'password' => 'Massal12345']);
        $this->assertTrue(Hash::check('Massal12345', User::find($b->id)->password));
        $this->assertFalse(Hash::check('Massal12345', User::find($admin->id)->password)); // admin tidak ikut

        $this->patch("/skl/siswa/{$admin->id}/password", ['password' => 'Coba123456'])->assertNotFound();
    }

    public function test_csv_export_has_no_passwords_and_import_resets_them(): void
    {
        $this->actingAsAdmin();
        $a = $this->student('a@x.com', 'Lama12345');
        $b = $this->student('b@x.com', 'Lama12345');

        $export = $this->get('/skl/siswa/ekspor')->assertOk();
        $csv = $export->streamedContent();
        $this->assertStringContainsString('a@x.com', $csv);
        $this->assertStringNotContainsString('Lama12345', $csv);

        $upload = UploadedFile::fake()->createWithContent('akun.csv', "ID,Email,Password Baru\n{$a->id},a@x.com,Impor12345\n{$b->id},b@x.com,\n999999,x@x.com,Impor12345\n");
        $this->post('/skl/siswa/impor', ['csv' => $upload])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('Impor12345', User::find($a->id)->password));
        $this->assertTrue(Hash::check('Lama12345', User::find($b->id)->password)); // kosong = dilewati
    }

    public function test_deleting_a_student_removes_their_pdfs_from_disk(): void
    {
        $this->actingAsAdmin();
        $student = $this->student();
        Storage::disk('local')->put('uploads/1_a.pdf', '%PDF-1.4');
        PortalFile::create(['user_id' => $student->id, 'nama_file' => 'a.pdf', 'path_file' => 'uploads/1_a.pdf']);

        $this->delete("/skl/siswa/{$student->id}")->assertSessionHasNoErrors();

        Storage::disk('local')->assertMissing('uploads/1_a.pdf');
        $this->assertDatabaseCount('files', 0);
        $this->assertDatabaseMissing('users', ['id' => $student->id]);
    }

    public function test_pdf_upload_stores_privately_with_random_name_and_can_be_assigned(): void
    {
        $this->actingAsAdmin();
        $student = $this->student();
        $pdf = UploadedFile::fake()->createWithContent('SKL ../../evil.pdf', "%PDF-1.4\nisi pdf");

        $this->post('/skl/berkas', ['files' => [$pdf]])->assertSessionHasNoErrors();

        $file = PortalFile::firstOrFail();
        $this->assertNull($file->user_id);
        $this->assertMatchesRegularExpression('#^uploads/\d+_[A-Za-z0-9]{12}\.pdf$#', $file->path_file);
        $this->assertStringNotContainsString('/', $file->nama_file);
        Storage::disk('local')->assertExists($file->path_file);

        $this->patch('/skl/berkas/bagikan', ['ids' => [$file->id], 'user_id' => $student->id])->assertSessionHasNoErrors();
        $this->assertSame($student->id, $file->fresh()->user_id);

        $this->patch('/skl/berkas/bagikan', ['ids' => [$file->id], 'user_id' => null]);
        $this->assertNull($file->fresh()->user_id);
    }

    public function test_upload_rejects_non_pdf_content_even_with_pdf_extension(): void
    {
        $this->actingAsAdmin();

        $fake = UploadedFile::fake()->createWithContent('penyamaran.pdf', '<?php system($_GET["c"]); ?>');
        $this->post('/skl/berkas', ['files' => [$fake]])->assertSessionHasErrors();

        $html = UploadedFile::fake()->createWithContent('x.pdf', '<html><script>alert(1)</script></html>');
        $this->post('/skl/berkas', ['files' => [$html]])->assertSessionHasErrors();

        $this->assertDatabaseCount('files', 0);
        $this->assertSame([], Storage::disk('local')->allFiles('uploads'));
    }

    public function test_upload_cannot_target_an_admin_account(): void
    {
        $admin = $this->actingAsAdmin();
        $pdf = UploadedFile::fake()->createWithContent('a.pdf', '%PDF-1.4');

        $this->post('/skl/berkas', ['files' => [$pdf], 'user_id' => $admin->id])->assertSessionHasErrors('user_id');
    }

    public function test_deleting_a_file_removes_row_and_pdf(): void
    {
        $this->actingAsAdmin();
        Storage::disk('local')->put('uploads/9_z.pdf', '%PDF-1.4');
        $file = PortalFile::create(['user_id' => null, 'nama_file' => 'z.pdf', 'path_file' => 'uploads/9_z.pdf']);

        $this->delete("/skl/berkas/{$file->id}");

        Storage::disk('local')->assertMissing('uploads/9_z.pdf');
        $this->assertDatabaseCount('files', 0);
    }

    public function test_legacy_warehouse_account_counts_as_unassigned_and_is_hidden_from_student_list(): void
    {
        $this->actingAsAdmin();
        $gudang = User::create(['email' => 'gudang@system.internal', 'password' => 'acak-acak-1', 'role' => 'admin']);
        PortalFile::create(['user_id' => $gudang->id, 'nama_file' => 'a.pdf', 'path_file' => 'uploads/a.pdf']);
        PortalFile::create(['user_id' => null, 'nama_file' => 'b.pdf', 'path_file' => 'uploads/b.pdf']);
        PortalFile::create(['user_id' => $this->student()->id, 'nama_file' => 'c.pdf', 'path_file' => 'uploads/c.pdf']);

        $this->get('/skl/berkas?scope=gudang')->assertInertia(fn ($page) => $page->where('files.total', 2));
        $this->get('/skl/siswa')->assertInertia(fn ($page) => $page->where('users.total', 1));
    }
}
