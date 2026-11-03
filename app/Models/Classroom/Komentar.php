<?php

namespace App\Models\Classroom;

use App\Models\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['postId', 'userId', 'isi'])]
class Komentar extends Model
{
    use HasUlid;

    protected $table = 'komentar';

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'postId');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(Akun::class, 'userId');
    }
}
