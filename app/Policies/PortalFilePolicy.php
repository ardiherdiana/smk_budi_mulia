<?php

namespace App\Policies;

use App\Models\PortalFile;
use App\Models\User;

class PortalFilePolicy
{
    /** Admin boleh mengunduh semua berkas; siswa hanya berkas miliknya sendiri. */
    public function download(User $user, PortalFile $file): bool
    {
        return $user->isAdmin() || (int) $file->user_id === (int) $user->id;
    }
}
