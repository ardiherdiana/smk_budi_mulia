<?php

use App\Models\About;
use App\Models\Attendance;
use App\Models\BriefingAttendance;
use App\Models\Classroom\Akun;
use App\Models\Classroom\Kelas;
use App\Models\Classroom\Komentar;
use App\Models\Classroom\Mapel;
use App\Models\Classroom\Pengumpulan;
use App\Models\Classroom\Percobaan;
use App\Models\Classroom\Post as PostKelas;
use App\Models\Classroom\Rombel;
use App\Models\Classroom\Siswa;
use App\Models\Classroom\TahunAjaran;
use App\Models\Guru;
use App\Models\Holiday;
use App\Models\JadwalHari;
use App\Models\LeaveRequest;
use App\Models\Major;
use App\Models\PortalFile;
use App\Models\Post;
use App\Models\Principal;
use App\Models\Settings;
use App\Models\Sppd\LaporanPerjalanan;
use App\Models\Sppd\Pencairan;
use App\Models\Sppd\PengajuanSppd;
use App\Models\Sppd\Pengaturan;
use App\Models\Sppd\Sppd;
use App\Models\Staff;
use App\Models\Supervisi\Supervisi;
use App\Models\User;

/*
| Log aktivitas bersama (App\Support\LogAktivitas\Perekam). Hanya model di daftar `model` yang dicatat saat ditambah,
| diubah, atau dihapus lewat Eloquent selama menangani permintaan web; seeder, impor, dan perintah konsol tidak dicatat.
| Anak data yang ramai (skor supervisi, jawaban ujian, lampiran, foto, soal) sengaja tidak didaftarkan; simpan soal
| dicatat satu baris dari controllernya.
|
| `judul`: kolom (boleh relasi bertitik, mis. `guru.nama`) yang dipakai sebagai nama data di keterangan; daftar kolom
| digabung dengan " - ". `grup`: model berbeda yang mewakili data yang sama (akun `users` + profil `profil_akun`, id
| sama), supaya tambah/hapus satu akun tidak tercatat dua kali dalam satu permintaan.
*/

return [
    // Tidak pernah masuk kolom `perubahan`.
    'abaikan_kolom' => ['createdAt', 'updatedAt', 'created_at', 'updated_at', 'remember_token', 'password_plain'],

    // Nilainya tidak disimpan; perubahan hanya ditandai "diubah".
    'kolom_rahasia' => ['password', 'qrToken', 'tokenAkses'],

    'model' => [
        // Website
        Post::class => ['modul' => 'Website', 'label' => 'Berita', 'judul' => 'title'],
        About::class => ['modul' => 'Website', 'label' => 'Tentang Kami', 'judul' => 'title'],
        Major::class => ['modul' => 'Website', 'label' => 'Jurusan', 'judul' => 'name'],
        Staff::class => ['modul' => 'Website', 'label' => 'Guru & Staf', 'judul' => 'name'],
        Principal::class => ['modul' => 'Website', 'label' => 'Kepala Sekolah', 'judul' => 'nama'],

        // SKL
        PortalFile::class => ['modul' => 'SKL', 'label' => 'Berkas SKL', 'judul' => 'nama_file'],

        // Classroom
        Kelas::class => ['modul' => 'Classroom', 'label' => 'Ruang kelas', 'judul' => ['mapel.nama', 'rombel.nama']],
        PostKelas::class => ['modul' => 'Classroom', 'label' => 'Postingan', 'judul' => 'judul'],
        Komentar::class => ['modul' => 'Classroom', 'label' => 'Komentar', 'judul' => 'isi'],
        Pengumpulan::class => ['modul' => 'Classroom', 'label' => 'Pengumpulan tugas', 'judul' => ['post.judul', 'siswa.name']],
        Percobaan::class => ['modul' => 'Classroom', 'label' => 'Pengerjaan ujian', 'judul' => ['post.judul', 'siswa.name']],
        Mapel::class => ['modul' => 'Classroom', 'label' => 'Mata pelajaran', 'judul' => 'nama'],
        Rombel::class => ['modul' => 'Classroom', 'label' => 'Rombel', 'judul' => 'nama'],
        Siswa::class => ['modul' => 'Classroom', 'label' => 'Siswa', 'judul' => 'nama'],
        TahunAjaran::class => ['modul' => 'Classroom', 'label' => 'Tahun ajaran', 'judul' => ['nama', 'semester']],

        // Absensi
        Attendance::class => ['modul' => 'Absensi', 'label' => 'Absensi', 'judul' => ['guru.nama', 'tanggal']],
        BriefingAttendance::class => ['modul' => 'Absensi', 'label' => 'Absen briefing', 'judul' => ['guru.nama', 'tanggal']],
        LeaveRequest::class => ['modul' => 'Absensi', 'label' => 'Izin/sakit', 'judul' => ['guru.nama', 'tanggalMulai']],
        Holiday::class => ['modul' => 'Absensi', 'label' => 'Hari libur', 'judul' => ['keterangan', 'tanggal']],
        JadwalHari::class => ['modul' => 'Absensi', 'label' => 'Jadwal hari', 'judul' => 'hari'],
        Settings::class => ['modul' => 'Absensi', 'label' => 'Pengaturan absensi', 'judul' => 'namaSekolah'],

        // Data Guru
        Guru::class => ['modul' => 'Data Guru', 'label' => 'Guru', 'judul' => 'nama'],

        // SPPD
        PengajuanSppd::class => ['modul' => 'SPPD', 'label' => 'Pengajuan SPPD', 'judul' => ['pemohon.name', 'tujuan']],
        Sppd::class => ['modul' => 'SPPD', 'label' => 'Surat SPPD', 'judul' => 'nomor_sppd'],
        Pencairan::class => ['modul' => 'SPPD', 'label' => 'Pencairan dana', 'judul' => ['jenis', 'jumlah']],
        LaporanPerjalanan::class => ['modul' => 'SPPD', 'label' => 'Laporan perjalanan', 'judul' => 'pengajuan.tujuan'],
        Pengaturan::class => ['modul' => 'SPPD', 'label' => 'Pengaturan SPPD', 'judul' => 'kunci'],

        // Supervisi
        Supervisi::class => ['modul' => 'Supervisi', 'label' => 'Supervisi', 'judul' => ['guru.name', 'tanggal']],

        // Akun
        Akun::class => ['modul' => 'Akun', 'label' => 'Akun', 'judul' => ['name', 'username'], 'grup' => 'akun'],
        User::class => ['modul' => 'Akun', 'label' => 'Akun', 'judul' => ['name', 'email'], 'grup' => 'akun'],
    ],
];
