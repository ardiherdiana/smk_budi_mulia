<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Skema hasil migration HARUS identik dengan produksi (u282680727_db_sekolah.sql), dengan SATU pengecualian
 * yang disengaja: 7 kolom foto/video kini menyimpan path file (VARCHAR(500)), bukan base64 (LONGTEXT/LONGBLOB).
 * Fixture dihasilkan dari information_schema database yang di-import dari dump.
 */
class SchemaParityTest extends TestCase
{
    /** Kolom yang dulu menyimpan blob base64 dan sekarang menyimpan path (lihat MediaMigrator::TARGETS). */
    /** Tabel produksi saja; tabel Classroom (migrasi `create_classroom_tables`) sengaja tidak ikut dibandingkan. */
    private const PRODUCTION_TABLES_SQL = "'files','guru_staff','jurusan','postingan','postingan_media','principals','tentang_kami','tentang_kami_media','users'";

    private const MEDIA_COLUMNS = [
        'postingan.image', 'postingan.video_file', 'postingan_media.file', 'tentang_kami_media.file',
        'jurusan.image', 'guru_staff.image', 'principals.foto',
    ];

    public function test_schema_matches_production_dump_except_media_path_columns(): void
    {
        $schema = DB::connection()->getDatabaseName();

        $rows = [];
        foreach ([
            "select table_name,ordinal_position,column_name,column_type,is_nullable,ifnull(column_default,'<NULL>'),ifnull(character_set_name,''),ifnull(collation_name,''),extra,column_key from information_schema.columns where table_schema=? and table_name in (".self::PRODUCTION_TABLES_SQL.') order by 1,2',
            "select '--IDX',table_name,index_name,non_unique,seq_in_index,column_name from information_schema.statistics where table_schema=? and table_name in (".self::PRODUCTION_TABLES_SQL.') order by 2,3,5',
            "select '--FK',table_name,constraint_name,column_name,referenced_table_name,referenced_column_name from information_schema.key_column_usage where table_schema=? and table_name in (".self::PRODUCTION_TABLES_SQL.') and referenced_table_name is not null order by 2,3',
            "select '--FKR',constraint_name,delete_rule,update_rule from information_schema.referential_constraints where constraint_schema=? and table_name in (".self::PRODUCTION_TABLES_SQL.') order by 2',
            "select '--TBL',table_name,engine,table_collation from information_schema.tables where table_schema=? and table_name in (".self::PRODUCTION_TABLES_SQL.') order by 2',
        ] as $query) {
            foreach (DB::select($query, [$schema]) as $row) {
                $rows[] = implode("\t", array_map(fn ($v) => (string) $v, array_values((array) $row)));
            }
        }

        $expected = explode("\n", trim(str_replace("\r\n", "\n", (string) file_get_contents(base_path('tests/Fixtures/production-schema.txt')))));

        // Kolom media dibandingkan terpisah (test di bawah); selain itu harus sama persis.
        $withoutMedia = fn (array $lines): array => array_values(array_filter($lines, function (string $line): bool {
            $cells = explode("\t", $line);

            return ! in_array(($cells[0] ?? '').'.'.($cells[2] ?? ''), self::MEDIA_COLUMNS, true);
        }));

        $this->assertSame($withoutMedia($expected), $withoutMedia($rows));
        $this->assertCount(count($expected), $rows); // tidak ada kolom yang hilang/bertambah
    }

    public function test_media_columns_are_nullable_varchar_paths_not_blobs(): void
    {
        $schema = DB::connection()->getDatabaseName();

        foreach (self::MEDIA_COLUMNS as $qualified) {
            [$table, $column] = explode('.', $qualified);
            $info = DB::selectOne(
                'select column_type as type, is_nullable as nullable, column_default as fallback from information_schema.columns where table_schema=? and table_name=? and column_name=?',
                [$schema, $table, $column],
            );

            $this->assertSame('varchar(500)', $info->type, $qualified);
            $this->assertSame('YES', $info->nullable, $qualified);
            $this->assertNull($info->fallback, $qualified);
        }
    }

    public function test_production_tables_exist_alongside_classroom_tables(): void
    {
        $tables = collect(DB::select('show tables'))->map(fn ($r) => array_values((array) $r)[0])->sort()->values()->all();

        $production = [
            'files', 'guru_staff', 'jurusan', 'postingan', 'postingan_media',
            'principals', 'tentang_kami', 'tentang_kami_media', 'users',
        ];
        $classroom = [
            'classroom_posts', 'jawaban', 'kelas', 'komentar', 'lampiran', 'mapel', 'pengumpulan',
            'percobaan', 'profil_akun', 'rombel', 'siswa', 'soal', 'tahun_ajaran',
        ];
        // Modul absensi + SPPD (migrasi 2026_11_03_000002_create_absensi_tables); tanpa tabel `users` sendiri.
        $absensi = [
            'attendance', 'briefing_attendance', 'guru', 'holidays', 'jadwal_hari', 'leave_requests', 'notifications',
            'push_subscriptions', 'settings',
            'model_has_permissions', 'model_has_roles', 'permissions', 'role_has_permissions', 'roles',
            'laporan_perjalanan_fotos', 'laporan_perjalanans', 'log_audits', 'pencairans', 'pengajuan_pengikut',
            'pengajuan_sppds', 'pengaturans', 'sppd_notifications', 'sppds',
        ];

        $this->assertSame(
            collect([...$production, ...$classroom, ...$absensi, 'migrations'])->sort()->values()->all(),
            $tables,
        );
    }
}
