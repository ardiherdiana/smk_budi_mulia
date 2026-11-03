<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Format soal TKA: bentuk PG / PGK_KATEGORI / PGK_MCMA / ESSAY, plus kompetensi dan sub kompetensi per soal.
     * `kunci` menampung beberapa nilai (MCMA: "0,2,4"; Kategori: "1,0,1" dengan 1 = Benar), jadi dilebarkan.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE soal MODIFY tipe ENUM('PG','PGK_KATEGORI','PGK_MCMA','ESSAY') NOT NULL");

        Schema::table('soal', function (Blueprint $table) {
            $table->string('kunci', 50)->nullable()->change();
            $table->text('kompetensi')->nullable()->after('teks');
            $table->text('subKompetensi')->nullable()->after('kompetensi');
        });
    }

    public function down(): void
    {
        DB::table('soal')->whereIn('tipe', ['PGK_KATEGORI', 'PGK_MCMA'])->delete();

        Schema::table('soal', function (Blueprint $table) {
            $table->dropColumn(['kompetensi', 'subKompetensi']);
            $table->string('kunci', 10)->nullable()->change();
        });

        DB::statement("ALTER TABLE soal MODIFY tipe ENUM('PG','ESSAY') NOT NULL");
    }
};
