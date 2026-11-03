<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Menautkan akun guru Classroom ke orangnya di `guru_staff` (tabel produksi yang dikunci, tidak diubah), supaya foto dan
        // tampil-di-#guru satu sumber. Null = akun tanpa baris di website. Orangnya tetap di website bila akunnya dihapus.
        Schema::table('profil_akun', function (Blueprint $table) {
            $table->integer('guruStaffId')->nullable()->after('absensi_user_id');
            $table->unique('guruStaffId', 'profil_akun_guru_staff_key');
            $table->foreign('guruStaffId', 'profil_akun_guru_staff_fkey')->references('id')->on('guru_staff')->nullOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::table('profil_akun', function (Blueprint $table) {
            $table->dropForeign('profil_akun_guru_staff_fkey');
            $table->dropUnique('profil_akun_guru_staff_key');
            $table->dropColumn('guruStaffId');
        });
    }
};
