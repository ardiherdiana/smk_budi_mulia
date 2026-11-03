@AGENTS.md

# CLAUDE.md

## Proyek ini

Landing page dan portal SMK Budi Mulia Karawang: satu aplikasi Laravel 13 + Inertia 3 + React 19 (lihat `README.md`).
Bagian ini ringkasan berbahasa Indonesia; panduan teknis lengkap (skema dikunci, media, keamanan, SEO, data
warisan) ada di bagian bahasa Inggris di bawah garis pemisah, dan arah desain di `DESIGN.md`. Dokumen di folder `md/`:
`md/PRD.md`, `md/ARCHITECTURE.md`, `md/DESIGN_SYSTEM.md`, `md/SECURITY.md`, `md/CODE_STYLE.md`, `md/TESTING.md`. Baca
yang relevan sebelum mengubah.

- Bahasa antarmuka Indonesia. Pesan validasi di `lang/id/validation.php` atau inline; hasil aksi lewat flash
  `->with('toast', ['type' => ..., 'message' => ...])`.
- **Skema produksi dikunci** identik dengan dump, kecuali tujuh kolom media yang berisi path. Jangan mengubah 9 tabel
  produksi atau `2026_09_21_000001_create_production_schema.php`, jangan menulis `users.password_plain`/`nama_file`/
  `path_file`. Tabel modul Classroom ditambah lewat migrasi tersendiri (lihat bagian Classroom); `SchemaParityTest` hanya
  membandingkan tabel produksi dan harus tetap hijau. Tabel framework (sessions/cache/jobs) tetap tidak ada.
- **Media hanya lewat `MediaService`** (`storeImage`, `storeVideo`, `url`, `delete`). Jangan merakit URL atau path media.
  Hapus berkas lama setelah model tersimpan. Berkas ada di `public/media`; PDF SKL di `storage/app/private/uploads`.
- Tautan berita di front end selalu `post.url` dari server (bentuk kanonik `/berita/{slug}-{id}`).
- **SEO dirender di server** (`Seo`, `SeoBuilder`, `partials/seo.blade.php`). Jangan menambah meta lewat `<Head>` React;
  di sana hanya judul tab. URL absolut dari `APP_URL`, bukan dari header `Host`.
- Isi berita, Tentang Kami, dan uraian jurusan adalah **teks polos**; jangan memakai HTML mentah.
- Teks resmi sekolah (visi, misi, keunggulan, kontak, `PPDB_URL`) hanya di `config/school.php`. Tidak ada angka tanpa
  sumber.
- Desain: baca `DESIGN.md` dulu. Token di `resources/js/index.css` (edit hanya blok "Khusus landing page"), tanpa
  heksadesimal di komponen, oranye hanya untuk aksi, warna solid tanpa gradasi, gerak sekali lewat `Reveal`/`Stagger`.
  Uji di terang dan gelap serta lebar ponsel. Kontras tombol oranye belum memenuhi AA; jangan menurunkan ukuran teksnya.
- Tema: kunci `localStorage` `theme` harus sama di `components/theme-provider.tsx` dan skrip inline `app.blade.php`.
- Satu berkas di `resources/js/pages` mengekspor satu komponen; halaman tanpa sidebar admin masuk `BARE_PAGES` di
  `main.tsx`.
- Rahasia: `.env`, dump `*.sql`, PDF, `*.zip`, dan catatan kredensial (`LOGIN.md`) git-ignored dan tidak boleh disalin
  ke berkas terlacak, commit, atau obrolan. `public/hot` tidak boleh ada di produksi.
- Aturan git-ignore ada di `.gitignore` akar; jangan menambah `.gitignore` di `md/` atau folder lain. Yang bersarang
  hanya bawaan Laravel (`storage/`, `bootstrap/cache/`, `database/`); biarkan apa adanya.
- Verifikasi tanpa dev server: `./vendor/bin/pint`, `composer test` (butuh MySQL dan `landing_page_testing`),
  `npm run lint`, `npm run build`. Jangan membiarkan `php artisan serve` atau `npm run dev` menyala setelah selesai.


## Classroom (digabung dari proyek lms, 2026-10-06)

