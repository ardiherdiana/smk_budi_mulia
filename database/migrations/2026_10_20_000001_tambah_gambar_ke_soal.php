<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Gambar pada soal. `gambar` = daftar path gambar di teks soal; `opsiGambar` = sejajar dengan `opsi`,
     * berisi path gambar tiap pilihan atau null. Path memakai format MediaService (`soal/<ulid>.<ext>`) dan boleh
     * dipakai bersama beberapa soal (wacana yang sama).
     */
    public function up(): void
    {
        Schema::table('soal', function (Blueprint $table) {
            $table->json('gambar')->nullable()->after('teks');
            $table->json('opsiGambar')->nullable()->after('opsi');
        });
    }

    public function down(): void
    {
        Schema::table('soal', function (Blueprint $table) {
            $table->dropColumn(['gambar', 'opsiGambar']);
        });
    }
};
