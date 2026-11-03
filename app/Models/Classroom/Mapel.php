<?php

namespace App\Models\Classroom;

use App\Models\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nama'])]
class Mapel extends Model
{
    use HasUlid;

    protected $table = 'mapel';

    public function kelas(): HasMany
    {
        return $this->hasMany(Kelas::class, 'mapelId');
    }
}
