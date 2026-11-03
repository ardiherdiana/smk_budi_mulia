<?php

namespace Tests\Feature;

use App\Models\PortalFile;
use App\Models\User;
use Database\Seeders\ProductionDataSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CommandsAndSeederTest extends TestCase
{
    public function test_harden_command_hashes_legacy_passwords_and_wipes_plaintext_column(): void
    {
        DB::table('users')->insert([
            ['email' => 'legacy@x.com', 'password' => 'admin123', 'password_plain' => 'admin123', 'role' => 'admin'],
            ['email' => 'siswa@x.com', 'password' => password_hash('rahasia', PASSWORD_BCRYPT), 'password_plain' => 'rahasia', 'role' => 'user'],
        ]);

        $this->artisan('users:harden --dry-run')->assertSuccessful();
        $this->assertSame('rahasia', DB::table('users')->where('email', 'siswa@x.com')->value('password_plain')); // dry-run tidak mengubah

        $this->artisan('users:harden')->assertSuccessful();

        $this->assertSame(0, DB::table('users')->whereNotNull('password_plain')->count());
        $legacy = User::where('email', 'legacy@x.com')->first();
        $this->assertTrue(Hash::check('admin123', $legacy->password));
        $this->assertTrue(Hash::check('rahasia', User::where('email', 'siswa@x.com')->value('password'))); // hash lama tetap valid
    }

    public function test_import_uploads_copies_only_real_pdfs_listed_in_the_database(): void
    {
        $source = sys_get_temp_dir().'/import_'.uniqid();
        mkdir($source);
        file_put_contents("$source/1_asli.pdf", "%PDF-1.4\nisi");
        file_put_contents("$source/2_palsu.pdf", '<?php system($_GET[1]);');
        file_put_contents("$source/shell.php", '<?php echo 1;');
        file_put_contents("$source/tak-terdaftar.pdf", "%PDF-1.4\nyatim");

        PortalFile::create(['user_id' => null, 'nama_file' => 'a.pdf', 'path_file' => 'uploads/1_asli.pdf']);
        PortalFile::create(['user_id' => null, 'nama_file' => 'b.pdf', 'path_file' => 'uploads/2_palsu.pdf']);
        PortalFile::create(['user_id' => null, 'nama_file' => 'c.pdf', 'path_file' => 'uploads/hilang.pdf']);

        $this->artisan('portal:import-uploads', ['source' => $source])->assertSuccessful();

        Storage::disk('local')->assertExists('uploads/1_asli.pdf');
        Storage::disk('local')->assertMissing('uploads/2_palsu.pdf');
        Storage::disk('local')->assertMissing('uploads/shell.php');
        Storage::disk('local')->assertMissing('uploads/tak-terdaftar.pdf');
    }

    public function test_production_seeder_replaces_tables_with_rows_from_the_php_data_file(): void
    {
        DB::table('jurusan')->insert(['id' => 99, 'name' => 'Sisa lama']);
        $berkas = $this->berkasData([
            'users' => [
                'kolom' => ['id', 'email', 'password', 'role'],
                'baris' => [[7, 'admin@x.com', password_hash('rahasia', PASSWORD_BCRYPT), 'admin']],
            ],
            'jurusan' => [
                'kolom' => ['id', 'name', 'description', 'image'],
                'baris' => [
                    [1, 'TKJ', "baris satu\nbaris dua; 'kutip' \"ganda\" \$dolar \\ miring", 'major-image/tkj.webp'],
                    [2, 'RPL', null, null],
                ],
            ],
        ]);

        $this->seederTanpaTruncate($berkas)->run();

        $this->assertSame([1, 2], DB::table('jurusan')->orderBy('id')->pluck('id')->all()); // baris lama terhapus
        $this->assertSame("baris satu\nbaris dua; 'kutip' \"ganda\" \$dolar \\ miring", DB::table('jurusan')->where('id', 1)->value('description'));
        $this->assertNull(DB::table('jurusan')->where('id', 2)->value('image'));
        $admin = DB::table('users')->where('id', 7)->first();
        $this->assertSame('admin', $admin->role);
        $this->assertTrue(Hash::check('rahasia', $admin->password));
        $this->assertNull($admin->password_plain); // kolom lama tidak pernah ditulis
    }

    public function test_production_seeder_is_idempotent(): void
    {
        $berkas = $this->berkasData(['jurusan' => ['kolom' => ['id', 'name'], 'baris' => [[1, 'TKJ'], [2, 'RPL']]]]);

        $this->seederTanpaTruncate($berkas)->run();
        $this->seederTanpaTruncate($berkas)->run();

        $this->assertSame(2, DB::table('jurusan')->count());
    }

    public function test_production_seeder_aborts_when_the_data_file_is_missing(): void
    {
        $this->expectException(HttpException::class);

        (new ProductionDataSeeder(sys_get_temp_dir().'/tidak-ada-'.uniqid().'.php'))->run();
    }

    /** @param  array<string, mixed>  $data */
    private function berkasData(array $data): string
    {
        $path = sys_get_temp_dir().'/produksi_'.uniqid().'.php';
        file_put_contents($path, '<?php return '.var_export($data, true).';');
        $this->beforeApplicationDestroyed(fn () => @unlink($path));

        return $path;
    }

    /** TRUNCATE memaksa commit sehingga merusak transaksi RefreshDatabase; DELETE cukup untuk tes. */
    private function seederTanpaTruncate(string $berkas): ProductionDataSeeder
    {
        return new class($berkas) extends ProductionDataSeeder
        {
            protected function kosongkan(string $table): void
            {
                DB::table($table)->delete();
            }
        };
    }
}
