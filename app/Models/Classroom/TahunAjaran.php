<?php

namespace App\Models\Classroom;

use App\Models\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['nama', 'semester', 'aktif'])]
class TahunAjaran extends Model
{
    use HasUlid;

    protected $table = 'tahun_ajaran';

    protected function casts(): array
    {
        return [
            'aktif' => 'boolean',
        ];
    }

    public static function aktif(): ?self
    {
        return static::where('aktif', true)->first();
    }

    public function label(): string
    {
        return $this->nama.' '.($this->semester === 'GANJIL' ? 'Ganjil' : 'Genap');
    }
}
