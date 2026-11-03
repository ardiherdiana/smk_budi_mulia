# Rencana Magang — Landing Page & CMS Sekolah (SMK Budi Mulia)

Simulasi rencana kegiatan magang selama **30 hari kerja** (6 minggu, 5 hari kerja/minggu), dari
proses awal (analisis kebutuhan) sampai deploy ke production, untuk proyek **Landing Page SMK Budi
Mulia** — situs resmi sekolah satu halaman, panel admin (CMS) untuk mengelola isinya, dan portal
siswa untuk mengunduh berkas SKL. Proyek ini adalah refactor dari situs lama (bundle React + PHP
lepas) menjadi satu aplikasi Laravel.

> **Cara pakai:** kolom "Hari ke-" tinggal dipetakan ke tanggal asli sesuai jadwal magang
> masing-masing (mis. Hari ke-1 = hari pertama masuk). Uraian kegiatan dan output tiap hari bisa
> disalin langsung ke jurnal/absensi magang, disesuaikan redaksinya dengan yang benar-benar
> dikerjakan.

**Progres: 0/30 hari selesai.**

## Minggu 1 — Persiapan & Analisis Kebutuhan (Hari 1–5) ✅

| ✓ | Hari ke- | Kegiatan | Output |
| --- | --- | --- | --- |
| [ ] | 1 | Onboarding: perkenalan dengan pembimbing/tim, penjelasan alur kerja, instalasi tools dasar (PHP 8.3+, Composer, Node.js, MySQL, Git, VS Code) | Environment dev siap, akses repo |
| [ ] | 2 | Audit situs lama: menelusuri 6 halaman, endpoint PHP, dan isi database; mencatat masalah (kata sandi admin di bundle JS, endpoint tulis tanpa autentikasi, kredensial DB terlihat, unggahan berkas tanpa validasi, foto/video base64 di database) | Daftar temuan masalah situs lama |
| [ ] | 3 | Analisis kebutuhan bersama pihak sekolah: konten apa yang perlu tampil (jurusan, profil, kepala sekolah, guru & staf, berita, pendaftaran, kontak) dan siapa yang mengelolanya | Daftar kebutuhan fungsional & peran (pengunjung, siswa, admin) |
| [ ] | 4 | Studi tech stack (Laravel, Inertia.js, React, TypeScript, Tailwind CSS) dan riset pola landing page satu halaman + SEO sisi server; memutuskan batasan hosting bersama (tanpa Node/SSR di server) | Keputusan tech stack final |
| [ ] | 5 | Mempelajari skema database produksi (dump SQL): tabel users, jurusan, postingan, tentang_kami, guru_staff, principals, berkas SKL; merancang ERD dan rencana memindahkan foto/video dari base64 ke file | ERD & strategi migrasi data |

## Minggu 2 — Fondasi Aplikasi, Autentikasi & Migrasi Data (Hari 6–10)

| ✓ | Hari ke- | Kegiatan | Output |
| --- | --- | --- | --- |
| [ ] | 6 | Inisialisasi project Laravel baru, setup `.env`, install dependency (Inertia, React, Tailwind, shadcn/ui), konfigurasi Vite, TypeScript, ESLint, dan database lokal | Project kosong berjalan (`composer dev`) |
| [ ] | 7 | Membuat migration skema produksi (dipertahankan sama dengan database lama) dan model Eloquent: `User`, `Major`, `Post`, `PostMedia`, `About`, `Staff`, `Principal`, `PortalFile` | Migration & model dasar |
| [ ] | 8 | Menulis seeder dari dump produksi dan perintah konversi media base64 → file di `public/media` (`MediaMigrator`, `media:migrate`) | Database ±79 MB menjadi ±0,3 MB, media berupa file |
| [ ] | 9 | Implementasi autentikasi (`LoginController`): login email/username, hash ulang kata sandi lama (`HardenUsers`), pembatasan percobaan login (throttle), regenerasi sesi, middleware `RequireRole` untuk `admin` dan `user` | Login/logout berfungsi per role |
| [ ] | 10 | Setup layout & design system (token warna, tema terang/gelap, komponen shadcn/ui) mengikuti tampilan aplikasi sekolah lain agar seragam; review & perbaikan bug minggu 2 | Layout dasar + tema siap, autentikasi stabil |

## Minggu 3 — Landing Page Publik (Hari 11–15)

| ✓ | Hari ke- | Kegiatan | Output |
| --- | --- | --- | --- |
| [ ] | 11 | `LandingController` + `LandingService` + `ContentPresenter`: menyiapkan data seksi dari database; kerangka halaman satu halaman dan header dengan menu gulir | Halaman `/` memuat data dari server |
| [ ] | 12 | Seksi Beranda (hero, tombol Daftar & Lihat Jurusan) dan Keunggulan (konsep BMW: Bekerja, Melanjutkan, Wirausaha) | Dua seksi pertama selesai & responsif |
| [ ] | 13 | Seksi Jurusan (baris per jurusan, dialog video YouTube) dan Profil (tab konten `tentang_kami`, lightbox foto, visi-misi) | Seksi Jurusan & Profil berfungsi |
| [ ] | 14 | Seksi Kepemimpinan (kepala sekolah saat ini + riwayat) dan Guru & Staf (tab Pimpinan/Guru/Staf, pencarian, tombol muat lebih banyak) | Seksi Kepemimpinan & Guru-Staf berfungsi |
| [ ] | 15 | Seksi Berita (filter tipe/kategori/pencarian, paginasi 9 per halaman), dialog detail berita dengan URL yang bisa dibagikan `/berita/{slug}-{id}`; review progres dengan pembimbing | Seksi Berita + detail berita berfungsi |

