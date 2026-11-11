<?php

/*
| Modul Supervisi Guru, persis format "INSTRUMEN OBSERVASI PEMBELAJARAN MENDALAM" (format/INSTRUMEN OBSERVASI
| PEMBELAJARAN MENDALAM NEW.docx). Butir dikunci di sini (tidak bisa diubah dari aplikasi); teksnya salinan kata per
| kata dari dokumen. JUMLAH SCORE = jumlah skor semua butir; TOTAL SCORE = Jumlah Score / (jumlah butir x skor_maks)
| x 100, yaitu Jumlah Score / 56 x 100 untuk 14 butir.
*/

return [
    'skor_maks' => 4,

    // Petunjuk pengisian di dokumen.
    'skor_label' => [
        1 => 'Hampir tidak ada',
        2 => 'Sedikit',
        3 => 'Cukup',
        4 => 'Memadai',
    ],

    // Kategori (bernomor 1-4 sesuai urutan) => butir.
    'instrumen' => [
        'Keselarasan' => [
            'Tujuan pembelajaran, langkah pembelajaran, dan asesmen pembelajaran sudah mengarah pada pencapaian Dimensi Profil Lulusan.',
            'Tujuan pembelajaran, langkah pembelajaran, dan asesmen pembelajaran sudah selaras.',
        ],
        'Kerangka Pembelajaran' => [
            'Praktik pedagogis yang dituliskan sudah tergambar pada langkah pembelajaran dan/atau asesmen pembelajaran.',
            'Lingkungan belajar yang dituliskan sudah tergambar pada langkah pembelajaran dan/atau asesmen pembelajaran.',
            'Kemitraan pembelajaran yang dituliskan sudah tergambar pada langkah pembelajaran dan/atau asesmen pembelajaran.',
            'Pemanfaatan digital yang dituliskan sudah tergambar pada langkah pembelajaran dan/atau asesmen pembelajaran.',
        ],
        'Langkah Pembelajaran' => [
            'Langkah pembelajaran memfasilitasi pengalaman belajar MEMAHAMI.',
            'Langkah pembelajaran memfasilitasi pengalaman belajar MENGAPLIKASI.',
            'Langkah pembelajaran memfasilitasi pengalaman belajar MEREFLEKSI.',
            'Langkah perencanaan memfasilitasi tindakan saling MEMULIAKAN.',
            'Prinsip pembelajaran mendalam tergambar pada langkah pembelajaran.',
            'Perencanaan mengakomodir karakteristik murid.',
        ],
        'Asesmen' => [
            'Perencanaan memuat asesmen yang digunakan untuk memberikan umpan balik guna memperbaiki proses pembelajaran.',
            'Perencanaan memuat asesmen yang dapat mengukur ketercapaian tujuan pembelajaran sesuai karakteristik murid.',
        ],
    ],
];
