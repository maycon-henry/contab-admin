<?php

namespace App\Models;

use App\Domain\Competencia as Mes;
use App\Enums\ClassificacaoNota;
use App\Enums\SituacaoNota;
use App\Models\Concerns\PertenceAEmpresa;
use App\Models\Concerns\PertenceAoEscritorio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Nota fiscal importada de XML — regras.md seção 10. */
class NotaFiscal extends Model
{
    use PertenceAEmpresa;
    use PertenceAoEscritorio;

    public const DISCO = 'local';

    protected $table = 'notas_fiscais';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'competencia' => 'date',
            'competencia_original' => 'date',
            'data_emissao' => 'datetime',
            'autorizada_em' => 'datetime',
            'cancelada_em' => 'datetime',
            'classificacao' => ClassificacaoNota::class,
            'situacao' => SituacaoNota::class,
            'cfops' => 'array',
            'valor_total' => 'decimal:2',
            'valor_produtos' => 'decimal:2',
            'valor_icms' => 'decimal:2',
            'valor_ipi' => 'decimal:2',
        ];
    }

    public function mes(): Mes
    {
        return Mes::daData($this->competencia);
    }

    public function lote(): BelongsTo
    {
        return $this->belongsTo(LoteImportacaoXml::class, 'lote_id');
    }

    public function enviadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enviado_por_id');
    }
}
