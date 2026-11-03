<?php

namespace App\Models\Classroom;

use App\Models\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['postId', 'pengumpulanId', 'tipe', 'nama', 'path', 'url', 'mime', 'ukuran'])]
class Lampiran extends Model
{
    use HasUlid;

    protected $table = 'lampiran';

    protected function casts(): array
    {
        return [
            'ukuran' => 'integer',
        ];
    }
}