## Minggu 4 — Kontak, SEO & Panel Admin (Hari 16–20)

| ✓ | Hari ke- | Kegiatan | Output |
| --- | --- | --- | --- |
| [ ] | 16 | Seksi Pendaftaran (tautan ke situs PPDB) dan Kontak: peta OpenStreetMap, formulir kontak yang mengirim email (`ContactController`, `Mail`), pembatasan kiriman (throttle) | Formulir kontak mengirim email ke sekolah |
| [ ] | 17 | SEO sisi server: `SeoBuilder`, meta, Open Graph, Twitter card, data terstruktur schema.org (`HighSchool`, `NewsArticle`, `VideoObject`), `sitemap.xml`, `robots.txt` | Tautan berita tampil sebagai kartu pratinjau di WhatsApp |
| [ ] | 18 | Pengalihan 301 dari alamat situs lama (`LegacyRedirectController`), URL berita kanonik, pembatasan indeks hanya untuk production | Tautan lama tidak putus, SEO bersih |
| [ ] | 19 | Panel admin: `Admin\DashboardController`, layout admin, CRUD Berita & galeri (upload foto/video lewat `MediaService`, validasi tipe & ukuran berkas) | Halaman "Berita" admin berfungsi |
| [ ] | 20 | CRUD Tentang Kami, Jurusan, Guru & Staf, dan Kepala Sekolah di panel admin; pembatasan tulis (`throttle:admin-writes`) | Seluruh konten situs bisa dikelola dari browser |

## Minggu 5 — Portal Siswa & SKL, Keamanan (Hari 21–25)

| ✓ | Hari ke- | Kegiatan | Output |
| --- | --- | --- | --- |
| [ ] | 21 | Modul SKL admin — akun siswa: daftar, tambah, impor Excel (PhpSpreadsheet), ekspor kredensial, ganti kata sandi satuan & massal | Halaman "Siswa" berfungsi |
| [ ] | 22 | Modul SKL admin — berkas: unggah PDF SKL, bagikan ke siswa terpilih, hapus; penyimpanan di luar `public` (`PortalFileStorage`); perintah impor berkas lama (`ImportPortalUploads`) | Halaman "Berkas" berfungsi |
| [ ] | 23 | Portal siswa `/portal`: siswa hanya melihat & mengunduh berkas miliknya; otorisasi unduhan (`PortalController::download`); ganti kata sandi sendiri (`PasswordController`) | Portal siswa aman & berfungsi |
| [ ] | 24 | Pemilih modul `/menu-utama`, header keamanan (`SecurityHeaders`), cegah cache tombol kembali (`PreventBackHistoryCache`), tinjau ulang celah situs lama agar tidak muncul lagi | Hardening keamanan selesai |
| [ ] | 25 | Tinjau responsif di HP, tema terang/gelap, aksesibilitas dan performa (ukuran gambar, lazy loading); perbaikan bug minggu 5 | Tampilan konsisten di semua ukuran layar |

## Minggu 6 — Testing, Optimasi & Deployment (Hari 26–30)

| ✓ | Hari ke- | Kegiatan | Output |
| --- | --- | --- | --- |
| [ ] | 26 | Menulis & menjalankan automated test (PHPUnit): landing page, SEO, formulir kontak, autentikasi, CMS admin, akses berkas portal, keamanan media, kesamaan skema (`SchemaParityTest`) | Test suite hijau (`composer test`) |
| [ ] | 27 | User Acceptance Testing (UAT) bersama pihak sekolah (admin/TU, guru, kepala sekolah), kumpulkan feedback langsung | Catatan feedback UAT |
| [ ] | 28 | Perbaikan berdasarkan feedback UAT, optimasi performa (query database, build asset frontend) | Revisi pasca-UAT selesai |
| [ ] | 29 | Persiapan deployment ke hosting bersama: konfigurasi `.env` production, build asset (`npm run build`) di lokal, jalankan migrasi, konversi media & impor data produksi, cek `robots.txt`/sitemap | Server production siap |
| [ ] | 30 | Deployment ke production, dokumentasi akhir (README, panduan admin), demo & serah terima ke pihak sekolah, laporan akhir magang | Situs live + serah terima proyek |

## Catatan

- Rencana ini disusun berdasarkan fitur-fitur nyata yang ada di proyek (landing page sembilan
  seksi, SEO sisi server, CMS admin, portal SKL siswa, migrasi data produksi, tema terang/gelap)
  supaya realistis dipakai sebagai isi jurnal/absensi magang.
- Modul **Classroom** (kelas, tugas, ujian, nilai) tidak dimasukkan karena merupakan proyek
  terpisah dari landing page; kalau mau dimasukkan, tambahkan sebagai minggu ekstra setelah Hari 25.
- Kalau magang berjalan lebih longgar (mis. libur di tengah, revisi lebih lama), urutan minggu
  boleh digeser — bagian yang penting adalah urutan tahapannya: **audit → desain data & migrasi →
  autentikasi → halaman publik → SEO & CMS → portal siswa → testing → deploy**.
