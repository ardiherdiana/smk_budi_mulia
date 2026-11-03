<?php

/*
 | Konten statis landing page yang TIDAK ada di database (skema DB dikunci
 | identik dengan produksi, jadi teks seperti visi/misi & kontak ada di sini).
 */
return [
    'name' => 'SMK Budi Mulia Karawang',
    'short_name' => 'SMK Budi Mulia',
    'tagline' => 'Mencetak Generasi Unggul, Kreatif, dan Berkarakter untuk Masa Depan Gemilang.',
    'description' => 'Website resmi SMK Budi Mulia Karawang. Informasi pendaftaran, jurusan, kegiatan sekolah, dan berita terbaru.',

    'address' => 'Jl. Ciherang, Wadas, Telukjambe Timur, Karawang, Jawa Barat 41361, Indonesia',
    'phone' => '(0267) 865-8659',
    'email' => 'websmkbmkrw@gmail.com',
    'instagram' => 'https://www.instagram.com/smk_budimuliakrw/',
    'map' => ['lat' => -6.336754, 'lng' => 107.277589],

    // Untuk data terstruktur (schema.org). Hanya rincian dari `address` di atas, tidak ada yang baru.
    'address_parts' => [
        'street' => 'Jl. Ciherang, Wadas',
        'locality' => 'Telukjambe Timur, Karawang',
        'region' => 'Jawa Barat',
        'postal_code' => '41361',
        'country' => 'ID',
    ],
    'phone_international' => '+62-267-865-8659',

    // Mesin pencari & pratinjau tautan (WhatsApp/Facebook). Semua tag dirender di server (resources/views/partials/seo.blade.php).
    'seo' => [
        'title' => 'SMK Budi Mulia Karawang - Info PPDB, Jurusan, dan Berita',
        'description' => 'Website resmi SMK Budi Mulia Karawang di Telukjambe Timur: info pendaftaran (PPDB), jurusan, guru, serta berita dan kegiatan sekolah.',
        'image' => '/og-default.jpg', // 1200x630
        'image_alt' => 'Gedung SMK Budi Mulia Karawang',
        // null = otomatis: hanya APP_ENV=production yang boleh diindeks (staging/subdomain uji otomatis noindex).
        // Paksa dengan SEO_INDEXABLE=true atau false.
        'indexable' => env('SEO_INDEXABLE'),
    ],

    // SKL untuk siswa (portal /portal): sementara dimatikan. Nyalakan dengan SKL_SISWA_AKTIF=true. Admin tetap bisa
    // mengelola SKL di /skl.
    'skl_siswa_aktif' => (bool) env('SKL_SISWA_AKTIF', false),

    'ppdb_url' => env('PPDB_URL', 'https://sumberbarokah.ponpes.id/registrasi/index.php?page=SMK'),
    'contact_mail_to' => env('CONTACT_MAIL_TO', 'websmkbmkrw@gmail.com'),

    // Pernyataan resmi sekolah (dari situs lama). Sengaja tanpa ikon; "Berita & Kegiatan Terkini" dibuang
    // karena hanya menunjuk ke bagian berita di halaman yang sama.
    'features' => [
        ['title' => 'Kurikulum Unggul', 'description' => 'Kurikulum terkini yang relevan dengan kebutuhan industri.'],
        ['title' => 'Tenaga Pendidik Profesional', 'description' => 'Guru berpengalaman dan berdedikasi tinggi.'],
        ['title' => 'Fasilitas Lengkap', 'description' => 'Ruang kelas nyaman, laboratorium modern, dan perpustakaan.'],
        ['title' => 'Ekstrakurikuler Beragam', 'description' => 'Kembangkan bakat dan minat melalui berbagai kegiatan.'],
        ['title' => 'Lingkungan Belajar Kondusif', 'description' => 'Suasana belajar yang aman, nyaman, dan inspiratif.'],
    ],

    'culture' => 'Di SMK Budi Mulia Karawang, kami menjunjung tinggi nilai-nilai disiplin, kejujuran, kerja keras, dan semangat kolaborasi. Kami menciptakan lingkungan belajar yang positif, inklusif, dan inspiratif, di mana setiap individu dihargai dan didorong untuk mencapai potensi terbaiknya.',

    'vision' => 'Terwujudnya sekolah unggulan di bidang IPTEK untuk mencetak SDM yang berakhlak mulia, terampil, mandiri, berjiwa enterpreneur, kompetitif, berwawasan lingkungan dan religius',

    'missions' => [
        'Menerapkan penguatan pendidikan karakter melalui kegiatan pembelajaran berbasis teknologi informasi dan komunikasi, pembiasaan, keagamaan dan pengembangan diri.',
        'Menyelenggarakan pendidikan di semua bidang keahlian secara profesional efektif dan efisien dengan konsep BMW ( Bekerja, Melanjutkan, Wirausaha).',
        'Meningkatkan kemampuan guru dan tenaga kependidikan lainnya agar menjadi tenaga yang profesional di bidangnya.',
        'Memberdayakan dan mengembangkan sarana/ prasarana secara maksimal.',
        'Mengadakan pelatihan-pelatihan untuk menciptakan SDM yang terampil, kreatif, dan inovatif.',
        'Membina dan meningkatkan kerjasama dengan seluruh stake holder.',
        'Melatih dan menanamkan jiwa enterpreneur.',
        'Mewujudkan lingkungan belajar yang sehat, bersih, dan nyaman.',
    ],

    'media' => [
        'max_image_kb' => 8192,
        // Video kini file biasa (bukan base64 di DB), jadi batasnya dinaikkan. Batas nyata juga ditentukan
        // php.ini (`upload_max_filesize` dan `post_max_size`), samakan di server.
        'max_video_mb' => (int) env('MEDIA_MAX_VIDEO_MB', 50),
        'image_max_side' => 1600,
    ],
];
