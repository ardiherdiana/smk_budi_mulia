<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['userId', 'endpoint', 'p256dh', 'auth'])]
class PushSubscription extends Model
{
    protected $table = 'push_subscriptions';

    public $incrementing = false;

    protected $keyType = 'string';

    const CREATED_AT = 'createdAt';

    const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::creating(function (PushSubscription $subscription) {
            $subscription->id ??= (string) Str::ulid();
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'userId');
    }
}
