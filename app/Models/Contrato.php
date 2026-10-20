<?php

namespace App\Models;

use App\Enums\DocumentoAdmissao;
use App\Enums\MotivoDesligamento;
use App\Enums\SituacaoContrato;
use App\Enums\TipoAviso;
use App\Enums\TipoContrato;
use App\Models\Concerns\PertenceAEmpresa;
use App\Models\Concerns\PertenceAoEscritorio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** Contrato de trabalho — regras.md seções 8.1, 8.3 a 8.5 e 8.8. */
class Contrato extends Model
{
    use PertenceAEmpresa;
    use PertenceAoEscritorio;

    protected $table = 'contratos';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'situacao' => SituacaoContrato::class,
            'tipo_contrato' => TipoContrato::class,
            'motivo_desligamento' => MotivoDesligamento::class,
            'tipo_aviso' => TipoAviso::class,
            'data_admissao_prevista' => 'date',
            'data_admissao' => 'date',
            'data_termino' => 'date',
            'data_aviso' => 'date',
            'data_desligamento' => 'date',
            'data_projetada_fim_aviso' => 'date',
            'salario' => 'decimal:2',
            'jornada_semanal' => 'decimal:2',
            'valor_liquido_rescisao' => 'decimal:2',
            'solicitado_em' => 'datetime',
            'atendimento_iniciado_em' => 'datetime',
            'admitido_em' => 'datetime',
            'desligado_em' => 'datetime',
        ];
    }

    public function funcionario(): BelongsTo
    {
        return $this->belongsTo(Funcionario::class);
    }

    public function estabelecimento(): BelongsTo
    {
        return $this->belongsTo(Estabelecimento::class);
    }

    public function responsavel(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsavel_id');
    }

    public function solicitadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'solicitado_por_id');
    }

    public function atendimentoIniciadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'atendimento_iniciado_por_id');
    }

    public function admitidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admitido_por_id');
    }

    public function historicos(): HasMany
    {
        return $this->hasMany(ContratoHistorico::class)->latest('id');
    }

    public function documentos(): MorphMany
    {
        return $this->morphMany(Documento::class, 'documentavel')->where('substituido', false);
    }

    /** Documento vigente de um item do checklist de admissão. */
    public function documentoDoChecklist(DocumentoAdmissao $tipo): ?Documento
    {
        return $this->documentos->firstWhere('tipo', $tipo->value);
    }

    public function registrarHistorico(string $acao, ?string $descricao = null, ?SituacaoContrato $anterior = null, ?User $user = null): void
    {
        $this->historicos()->create([
            'user_id' => $user?->id ?? auth()->id(),
            'acao' => $acao,
            'situacao_anterior' => $anterior?->value,
            'situacao_nova' => $this->situacao?->value,
            'descricao' => $descricao,
        ]);
    }
}
