<?php

namespace App\Models;

use App\Models\Concerns\PertenceAEmpresa;
use App\Models\Concerns\PertenceAoEscritorio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** RN-1319 — histórico de cobranças. */
class CobrancaDocumentacao extends Model
{
    use PertenceAEmpresa;
    use PertenceAoEscritorio;

    protected $table = 'cobrancas_documentacao';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'data_referencia' => 'date',
            'competencia_referencia' => 'date',
            'destinatarios' => 'array',
            'itens' => 'array',
        ];
    }

    public function enviadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enviado_por_id');
    }
}