Modul ruang kelas ala Google Classroom (kelas, materi, tugas, ujian bentuk TKA, kalender, rekap nilai + ekspor xlsx).
Setelah login semua orang melihat menu `/menu-utama` dengan kartu **Website** (`/admin`, admin), **SKL**
(`/skl` untuk admin, modul terpisah dengan sidebar sendiri: Akun Siswa dan Berkas; `/portal` untuk siswa/alumni) dan **Classroom** (`/classroom/kelas`). Kartu disaring
per peran (`MenuController`); bila hanya satu modul yang boleh dibuka, pengguna langsung diarahkan ke sana. Nama di
sistem selalu **Classroom** (rute, label, namespace, config); kata "lms" tidak dipakai.

- Kode di namespace `Classroom` (`App\Models\Classroom`, `App\Http\Controllers\Classroom`, `App\Services\Classroom`,
  `App\Support\Classroom`), rute di `routes/classroom.php` (prefix `/classroom`, dimuat dari `web.php` di dalam grup
  `auth` + `no-store`), halaman React di `resources/js/pages/classroom/*`, `ClassroomLayout`, komponen di
  `components/classroom/*`, util di `lib/classroom/*`. `main.tsx` memilih layout dari awalan nama halaman `classroom/`.
- **Akun**: tabel `users` produksi tidak diubah (id int, email, role `admin|user`). Peran Classroom
  (ADMIN|KEPSEK|GURU|SISWA), username, dan nama ada di tabel pendamping `profil_akun` (`id` = `users.id`), model `Akun`.
  `Akun` membuat/menghapus baris `users` pendampingnya (email sintetis `<username>@akun.local`) dan meneruskan sandi ke
  `users.password`. Middleware `peran:` (`RequirePeran`) memeriksa peran profil dan mengganti `$request->user()` menjadi
  `Akun` selama permintaan Classroom; `HandleInertiaRequests` membagikan `auth` dan `kelasSaya` secara lazy karena itu.
  Admin lama otomatis dibuatkan profil ADMIN saat pertama membuka Classroom (`Akun::untukPengguna`).
- **Login** satu form "Email / Username": ada `@` = alur email lama; selain itu = username Classroom. Guru/kepsek/admin
  staf diverifikasi ke basis data absensi (koneksi baca-saja `absensi`, `AbsensiAccountService`) lalu disalin ke
  `profil_akun` (`absensi_user_id`); siswa (username `2627NNNN`, berkas sumber tanpa NIS) dan guru hasil `GuruSeeder` (`SMKBM001`, dst.) memakai sandi lokal.
- **Skema**: migrasi baru `2026_10_06_000001_create_classroom_tables.php` (13 tabel: `profil_akun`, `tahun_ajaran`,
  `rombel`, `siswa`, `mapel`, `kelas`, `classroom_posts`, `komentar`, `pengumpulan`, `lampiran`, `soal`,
  `percobaan`, `jawaban`). Id tabel Classroom ULID string dengan kolom waktu camelCase (trait `HasUlid`); kolom
  yang merujuk pengguna bertipe integer ke `profil_akun.id`. Berkas lampiran di `storage/app/private/lampiran`, hanya
  dilayani `LampiranController`; batas di `config/classroom.php`.
