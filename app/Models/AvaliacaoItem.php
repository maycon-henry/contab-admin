<?php

namespace App\Models;

use App\Enums\MotivoRecusa;
use App\Enums\ResultadoCertidao;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** RN-655 — uma linha por avaliação, nunca atualizada. */
class AvaliacaoItem extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'avaliacoes_item';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'motivo_recusa' => MotivoRecusa::class,
            'resultado_certidao' => ResultadoCertidao::class,
            'validade' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
