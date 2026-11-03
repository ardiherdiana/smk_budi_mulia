<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Token akses ujian (opsional per ujian): siswa memasukkannya saat memulai percobaan. Null = ujian tanpa token.
        Schema::table('classroom_posts', function (Blueprint $table) {
            $table->string('tokenAkses', 8)->nullable()->after('tampilkanNilai');
        });
    }

    public function down(): void
    {
        Schema::table('classroom_posts', function (Blueprint $table) {
            $table->dropColumn('tokenAkses');
        });
    }
};
