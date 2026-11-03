# Landing Page & Portal SMK Budi Mulia Karawang

Situs resmi SMK Budi Mulia Karawang, hasil refactor situs lama (bundle React + puluhan berkas PHP lepas) ke
**Laravel 13, Inertia 3, React 19, Tailwind 4, shadcn (`base-nova`), dan Framer Motion**, dengan design system yang
sama dengan aplikasi `absensi`:

- **Landing page satu halaman**: Beranda, Keunggulan, Jurusan, Profil (Tentang Kami, Visi & Misi), Kepemimpinan,
  Guru & Staf, Berita & Galeri, Pendaftaran, Kontak. Detail berita terbuka sebagai dialog, dan tautan
  `/berita/{slug}-{id}` bisa dibagikan.
- **Panel admin** (`/admin`): kelola berita/galeri, tentang kami, jurusan, guru & staf, kepala sekolah, akun siswa,
  dan berkas SKL.
- **Portal siswa / SKL** (`/portal`): siswa masuk dengan email dan mengunduh PDF miliknya sendiri.
- **Classroom** (`/classroom/kelas`): ruang kelas, materi, tugas, ujian (bentuk soal format TKA: PG, PGK Kategori, PGK MCMA, essay; editor soal satu halaman), dan nilai untuk guru, kepsek, admin, dan siswa. Setelah masuk, `/menu-utama` menampilkan kartu Website, SKL, dan Classroom sesuai peran.
- **SEO sisi server**: meta, Open Graph (pratinjau tautan di WhatsApp/Facebook), data terstruktur schema.org,
  `sitemap.xml`, `robots.txt`. Semuanya dirender di server, jadi terbaca crawler tanpa menjalankan JavaScript; hanya
  produksi yang diindeks.

Database sama dengan produksi (9 tabel produksi dikunci; modul Classroom menambah tabel lewat migrasi sendiri), kecuali tujuh kolom foto/video yang kini berisi path file di
`public/media`, bukan base64 (database turun dari sekitar 79 MB ke 0,3 MB). Tema terang dan gelap. Semua teks
antarmuka berbahasa Indonesia. Panduan pengembang lengkap (skema dikunci, media, keamanan, SEO, data warisan) ada di
[CLAUDE.md](CLAUDE.md), dan dokumen proyek di folder [md/](md/).

## Menjalankan

Prasyarat: PHP 8.4, Composer, Node 20+, MySQL/MariaDB.

```bash
composer install
npm install --ignore-scripts
cp .env.example .env && php artisan key:generate    # sesuaikan DB_* di .env
# buat database kosong `budi_mulia` (dan `landing_page_testing` untuk tes); basis data absensi bernama `absensi`

php artisan migrate     # membuat skema produksi; di database lama juga mengubah media base64 menjadi berkas

# Data produksi (opsional): taruh dump di database/seeders/data/production.sql (gitignored), lalu:
php artisan db:seed --class=ProductionDataSeeder      # foto/video di dump dikonversi jadi file di public/media
php artisan portal:import-uploads <folder portal_download/uploads dari situs lama>   # PDF SKL asli
php artisan users:harden      # hash kata sandi mentah warisan dan kosongkan users.password_plain


# Data Classroom (guru, mapel, rombel, siswa ditulis di kode; lihat bagian "Data Classroom" di bawah):
php artisan db:seed           # tahun ajaran aktif, guru, mapel, rombel, lalu siswa

npm run build                 # atau `npm run dev` untuk HMR
php -d memory_limit=512M -d upload_max_filesize=64M -d post_max_size=80M artisan serve
```

Buka http://localhost:8000. Masuk ke admin dengan akun `role = admin` di tabel `users`. Akun awal warisan situs lama
(`admin@gmail.com`) memakai kata sandi lemah yang pernah bocor: **ganti segera** lewat menu "Ganti Password". Jangan
menulis kredensial di dokumen mana pun.

Catatan lokal:

- `public/hot` tidak boleh tertinggal saat `npm run dev` mati. Berkas itu membuat halaman memuat aset dari server Vite
  yang tidak ada dan tampil kosong. Hapus, atau nyalakan `npm run dev`.