- **Ujian & soal** (format TKA, `format/FORMAT SOAL TKA.docx`): tabel `soal` punya `tipe` `PG | PGK_KATEGORI | PGK_MCMA | ESSAY`, `kompetensi`, `subKompetensi` (hanya terlihat guru; migrasi `2026_10_09_000001_soal_format_tka`). Format `kunci` dan jawaban siswa sama untuk tiap bentuk: PG `"2"`, MCMA `"0,2,4"` (indeks yang dipilih), Kategori `"1,0,1"` (1 = Benar, urut sesuai pernyataan, kosong = belum dijawab). Soal diedit di **satu halaman penuh** (`GET|PUT /classroom/post/{post}/soal`, `SoalController@edit|sinkron`, `pages/classroom/ujian/editor-soal-page.tsx`; bukan popup): seluruh daftar disimpan sekaligus dalam satu transaksi (id ada = ubah, baru = buat, tidak dikirim = hapus, urutan = urutan kiriman). Terkunci begitu ada siswa yang mengerjakan. Penilaian otomatis di `Soal::skorUntuk()` (dipanggil `UjianService::selesai`), PGK parsial: MCMA `poin × max(0, benar − salah) / jumlah kunci`, Kategori `poin × pernyataan cocok / jumlah pernyataan`; essay tetap dikoreksi guru. Helper React bersama di `lib/classroom/soal.ts`.
- **Postingan multi-kelas** (migrasi `2026_10_13_000001_tambah_grup_id_ke_classroom_posts`, kolom `classroom_posts.grupId`): satu postingan bisa diterbitkan ke beberapa ruang kelas milik guru yang sama sekaligus. Hasilnya **satu baris `classroom_posts` per kelas** (jadi pengumpulan, percobaan, nilai, komentar, agenda, dan cascade tetap per kelas) yang ditautkan oleh `grupId` yang sama (null = berdiri sendiri). `PostPublisher` (`app/Services/Classroom`) memegang logikanya: `tujuan()` (kelas milik `guruId` yang sama, tahun ajaran sama, tidak diarsipkan; admin juga dibatasi ke guru pengajar kelas yang dibuka), `terbitkan()` (transaksi; lampiran disimpan sekali lalu **digandakan** per kelas lewat `LampiranService::salin`, berkas fisik dibersihkan bila gagal), `terapkanKeSaudara()` (ubah), `sinkronkanSoal()` (soal ujian). Form mengirim `kelasTujuan[]` ke `POST /classroom/kelas/{kelas}/post`; kelas asal selalu ikut dan ID di luar `tujuan()` ditolak 422. Ubah/hapus hanya memengaruhi satu kelas kecuali `semuaKelas=1`; `PostPresenter` mengirim `jumlahGrup` supaya UI menawarkan centangnya. Menyimpan soal (`SoalController@sinkron`) selalu menimpa soal saudara ujian yang belum dikerjakan dan melewati yang sudah ada `percobaan`. UI: `components/classroom/target-kelas.tsx` (filter Tingkat/Jurusan dari data rombel, daftar centang kelas), `hooks/use-hapus-post.ts`, dan opsi `checkbox` di `useConfirm`. Pengumpulan tidak lintas kelas: siswa tetap hanya melihat salinan di rombelnya.
- **Reset dan seed ruang kelas**: `php artisan classroom:reset-kelas [--dry-run] [--force]` (`ResetKelas`) menghapus semua `kelas` lewat `PostCleaner::hapusKelas` (berkas lampiran dulu, lalu baris; forum, pengumpulan, soal, percobaan, dan jawaban ikut terhapus lewat cascade) dan tidak menyentuh akun, rombel, siswa, mapel, atau tahun ajaran. `php artisan db:seed --class=KelasSeeder` membuat ruang kelas dengan **aturan wali kelas**: berkas Excel tidak punya jadwal mengajar, jadi tiap rombel diberi ruang kelas untuk semua mapel wali kelasnya (nama wali dibaca `SiswaSeeder::bacaWaliKelas` dari tanda tangan blok, dicocokkan ke `GuruSeeder::DAFTAR` oleh `KelasSeeder::cariGuru`; mapel dari kolom `mapel` di `GuruSeeder::DAFTAR`) dan `rombel.waliKelasId` diisi bila kosong. Hasilnya perkiraan, `firstOrCreate` per (rombel, mapel, tahun ajaran) sehingga aman dijalankan ulang dan tidak menimpa kelas milik guru lain. `KelasSeeder` tidak dimasukkan ke `ClassroomSeeder` (agar `db:seed` tidak menghidupkan kembali kelas yang dihapus guru). Tes seeder tidak boleh menulis ke `storage/app/private/data-*.xlsx`; jalur berkas dioper lewat parameter.
- **Data guru (`/classroom/data-guru`) hanya CRUD, tanpa sinkron.** Admin menambah/mengubah/menghapus akun guru dan kepsek langsung (`GuruController@store|update|destroy`, sandi lokal, `absensi_user_id` null). Tombol dan rute `POST /data-guru/sinkron` sudah dihapus. Akun ADMIN bisa diubah tapi tidak dihapus, peran admin tidak bisa diganti, dan akun yang sedang dipakai tidak bisa dinonaktifkan; guru yang masih mengajar (`kelas.guruId` RESTRICT) atau pernah membuat postingan ditolak 409. Mengisi sandi baru pada akun bekas sinkron melepas `absensi_user_id` agar sandi lokal berlaku. `ProfileController` mengizinkan ganti sandi sendiri bagi semua akun dengan `absensi_user_id` null (bukan hanya siswa). Fallback login ke absensi (`LoginController`) dan `classroom:sinkron-guru` sengaja tidak diubah.
- Daftar ruang kelas (`/classroom/kelas`) tidak lagi punya tombol Arsip; mode `?arsip=1`, item sidebar "Kelas yang diarsipkan", dan tombol Arsipkan/Pulihkan di header kelas tetap ada. Halaman kalender, stream, dan tab Tugas/Anggota kelas dibuat selebar penuh (tanpa `mx-auto max-w-*`).
- Perintah: `php artisan classroom:sinkron-guru` (salin akun staf dari absensi), `php artisan db:seed` (= `ClassroomSeeder`: `TahunAjaranSeeder`, `GuruSeeder`, `MapelSeeder`, `RombelSeeder` (14 rombel di kode, tanpa wali), `SiswaSeeder`, aman dijalankan ulang dan tidak menimpa sandi/rombel/NIS yang diubah di aplikasi). Sumber data: guru dan mapel **ditulis di kode** (`GuruSeeder::DAFTAR`: nama, username, hash bcrypt sandi, mapel; `MapelSeeder::DAFTAR`: 23 mapel; keduanya tidak membaca Excel, sandi hanya hash karena kredensial dilarang di berkas terlacak, SMKBM028 bersandi acak yang diatur lewat Data Guru; `GuruSeeder` yang dijalankan ulang hanya memperbarui nama, tidak menimpa sandi/peran/status). Siswa juga di kode (`database/seeders/data/siswa.php`, `SiswaSeeder::daftar()`): 380 baris `[rombel, nama, username, hash]` dari `kredensial-siswa.xlsx` produksi, sheet lama dipetakan ke 14 rombel baru (TJKT => TKJ, AKUNTANSI/AKL => AKT, XI BINAAN BNET => XI TKJ 1 adalah dugaan). Berisi nama siswa, jangan ke repositori publik. Hanya `SiswaSeeder::bacaWaliKelas` (untuk `KelasSeeder`) yang masih membaca `storage/app/private/data-siswa.xlsx` (salinan "DATA SISWA SMKBM 2627.xlsx"), git-ignored, dan namanya masih nama rombel lama sehingga `KelasSeeder` melewati semua rombel sampai dipetakan. 14 rombel dan 380 siswa AKTUAL; username siswa `2627NNNN`; seeder tidak lagi menulis Excel kredensial (berkas `kredensial-siswa.xlsx` yang ada berisi sandi teks biasa, jangan dikomit). Tanpa tahun ajaran aktif, `KelasController@store` menolak dengan 409. Env: `ABSENSI_DB_*`, `CLASSROOM_MAX_UPLOAD_KB`.
- Basis data lokal: aplikasi `budi_mulia` (`DB_DATABASE`), absensi `absensi` (`ABSENSI_DB_DATABASE`), tes `landing_page_testing`. MySQL 8.4 tidak memuat plugin `mysql_native_password`; user DB harus `caching_sha2_password`, kalau tidak koneksi gagal dan semua halaman 500.
- Sidebar tiap modul (Website `components/app-sidebar.tsx`, SKL `components/skl-sidebar.tsx`, Classroom `components/classroom/classroom-sidebar.tsx`) punya item **Menu Utama** (`/menu-utama`, ikon `Home`) di grup "Menu", seperti absensi. `main.tsx` memilih layout dari awalan halaman: `classroom/` → `ClassroomLayout`, `skl/` → `SklLayout`, lainnya `DashboardLayout`.
- Modul SKL admin di `/skl/*` (`role:admin`): `Skl/DashboardController`, `Skl/PortalUserController` (akun siswa), `Skl/PortalFileController` (berkas), halaman `pages/skl/*`.
- PWA: `public/manifest.json` + ikon `pwa-*.png`, `start_url` = `/menu-utama`.

