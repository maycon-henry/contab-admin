<?php

namespace App\Models;

use App\Enums\SituacaoContrato;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContratoHistorico extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'contrato_historicos';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'situacao_anterior' => SituacaoContrato::class,
            'situacao_nova' => SituacaoContrato::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
