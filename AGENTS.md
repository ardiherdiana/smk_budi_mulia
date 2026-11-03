Ini aplikasi web Laravel 13 + Inertia 3 + React 19 + Tailwind 4 + shadcn (`base-nova`) + Framer Motion untuk SMK Budi Mulia Karawang. Utamakan keamanan, SEO sisi server, tampilan ponsel, dan kesamaan dengan design system `absensi`.

Panduan teknis lengkap (skema dikunci, media, keamanan, SEO, data warisan) ada di [CLAUDE.md](CLAUDE.md), arah desain di [DESIGN.md](DESIGN.md), dan dokumen proyek di folder [md/](md/) (`PRD`, `ARCHITECTURE`, `DESIGN_SYSTEM`, `SECURITY`, `CODE_STYLE`, `TESTING`).

## Versi paket berubah, jangan percaya ingatan

Laravel, Inertia, React, Tailwind, Vite, dan Base UI sering memindahkan atau mengganti API antar versi mayor. Sebelum menulis kode yang menyentuhnya:

1. Baca versi di `composer.json` (`laravel/framework`, `inertiajs/inertia-laravel`) dan `package.json` (`@inertiajs/react`, `react`, `tailwindcss`, `vite`, `framer-motion`, `@base-ui/react`, `shadcn`).
2. Baca dokumentasi versi itu, bukan versi terbaru secara umum: Laravel (laravel.com/docs, pilih versi terpasang), Inertia (inertiajs.com), Tailwind (tailwindcss.com/docs), Motion/Framer Motion (motion.dev), Base UI (base-ui.com), shadcn (ui.shadcn.com).
3. Beberapa hal yang sudah berbeda dari kebiasaan lama: Tailwind 4 dikonfigurasi di CSS (`resources/js/index.css`), bukan `tailwind.config.js`; `composer dev` menjalankan `php artisan dev` (server, antrean, dan Vite); model memakai atribut `#[Fillable]`; komponen shadcn `base-nova` memakai prop `render` (bukan `asChild`); TypeScript 6 dengan `erasableSyntaxOnly` dan `verbatimModuleSyntax`.

## Perintah

```bash
composer dev                 # server + antrean + Vite (nyalakan hanya saat perlu, lalu matikan)
composer test                # PHPUnit terhadap MySQL `landing_page_testing`
./vendor/bin/pint            # format PHP
npm run lint                 # ESLint
npm run build                # tsc -b lalu vite build
npm install --ignore-scripts # .npmrc sudah mematikan skrip pasca-pasang
```

Jalankan `pint`, `composer test`, `npm run lint`, dan `npm run build` sebelum menyatakan pekerjaan selesai. Verifikasi lewat perintah itu, bukan lewat dev server yang dibiarkan menyala.

## Rute dan halaman

- Semua rute di `routes/web.php` dan `routes/classroom.php` (tidak ada API). Publik: `/`, `/berita/{post}`, `POST /kontak`. Masuk: `/login` (kolom "Email / Username"), lalu menu `/menu-utama` (kartu Website, SKL, Classroom menurut peran). Portal siswa/SKL: `/portal` (`role:user`). Panel admin Website: `/admin/*` (`role:admin`, `throttle:admin-writes`). Modul SKL admin (akun siswa, berkas): `/skl/*` (`role:admin`, sidebar sendiri). Classroom: `/classroom/*` (middleware `peran:`, halaman `classroom/*`, namespace `Classroom`).
- Nama halaman Inertia sama dengan jalurnya di `resources/js/pages` (`admin/berita-page`). Satu berkas mengekspor satu komponen. `landing-page` dimuat eager, lainnya lazy; halaman tanpa sidebar admin didaftarkan di `BARE_PAGES` pada `main.tsx`.
- Controller tipis: `LandingService` menyusun props landing, `ContentPresenter` mengubah model menjadi array, `MediaService` mengurus foto dan video, `PortalFileStorage` mengurus PDF.

## Aturan

- **Skema produksi dikunci** sama dengan dump kecuali tujuh kolom media (berisi path). Jangan mengubah 9 tabel produksi atau migration `2026_09_21_000001_create_production_schema.php`. Tabel Classroom hidup di migrasi sendiri (`2026_10_06_000001_create_classroom_tables`); `SchemaParityTest` hanya membandingkan tabel produksi dan harus hijau.
- Soal ujian memakai bentuk TKA (`PG`, `PGK_KATEGORI`, `PGK_MCMA`, `ESSAY`) dan diedit di satu halaman penuh (`/classroom/post/{post}/soal`), bukan popup; format kunci/jawaban dan penilaian parsial ada di CLAUDE.md. Data guru, mapel, rombel, dan siswa diisi `php artisan db:seed` dari daftar di kode (`GuruSeeder::DAFTAR`, `MapelSeeder::DAFTAR`, `RombelSeeder::DAFTAR`, `database/seeders/data/siswa.php`), bukan dari Excel. Sandi hanya disimpan sebagai hash bcrypt; Excel kredensial berisi sandi teks biasa dan tidak boleh dikomit.
- Nama modul ruang kelas di sistem adalah **Classroom** (rute, label, namespace); jangan memakai "lms".
- Media hanya lewat `MediaService`; jangan merakit URL atau path media. Berkas di `public/media`, PDF di `storage/app/private/uploads`.
- Meta SEO dirender di server; `<Head>` React hanya untuk judul tab.
- Isi berita dan teks pengguna adalah teks polos, tidak pernah HTML.
- Baca `DESIGN.md` sebelum menyentuh tampilan; token warna hanya diubah di blok "Khusus landing page" pada `index.css`.
- Jangan menulis kredensial, isi `.env`, atau isi dump database ke berkas terlacak, commit, atau pesan.
- Jangan menambah pustaka baru bila komponen shadcn atau `lucide-react` yang ada sudah cukup.