- Pemrosesan gambar bergantung pada GD. PHP lokal tanpa JPEG/WebP menyimpan berkas asli, bukan hasil encode ulang;
  produksi harus punya GD lengkap.
- Situs lama (`landing_page_production`) sudah dihapus dari laptop ini pada 24 September 2026; dump produksinya
  disimpan sebagai `database/seeders/data/production.sql`, dan PDF yang tercatat di database sudah ada di
  `storage/app/private/uploads`.

## Data Classroom (seeder)

`php artisan db:seed` menjalankan `ClassroomSeeder`, yang memanggil berurutan `TahunAjaranSeeder` (2026/2027 ganjil, aktif; tanpa tahun ajaran aktif ruang kelas baru tidak bisa dibuat), `GuruSeeder`, `MapelSeeder`, `RombelSeeder`, dan `SiswaSeeder`. Semuanya aman dijalankan ulang (updateOrCreate).
Guru, mapel, rombel, dan siswa ditulis langsung di kode, tanpa Excel. Satu-satunya berkas yang masih dibaca adalah salinan berkas siswa di `storage/app/private/` (git-ignored, tidak boleh dikomit), hanya untuk `KelasSeeder`:

| Berkas di `storage/app/private/` | Asal | Dipakai oleh |
| --- | --- | --- |
| `data-siswa.xlsx` | "DATA SISWA SMKBM 2627.xlsx" (blok "DAFTAR ABSENSI SISWA KELAS <nama>") | `KelasSeeder` (nama wali kelas) |

- Guru: `GuruSeeder::DAFTAR` berisi 23 guru (nama, username `SMKBM001`..., hash bcrypt sandi, mapel). Username dan sandi meneruskan "DAFTAR GURU MAPEL SMKBM.xlsx", jadi login yang sudah dibagikan tetap berlaku; sandi disimpan sebagai hash, bukan teks biasa. Herianto Saputra (SMKBM028) belum punya sandi di Excel, jadi bersandi acak yang tidak diketahui; atur lewat halaman Data Guru. Staf non-pengajar tidak termasuk. Dijalankan ulang, seeder hanya memperbarui nama; sandi, peran, dan status aktif yang diubah admin tidak ditimpa.
- Rombel: 14 nama di `RombelSeeder::DAFTAR` (`X TKJ 1` ... `XII AKT`), tanpa wali kelas; tingkat dan jurusan diambil dari nama. Rombel yang sudah ada tidak diubah.
- Mapel: 23 nama resmi di `MapelSeeder::DAFTAR`; semua mapel guru di `GuruSeeder::DAFTAR` memakai nama yang sama persis (dijaga `GuruSeederTest`).
- Siswa: 380 siswa di `database/seeders/data/siswa.php` (`[rombel, nama, username 2627NNNN, hash bcrypt]`), diambil dari `kredensial-siswa.xlsx` yang dipakai produksi, jadi login yang sudah dibagikan tetap berlaku. Sheet-nya masih bernama lama, dan sudah dipetakan ke 14 rombel baru (TJKT => TKJ, AKUNTANSI/AKL => AKT, XI BINAAN BNET => XI TKJ 1). Berkas itu memuat nama 380 siswa: jangan didorong ke repositori publik. Seeder tidak lagi menulis Excel kredensial. Dijalankan ulang, hanya nama yang diperbarui; sandi, rombel, dan NIS yang diubah lewat aplikasi tidak ditimpa. Sekitar 9 detik (sandi sementara memakai biaya bcrypt rendah).

### Ruang kelas (`KelasSeeder`) dan reset

Berkas di atas **tidak memuat jadwal mengajar** (guru x rombel x mapel). Satu-satunya kaitan guru dengan rombel adalah nama **wali kelas** di tanda tangan tiap blok `data-siswa.xlsx`. `KelasSeeder` memakainya: tiap rombel mendapat satu ruang kelas untuk setiap mapel milik wali kelasnya (nama wali dicocokkan ke daftar guru, gelar dan singkatan ditoleransi) dan `rombel.waliKelasId` ikut diisi. Hasilnya **perkiraan** (30 ruang kelas dari 14 rombel pada data 2026/2027); guru non-wali dan koreksi ditambahkan lewat tombol "Buat Ruang Kelas". Bila ada nama wali yang tidak ketemu, seeder melewatinya dengan catatan di tabel hasil; tambahkan ke `ALIAS_WALI` di `database/seeders/KelasSeeder.php`. **Catatan:** `SiswaSeeder::bacaWaliKelas` mengembalikan nama rombel di berkas lama (`X TJKT 1`, `XII AKUNTANSI`, ...), sedangkan rombel sekarang bernama baru (`X TKJ 1`, `XII AKT`, ...) dan belum punya wali. Sampai nama-nama itu dipetakan, `KelasSeeder` melewati semua rombel dengan catatan "rombel belum ada".

