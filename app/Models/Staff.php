<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Tabel `guru_staff`: pendidik & tenaga kependidikan. `role` (enum) menentukan pengelompokan. */
#[Fillable(['name', 'email', 'role', 'type', 'subject', 'expertise', 'position', 'image'])]
class Staff extends Model
{
    protected $table = 'guru_staff';

    public const ROLES = ['principal', 'vice_principal', 'program_head', 'teacher', 'staff'];

    public function scopeListing(Builder $query): Builder
    {
        return $query; // `image` kini path pendek
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderByRaw("FIELD(role, 'principal', 'vice_principal', 'program_head', 'teacher', 'staff')")
            ->orderBy('name');
    }
}
