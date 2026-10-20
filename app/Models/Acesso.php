<?php

namespace App\Models;

use App\Models\Concerns\PertenceAoEscritorio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** RN-330 — registro de acessos. */
class Acesso extends Model
{
    use PertenceAoEscritorio;

    public const UPDATED_AT = null;

    protected $table = 'acessos';

    protected $guarded = ['id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
