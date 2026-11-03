<?php

namespace App\Http\Controllers\Classroom;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

abstract class Controller
{
    use AuthorizesRequests;

    /**
     * Jumlah baris per halaman untuk daftar berpaginasi (query `per_page`).
     */
    protected function perPage(Request $request): int
    {
        $perPage = $request->integer('per_page');

        return in_array($perPage, [50, 100, 200], true) ? $perPage : 50;
    }
}
