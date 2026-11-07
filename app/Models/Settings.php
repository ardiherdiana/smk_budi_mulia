<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['id', 'namaSekolah'])]
class Settings extends Model
{
    protected $table = 'settings';

    public $incrementing = false;

    protected $keyType = 'int';

    const CREATED_AT = null;

    const UPDATED_AT = 'updatedAt';
}
