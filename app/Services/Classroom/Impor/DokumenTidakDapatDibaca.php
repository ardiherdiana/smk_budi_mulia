<?php

namespace App\Services\Classroom\Impor;

use RuntimeException;

/** PDF tidak bisa diproses; pesannya sudah berbahasa Indonesia dan aman ditampilkan ke guru. */
class DokumenTidakDapatDibaca extends RuntimeException {}