---

Panduan teknis lengkap (bahasa Inggris):

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project overview

**Landing page & portal SMK Budi Mulia Karawang** - a refactor of the old React(Vite build)+loose-PHP
site (was `../landing_page_production`, deleted from this laptop on 2026-09-24; the production dump is kept as `database/seeders/data/production.sql`) into Laravel + Inertia + React. One-page public landing site
(Framer Motion), an admin panel for the site content, and a student portal (download SKL PDFs).
All UI text is Indonesian. **Read `DESIGN.md` before touching the UI**: it holds the Design Read (dials),
colour roles and the written reasons; UI work is filtered through the [antislop](https://github.com/miqdadbadjuber/anti-slop)
rules (no decorative badges/glow/endless animation, no invented numbers, WCAG AA contrast, 44px tap targets,
no em dashes, varied section composition). The design system is a **1:1 copy of `../absensi`** (Tailwind 4 + shadcn
`base-nova`, Geist font, orange `--primary` for actions, blue `--sidebar-*` for structure, solid
colours only - no gradients except the photo scrim on the login page). Don't invent new tokens; edit
`resources/js/index.css` only in the marked "Khusus landing page" block at the bottom.

## Commands

```bash
composer dev                 # php artisan dev (built into Laravel 13): server + queue listener + vite
php artisan serve            # backend only (see "Local PHP limits" below)
npm run dev | build | lint   # build = tsc -b && vite build

composer test                # PHPUnit against MySQL `landing_page_testing`
./vendor/bin/pint            # PHP formatting

php artisan migrate
php artisan db:seed --class=ProductionDataSeeder      # load database/seeders/data/production.sql
php artisan portal:import-uploads <old portal_download/uploads>   # copy the real PDFs (PDF magic checked)
php artisan users:harden [--dry-run]                  # hash legacy plaintext passwords + wipe password_plain
php artisan media:migrate-to-files [--dry-run]        # leftover base64 in the DB -> files (migrate already does it)
```

## The database schema is (almost) FROZEN

`u282680727_db_sekolah.sql` (production) is the source of truth and the new app must keep working
against that schema, with ONE deliberate exception: the 7 photo/video columns are `VARCHAR(500)` file
paths instead of base64 `LONGTEXT`/`LONGBLOB` (see "Media" below). So:

- `database/migrations/2026_09_21_000001_create_production_schema.php` is **raw DDL** copied from the
  dump (Schema builder can't reproduce the collations/defaults 1:1). Do not edit it to "improve" the
  schema; it skips tables that already exist so it is safe on the live production database.
  `tests/Feature/SchemaParityTest` compares `information_schema` against
  `tests/Fixtures/production-schema.txt` (the ORIGINAL production schema) and allows exactly the 7 media
  columns to differ; it must stay green.
- Only the 9 production tables, the 13 Classroom tables (separate migration `2026_10_06_000001_create_classroom_tables`,
  see the Classroom section above) and `migrations` exist. Sessions/cache use the **file** driver and queue
  is `sync` on purpose (no framework tables). Don't add other tables; the contact form sends email instead
  of storing messages. `SchemaParityTest` only compares the production tables.
- Models follow the *production* conventions, not absensi's ULID/camelCase ones: int auto-increment
  ids (`users`, `files`, `guru_staff`, `jurusan`, `principals`), string ids like `post_<ulid>` /
  `tentang_<ulid>` / `media_<ulid>`, snake_case columns, mostly `$timestamps = false`.
- `users.password_plain`, `users.nama_file`, `users.path_file` are legacy columns kept only for schema
  parity. **Never write them.** `users:harden` nulls `password_plain`.
- `guru_staff.email` is only `varchar(20)`; validation enforces it.
- The DB connection uses `timezone => +07:00` (== `APP_TIMEZONE`); the seeder loads the dump under
  `+00:00` exactly like the dump header does, so timestamps stay identical to production. Only the
  7 media columns differ (path instead of base64).

## Media: files in `public/media`, paths in the database

The production dump stored photos/videos as base64 (the DB was ~79 MB). They are now real files and the
7 columns (`postingan.image`, `postingan.video_file`, `postingan_media.file`, `tentang_kami_media.file`,
`jurusan.image`, `guru_staff.image`, `principals.foto`) hold a relative path such as
`post-image/01k....webp`, typed `VARCHAR(500)`. Rules:

- Files live on disk `media` = `public/media` (`config/filesystems.php`, `MEDIA_ROOT` overrides). It is
  deliberately NOT `storage/app/public` + `storage:link`: shared hosting often can't create symlinks, and
  the web server then serves the file directly (cache headers, video Range) without booting PHP.
  `public/media/.htaccess` turns PHP off there and allows only jpg/png/webp/mp4/webm/ogv; `public/media/*`
  is gitignored except that file.
- All reads/writes go through `MediaService`. `url($path)` turns a stored path into `/media/...`; it and
  `delete()` first validate the path with a strict regex (`kind/name.ext`, no `..`), so a bad DB value can
  never become a URL or delete something else. Never build media URLs or paths by hand.
- Uploads: `storeImage()` (GD re-encode + resize to 1600px, strips EXIF; if GD lacks JPEG/WebP, as on
  Herd's PHP, the verified original is kept) and `storeVideo()` (MIME sniffed with finfo, capped by
  `MEDIA_MAX_VIDEO_MB`, default 50). Names are server-made (`{kind}/{ulid}.{ext}`), never from the client.
- Controllers delete the old file only AFTER the model saved, and delete files when a record or
  gallery item is removed. Keep that order: a failed save must not lose the existing photo.
- Legacy base64 -> files is `MediaMigrator` (`toFiles()` then `narrow()`; rollback `widen()` + `toBlobs()`),
  run by migration `2026_09_22_000001_store_media_as_files`, by `media:migrate-to-files` and by
  `ProductionDataSeeder`. `narrow()` refuses while any non-path value remains, so nothing is truncated.
  Content that is not a real image/video (e.g. the PHP web shell found in `tentang_kami_media`) is never
  published: it goes to `storage/app/private/media-rejected/` and the column becomes NULL. Migrated files are
  named `{kind}/{id}-{sha1[0..8]}.{ext}` (idempotent); the extension comes from the real content, not
  the old (sometimes wrong) data-URI mime.

## Architecture

- Routes in `routes/web.php`: public landing (`/`, `/berita/{id}` = same page with the post dialog open,
  `POST /kontak`), auth (`/login`, one "Email / Username" field), module picker (`/menu-utama`), Classroom
  (`/classroom/*`, `routes/classroom.php`, `peran:` middleware), student portal (`/portal`, `role:user`), admin panel (`/admin/*`,
  `role:admin`, `throttle:admin-writes`). Authorization is the `role:` middleware plus `PortalFilePolicy`.
- Controllers are thin; `LandingService` assembles the landing props, `ContentPresenter` turns models
  into arrays, `PortalFileStorage` owns PDF storage. Validation is inline (`$request->validate`) like
  absensi. Toasts are `->with('toast', [...])` flash, shown by `main.tsx`.
- Inertia page name == file path under `resources/js/pages` (`admin/berita-page`). A page file must
  export exactly ONE component (main.tsx takes the first export). `landing-page` is bundled eagerly,
  everything else is lazy-loaded; new full-bleed pages go in `BARE_PAGES` in `main.tsx`.
- Landing sections live in `resources/js/components/landing/*`; animation primitives (Reveal, Stagger,
  CountUp, ScrollProgress) in `motion.tsx`. `MotionConfig reducedMotion="user"` wraps the page.
- Static content that has no DB home (visi/misi, contact, features, PPDB link) is in `config/school.php`.

## Security decisions (keep them)

- Admin is a real server-side session login (the old site had the admin password hard-coded in the
  JS bundle and open write endpoints). Login: bcrypt, 5 attempts/min per email+IP, session regenerated,
  generic error. A legacy plaintext `users.password` is accepted once then re-hashed.
- PDFs are stored in `storage/app/private/uploads` (never in `public/`), random names, PDF magic bytes
  checked, downloaded only via `portal.download` (owner or admin). `path_file` from the DB is
  whitelisted (`uploads/<safe>.pdf`) before touching disk. `filesystems.local.serve` is `false`.
- Student passwords are never stored or exported in plaintext: bulk create/reset shows generated
  credentials once (flash), CSV export has no passwords, import takes new ones.
- `SecurityHeaders` middleware: CSP with per-request nonce (inline theme script in `app.blade.php`
  carries `Vite::cspNonce()`), no `unsafe-eval`, frame-src limited to YouTube-nocookie/OpenStreetMap,
  plus HSTS on HTTPS, nosniff, referrer/permissions policies. Logged-in areas send `no-store`.
- Post/Tentang Kami text is plain text rendered by React (`whitespace-pre-line`), never HTML.
- Contact form: honeypot, header-injection guard, `throttle:contact` (3/min, 20/day per IP).

## SEO

The app is an Inertia SPA without SSR (Hostinger shared hosting can't run Node), so crawlers and link
previews (WhatsApp/Facebook do NOT run JS) only see the first HTML. Everything SEO therefore renders on the
server:

- `App\Support\Seo` (request-scoped) holds title, description, canonical, robots, Open Graph, JSON-LD.
  `SeoBuilder` fills it (`home()`, `post()`), `resources/views/partials/seo.blade.php` prints it. The default
  is `noindex, nofollow`, so login/portal/admin/error pages can never be indexed by forgetting something.
  React's `<Head>` only sets the tab title (do NOT add meta tags there: they would duplicate the server ones).
- URLs (canonical, og:url, sitemap, redirect targets) are built from `APP_URL` via `Seo::absolute()`, never from
  the request Host header (spoofable). `APP_URL` must be the final https domain in production.
- Only production is indexable (`Seo::indexable()`): other environments get `Disallow: /` in robots.txt, a
  `noindex` meta and an `X-Robots-Tag` header. Force with `SEO_INDEXABLE=true|false`.
- Post URLs are `/berita/{slug}-{id}` (`Post::path()`), the id is the only lookup key. Old `/berita/{id}` links and
  stale slugs 301 to the canonical URL. The front end must link with `post.url`, never build `/berita/${id}`.
- `/robots.txt` and `/sitemap.xml` are routes (`SeoController`), so `public/robots.txt` must not exist (it would
  shadow the route). Sitemap = home + every post with its cover image.
- Search/filter/page>1 variants of the home page are `noindex, follow` with canonical `/`.
- JSON-LD: `HighSchool` + `WebSite` on the home page; `NewsArticle`, `BreadcrumbList` and (YouTube/uploaded video)
  `VideoObject` on posts. Only real data (address, phone, coordinates from `config/school.php`); no invented
  ratings/numbers. JSON is encoded with `JSON_HEX_TAG` so a post title can never close the `<script>`.
- Default share image is `public/og-default.jpg` (1200x630, < 300 KB for WhatsApp). Change the copy in
  `config/school.php` (`seo.*`), not in the Blade file.

## Local PHP limits

`php artisan serve` inherits php.ini. For video uploads (default cap 50 MB) run with e.g.
`php -d memory_limit=512M -d upload_max_filesize=64M -d post_max_size=80M artisan serve`.
Production needs `upload_max_filesize`/`post_max_size` >= `MEDIA_MAX_VIDEO_MB`. Converting a legacy
base64 database (`migrate`, `media:migrate-to-files`, the seeder) needs `memory_limit` ~1G; the migrator
raises it itself when the host allows. `public/hot` must NOT exist in production - a stale
one makes Laravel (and the CSP) point at a Vite dev server that isn't there.

## Known data caveats (from the production dump)

The dump contains rows left by an attacker/tests (a `HACKET BY NEURA SEC` block in `tentang_kami`,
posts titled `$(curl ...)`/`Test`, a `test` principal in `guru_staff`, a user `alfa.php` upload). They are
loaded as-is on purpose (data parity) - delete them from the admin panel. One of those rows
(`tentang_kami_media` `media_6a76dea838c06`) held a PHP web shell as its "image"; the media migration
refuses to publish it (quarantine in `storage/app/private/media-rejected/`, column NULL).
`principals` id 30's photo was already cut off at the old `TEXT` 64 KB limit in production (bottom third
garbled); it is migrated faithfully, so re-upload it from the admin panel.
`gudang@system.internal` is the legacy "warehouse" admin account; new unassigned files use `user_id = NULL`.

## Secrets & personal notes

The ignore rules live in the root `.gitignore`. `md/` has no `.gitignore` of its own (removed 2026-09-24; only
Laravel's stock nested ones in `storage/`, `bootstrap/cache/` and `database/` remain, keep them as they are).
`md/` holds the project docs (`PRD`, `ARCHITECTURE`, `DESIGN_SYSTEM`, `SECURITY`, `CODE_STYLE`, `TESTING`; `README`,
`CLAUDE` and `AGENTS` live at the project root) and IS tracked, with no folder-level protection: personal notes and
credential files must not be put there unless their name matches the root patterns (`LOGIN.md`, `*LOGIN*.md`,
`*credential*`, `*kredensial*`, `*secret*`). When present, `md/LOGIN.md` holds every login credential (admin, 98 student accounts
from the dump, local DB, and the old site's leaked ones) - never copy its contents into tracked files, commits, or
chat (it was not in `md/` on 2026-09-24). `.env*` (except `.env.example`), `*.sql` dumps, PDFs in
`storage/app/private`, `*.zip` backups and exported credential CSVs are ignored too.
