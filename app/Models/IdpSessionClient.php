<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A tool that obtained an authorization code during an IdP session (ADR-010):
 * the set of clients that must receive a logout_token when that sid ends.
 *
 * @property int $id
 * @property string $sid
 * @property string $client_id
 * @property Carbon|null $created_at
 * @property Carbon|null $last_seen_at
 */
#[Fillable(['sid', 'client_id', 'last_seen_at'])]
class IdpSessionClient extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<IdpSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(IdpSession::class, 'sid', 'sid');
    }

    /** @return BelongsTo<OauthClient, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(OauthClient::class, 'client_id');
    }
}