`KelasSeeder` sengaja **tidak** dipanggil `ClassroomSeeder`: `db:seed` penuh akan menghidupkan lagi ruang kelas yang sengaja dihapus guru. Jalankan eksplisit:

```bash
php artisan classroom:reset-kelas --dry-run   # tampilkan jumlah yang akan dihapus, tidak menghapus
php artisan classroom:reset-kelas             # minta konfirmasi (atau --force tanpa bertanya)
php artisan db:seed --class=KelasSeeder       # buat ulang ruang kelas (aman dijalankan ulang, tidak menimpa guru lain)
```

`classroom:reset-kelas` menghapus semua ruang kelas beserta forum, komentar, tugas, pengumpulan, soal, percobaan dan jawaban ujian, serta berkas lampirannya. Akun (admin, guru, siswa), rombel, siswa, mapel, dan tahun ajaran **tidak disentuh**. Tidak bisa dibatalkan; di produksi jalankan `--dry-run` dulu.

## Perintah

| Perintah | Fungsi |
| --- | --- |
| `composer dev` | Server, pendengar antrean, dan Vite sekaligus |
| `composer test` | PHPUnit (butuh MySQL hidup dan database `landing_page_testing`) |
| `./vendor/bin/pint` | Format PHP |
| `npm run lint` | ESLint |
| `npm run build` | `tsc -b` lalu `vite build`, hasil di `public/build` |
| `php artisan media:migrate-to-files [--dry-run]` | Ubah sisa foto/video base64 di database menjadi berkas (idempoten; `migrate` sudah memanggilnya) |
| `php artisan users:harden [--dry-run]` | Hash kata sandi mentah warisan dan kosongkan `password_plain` |
| `php artisan portal:import-uploads <folder>` | Salin PDF SKL asli (hanya yang tercatat di tabel `files`, header PDF diperiksa) |

Pemeriksaan sebelum selesai (tanpa dev server):

```bash
./vendor/bin/pint
composer test
npm run lint
npm run build
```

## Yang berubah dibanding sistem lama

| Sebelumnya | Sekarang |
| --- | --- |
| Password admin ditulis di dalam bundle JS, login hanya di browser | Login sesi server-side, bcrypt, batas percobaan, sesi diregenerasi |
| Endpoint `postingan.php`, `jurusan.php`, ... terbuka untuk siapa saja (CORS `*`, tanpa auth) | Semua tulis lewat `/admin/*` (role admin + CSRF + rate limit) |
| Kredensial DB tampil di file PHP dan error debug | `.env`, error tidak bocor, `APP_DEBUG=false` di produksi |
| PDF SKL bisa diunduh langsung lewat URL `uploads/...` | Disimpan privat, unduh hanya lewat otorisasi (pemilik/admin) |
| Password siswa disimpan mentah di `password_plain` & di-export | Selalu ter-hash; kredensial baru ditampilkan sekali |
| Upload file apa saja (ada `alfa.php`) | Whitelist tipe berdasarkan isi berkas; gambar di-encode ulang; PDF dicek header |
| Foto & video disimpan sebagai base64 di database (79 MB, halaman berita memuat JSON 73 MB) | Foto & video jadi file di `public/media/`, database hanya menyimpan path; web server yang melayani (cache + Range) |
| 6 halaman terpisah | 1 halaman scroll + dialog, animasi Framer Motion, tema gelap/terang |

Detail teknis dan keputusan keamanan: lihat [CLAUDE.md](CLAUDE.md) dan [md/SECURITY.md](md/SECURITY.md).

## Struktur

