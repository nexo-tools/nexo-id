<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One Nexo ID login on one device: the OIDC `sid` (ADR-010). Every id_token
 * issued during this login carries it, and ending it is what fans out the
 * back-channel logout to the tools listed in idp_session_clients.
 *
 * @property string $sid
 * @property string $user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $last_seen_at
 * @property Carbon|null $ended_at
 * @property string|null $ended_reason
 */
#[Fillable(['sid', 'user_id', 'last_seen_at', 'ended_at', 'ended_reason'])]
class IdpSession extends Model
{
    public const UPDATED_AT = null;

    protected $primaryKey = 'sid';

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<IdpSessionClient, $this> */
    public function clients(): HasMany
    {
        return $this->hasMany(IdpSessionClient::class, 'sid', 'sid');
    }

    public function isEnded(): bool
    {
        return $this->ended_at !== null;
    }
}
