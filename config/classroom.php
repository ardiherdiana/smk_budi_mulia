<?php

return [

    'nama_sekolah' => 'SMK Budi Mulia Karawang',

    /*
    | Batas unggahan per berkas (KB) dan jumlah lampiran per kiriman. Berlaku untuk materi/tugas dari guru maupun
    | hasil kerja siswa. Berkas disimpan di disk `local` (storage/app/private) dan hanya dilayani lewat
    | LampiranController yang memeriksa hak akses.
    */
    'max_upload_kb' => (int) env('CLASSROOM_MAX_UPLOAD_KB', 20480),
    'max_lampiran' => 10,

    /*
    | Impor soal dari .docx atau PDF. Dokumen hanya dibaca untuk dipecah menjadi draf soal (tidak disimpan); gambar di
    | dalamnya disimpan sementara di media `soal-tmp` sampai guru menyimpan soal. `max_gambar_soal` berlaku per soal;
    | `max_pdf_halaman` hanya untuk PDF.
    */
    'max_impor_kb' => (int) env('CLASSROOM_MAX_IMPOR_KB', 10240),
    'max_pdf_halaman' => 60,
    'max_gambar_soal' => 4,
    'mimes' => [
        'pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'txt', 'csv',
        'jpg', 'jpeg', 'png', 'webp', 'gif', 'mp4', 'mp3', 'zip', 'rar',
    ],

];