```
app/                Controller (publik, Admin/, Auth/, Portal/), Service, Model, Policy, Middleware, Console/Commands
config/school.php   konten sekolah yang tidak punya tabel (visi, misi, kontak, SEO, batas media)
database/           migration DDL mentah (skema produksi) dan seeder dump produksi
public/media/       foto dan video unggahan (dilayani web server langsung)
resources/js/       pages/ (landing, login, portal, admin/), components/landing/, components/ui/, lib/
resources/views/    app.blade.php dan partials/seo.blade.php (meta SEO dari server)
routes/web.php      semua rute (tidak ada API)
tests/              PHPUnit: Feature/ dan Unit/
md/                 dokumen proyek (lihat bagian Dokumentasi)
```

## Desain

Salinan 1:1 design system `absensi`: token warna di `resources/js/index.css`, font Geist, oranye `#ea580c` untuk aksi,
biru `#0e57a6` untuk struktur, radius dasar 10 px, warna solid tanpa gradasi (satu-satunya pengecualian: scrim di atas
foto pada halaman login). Arah desain: ENERGY 2 / RHYTHM 3 / MOTION 2, disaring aturan antislop (tanpa lencana
dekoratif, tanpa angka karangan, tanpa animasi berputar terus). Alasan tiap keputusan ada di [DESIGN.md](DESIGN.md);
token, kontras terukur, dan aturan menambah komponen ada di [md/DESIGN_SYSTEM.md](md/DESIGN_SYSTEM.md). Tema terang dan
gelap diatur `ThemeProvider` (kunci `localStorage` `theme`) dan diterapkan sebelum React dimuat supaya tidak berkedip.

Teks putih di atas oranye hanya 3,41:1, sehingga tombol oranye belum memenuhi AA untuk teks biasa. Temuan dan pilihan
perbaikannya ada di `md/DESIGN_SYSTEM.md` bagian 7 dan menunggu keputusan pemilik.

## Catatan

- Foto dan video ada di `public/media` (bukan `storage:link`, karena hosting bersama sering tidak bisa membuat symlink);
  PDF SKL ada di `storage/app/private/uploads` dan hanya bisa diunduh lewat otorisasi.
- Sesi dan cache memakai driver file, antrean `sync`, dan tidak ada tabel framework. Jangan menambah tabel framework; tabel Classroom ada di migrasi tersendiri.
- Pesan formulir kontak dikirim sebagai email ke `CONTACT_MAIL_TO`, tidak disimpan.
- Aturan git-ignore ada di `.gitignore` akar proyek; folder `md/` tidak punya `.gitignore` sendiri (yang bersarang
  hanya bawaan Laravel di `storage/`, `bootstrap/cache/`, dan `database/`). Dokumen di `md/` ikut git, jadi jangan
  menaruh catatan pribadi atau kredensial di sana (`.gitignore` menolak nama `LOGIN.md`, `*LOGIN*.md`, `*credential*`,
  `*kredensial*`, `*secret*`).
- Pemasangan produksi: ikuti `md/SECURITY.md` bagian 7 dan `md/ARCHITECTURE.md` bagian 10. Server tidak menjalankan
  Node, jadi `public/build` harus sudah ada.

## Dokumentasi

| Berkas | Isi |
| --- | --- |
| `md/PRD.md` | Tujuan, pengguna, ruang lingkup, kebutuhan fungsional, aturan bisnis, status dan risiko |
| `md/ARCHITECTURE.md` | Struktur aplikasi, rute dan gerbang, alur Inertia, data, media, SEO, konfigurasi, keputusan |
| `md/DESIGN_SYSTEM.md` | Token warna terang/gelap, cara kerja tema, tipografi, komposisi seksi, gerak, komponen, kontras |
| `md/SECURITY.md` | Data yang dilindungi, kontrol di front end dan server, celah yang diketahui, daftar periksa rilis |
| `md/CODE_STYLE.md` | Penamaan, struktur berkas, PHP, TypeScript, data dan media, galat, komentar |
| `md/TESTING.md` | Pemeriksaan statis, PHPUnit, pemeriksaan browser, daftar uji manual |
| `DESIGN.md` | Arah desain (Design Read, peran warna, komposisi seksi) beserta alasannya |
| `CLAUDE.md`, `AGENTS.md` | Petunjuk untuk asisten pemrograman (CLAUDE.md memuat panduan teknis lengkap) |
