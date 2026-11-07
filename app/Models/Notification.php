<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

#[Fillable(['type', 'judul', 'pesan', 'guruId', 'isRead'])]
class Notification extends Model
{
    use MassPrunable;

    /** A notification is deleted exactly this many days after it was created. */
    const RETENTION_DAYS = 7;

    protected $table = 'notifications';

    public $incrementing = false;

    protected $keyType = 'string';

    const CREATED_AT = 'createdAt';

    const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::creating(function (Notification $notification) {
            $notification->id ??= (string) Str::ulid();
        });
    }

    protected function casts(): array
    {
        return [
            'isRead' => 'boolean',
        ];
    }

    /** Rows past retention, read or not - deleted by `model:prune`, which
     * routes/console.php runs every minute so a notification created at
     * 13:39 goes away at 13:39 a week later. */
    public function prunable(): Builder
    {
        return static::where('createdAt', '<=', Carbon::now()->subDays(self::RETENTION_DAYS));
    }

    public function guru(): BelongsTo
    {
        return $this->belongsTo(Guru::class, 'guruId');
    }
}
