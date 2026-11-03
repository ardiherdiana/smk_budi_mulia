<?php

namespace Tests\Feature;

use App\Services\MediaMigrator;
use App\Services\MediaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

/**
 * Migrator diuji pada tabel TEMPORARY supaya tidak memicu DDL pada tabel asli (DDL di MySQL meng-commit
 * transaksi RefreshDatabase). SQL `ALTER ... MODIFY` sudah dijalankan oleh `migrate:fresh` di setiap test run.
 */
class MediaMigratorTest extends TestCase
{
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    private MediaMigrator $migrator;

    protected function setUp(): void
    {
        parent::setUp();

        DB::statement('CREATE TEMPORARY TABLE tmp_media (id VARCHAR(50) PRIMARY KEY, blob_col LONGTEXT NULL)');
        $this->migrator = $this->migratorFor(raw: false);
    }

    private function migratorFor(bool $raw, string $widen = 'LONGTEXT'): MediaMigrator
    {
        return new MediaMigrator(app(MediaService::class), [
            ['table' => 'tmp_media', 'column' => 'blob_col', 'kind' => 'post-image', 'widen' => $widen, 'raw' => $raw],
        ]);
    }

    private function row(string $id, ?string $value): void
    {
        DB::table('tmp_media')->insert(['id' => $id, 'blob_col' => $value]);
    }

    private function column(string $id): ?string
    {
        return DB::table('tmp_media')->where('id', $id)->value('blob_col');
    }

    private function png(): string
    {
        return base64_decode(self::PNG);
    }

    private function jpeg(): string
    {
        return "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00\xFF\xD9";
    }

    private function mp4(): string
    {
        return pack('N', 24).'ftypmp42'.str_repeat(chr(0), 4).'mp42isom'.str_repeat(chr(1), 64);
    }

    public function test_data_uris_raw_base64_and_wrong_mimes_become_files_with_the_real_extension(): void
    {
        $this->row('a', 'data:image/png;base64,'.self::PNG);
        $this->row('b', base64_encode($this->jpeg())); // tanpa awalan data-URI
        $this->row('c', 'data:application/octet-stream;base64,'.self::PNG); // MIME lama salah, isinya PNG
        $this->row('d', 'data:video/mp4;base64,'.base64_encode($this->mp4()));
        $this->row('e', null);
        $this->row('f', '');

        $report = $this->migrator->toFiles()[0];

        $this->assertSame(4, $report['converted']);
        $this->assertSame(1, $report['emptied']);
        $this->assertSame(0, $report['rejected']);

        $this->assertMatchesRegularExpression('#^post-image/a-[0-9a-f]{8}\.png$#', $this->column('a'));
        $this->assertMatchesRegularExpression('#^post-image/b-[0-9a-f]{8}\.jpg$#', $this->column('b'));
        $this->assertMatchesRegularExpression('#^post-image/c-[0-9a-f]{8}\.png$#', $this->column('c'));
        $this->assertMatchesRegularExpression('#^post-image/d-[0-9a-f]{8}\.mp4$#', $this->column('d'));
        $this->assertNull($this->column('e'));
        $this->assertNull($this->column('f'));

        $disk = Storage::disk('media');
        $this->assertSame($this->png(), $disk->get($this->column('a')));
        $this->assertSame($this->jpeg(), $disk->get($this->column('b')));
        $this->assertSame($this->png(), $disk->get($this->column('c')));
        $this->assertSame($this->mp4(), $disk->get($this->column('d')));
    }

    public function test_running_twice_changes_nothing(): void
    {
        $this->row('a', 'data:image/png;base64,'.self::PNG);
        $this->row('b', base64_encode($this->jpeg()));

        $this->migrator->toFiles();
        $before = [$this->column('a'), $this->column('b')];
        $filesBefore = Storage::disk('media')->allFiles();

        $report = $this->migrator->toFiles()[0];

        $this->assertSame(0, $report['converted']);
        $this->assertSame(2, $report['already']);
        $this->assertSame($before, [$this->column('a'), $this->column('b')]);
        $this->assertEqualsCanonicalizing($filesBefore, Storage::disk('media')->allFiles());
    }

