<?php

// Ziggy (`route()` di React) hanya dipakai halaman modul SPPD. Daftar rute yang dikirim ke browser dibatasi ke `sppd.*`
// supaya rute admin lain tidak ikut terbaca, dan `@routes` hanya dirender untuk pengguna yang login (app.blade.php).
return [
    'only' => ['sppd.*'],
];
