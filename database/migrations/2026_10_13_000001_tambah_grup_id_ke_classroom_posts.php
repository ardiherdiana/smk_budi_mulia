<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Postingan yang diterbitkan sekaligus ke beberapa ruang kelas menjadi satu baris per kelas dengan `grupId` yang sama.
        // Null berarti postingan itu berdiri sendiri.
        Schema::table('classroom_posts', function (Blueprint $table) {
            $table->string('grupId', 191)->nullable()->after('kelasId');
            $table->index('grupId', 'posts_grupId_idx');
        });
    }

    public function down(): void
    {
        Schema::table('classroom_posts', function (Blueprint $table) {
            $table->dropIndex('posts_grupId_idx');
            $table->dropColumn('grupId');
        });
    }
};