    public function test_dry_run_reports_but_writes_nothing(): void
    {
        $this->row('a', 'data:image/png;base64,'.self::PNG);
        $this->row('f', '');

        $report = $this->migrator->toFiles(dryRun: true)[0];

        $this->assertSame(1, $report['converted']);
        $this->assertSame(1, $report['emptied']);
        $this->assertSame('data:image/png;base64,'.self::PNG, $this->column('a'));
        $this->assertSame('', $this->column('f'));
        $this->assertSame([], Storage::disk('media')->allFiles());
    }

    public function test_content_that_is_not_an_image_or_video_is_kept_privately_and_never_published(): void
    {
        $this->row('html', '<html><script>alert(1)</script></html>');
        $this->row('php', 'data:image/png;base64,'.base64_encode('<?php system($_GET["c"]); ?>'));
        $this->row('svg', 'data:image/svg+xml;base64,'.base64_encode('<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"/>'));

        $report = $this->migrator->toFiles()[0];

        $this->assertSame(3, $report['rejected']);
        $this->assertSame(0, $report['converted']);
        foreach (['html', 'php', 'svg'] as $id) {
            $this->assertNull($this->column($id));
            Storage::disk('local')->assertExists("media-rejected/tmp_media-{$id}-blob_col.txt");
        }
        $this->assertSame([], Storage::disk('media')->allFiles()); // tidak ada yang dipublikasikan
    }

    public function test_narrow_refuses_while_any_row_is_not_a_path(): void
    {
        $this->row('a', 'data:image/png;base64,'.self::PNG);
        $this->row('b', 'post-image/ok.png');
        $this->row('c', str_repeat('x', 300)); // terlalu panjang untuk jadi path

        $this->assertSame(2, $this->migrator->nonPathRows());

        $this->expectException(RuntimeException::class);
        $this->migrator->narrow();
    }

    public function test_after_conversion_no_row_is_left_that_would_be_truncated(): void
    {
        $this->row('a', 'data:image/png;base64,'.self::PNG);
        $this->row('b', '<html>');
        $this->row('c', null);

        $this->migrator->toFiles();

        $this->assertSame(0, $this->migrator->nonPathRows());
    }

    public function test_rollback_puts_the_same_bytes_back_as_data_uris(): void
    {
        $this->row('a', 'data:image/png;base64,'.self::PNG);
        $this->row('b', base64_encode($this->jpeg()));
        $this->migrator->toFiles();

        $report = $this->migrator->toBlobs()[0];

        $this->assertSame(2, $report['embedded']);
        $this->assertSame('data:image/png;base64,'.self::PNG, $this->column('a'));
        $this->assertSame('data:image/jpeg;base64,'.base64_encode($this->jpeg()), $this->column('b'));
    }

    public function test_rollback_reports_and_clears_rows_whose_file_is_missing(): void
    {
        $this->row('a', 'data:image/png;base64,'.self::PNG);
        $this->migrator->toFiles();
        Storage::disk('media')->delete($this->column('a'));

        $report = $this->migrator->toBlobs()[0];

        $this->assertSame(1, $report['missing']);
        $this->assertNull($this->column('a'));
    }

    public function test_raw_columns_round_trip_without_a_data_uri_prefix(): void
    {
        $migrator = $this->migratorFor(raw: true);
        $this->row('a', self::PNG); // format principals.foto: base64 mentah
        $migrator->toFiles();

        $this->assertMatchesRegularExpression('#^post-image/a-[0-9a-f]{8}\.png$#', $this->column('a'));

        $migrator->toBlobs();
        $this->assertSame(self::PNG, $this->column('a'));
    }

    public function test_rollback_fits_a_photo_truncated_at_the_text_limit_and_drops_what_cannot_fit(): void
    {
        $migrator = $this->migratorFor(raw: true, widen: 'TEXT');
        // 49151 byte -> base64 65536 karakter (dengan `=`), 1 di atas batas TEXT; tanpa padding pas 65535.
        $fits = $this->jpeg().str_repeat('a', 49151 - strlen($this->jpeg()));
        $tooBig = $this->jpeg().str_repeat('a', 60000);
        $this->row('fits', base64_encode($fits));
        $this->row('big', base64_encode($tooBig));
        $migrator->toFiles();

        $report = $migrator->toBlobs()[0];

        $this->assertSame(1, $report['embedded']);
        $this->assertSame(1, $report['oversize']);
        $this->assertSame(65535, strlen($this->column('fits')));
        $this->assertSame($fits, base64_decode($this->column('fits'), true));
        $this->assertNull($this->column('big'));
    }
}
