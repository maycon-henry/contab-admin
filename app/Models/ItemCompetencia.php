<?php

namespace App\Models;

use App\Enums\MotivoRecusa;
use App\Enums\ResultadoCertidao;
use App\Enums\SituacaoItem;
use App\Enums\TipoItem;
use App\Models\Concerns\PertenceAEmpresa;
use App\Models\Concerns\PertenceAoEscritorio;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** Item da competência — regras.md seção 9.4. */
class ItemCompetencia extends Model
{
    use PertenceAEmpresa;
    use PertenceAoEscritorio;

    protected $table = 'itens_competencia';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'tipo' => TipoItem::class,
            'situacao' => SituacaoItem::class,
            'motivo_recusa' => MotivoRecusa::class,
            'resultado_certidao' => ResultadoCertidao::class,
            'prazo' => 'date',
            'validade' => 'date',
            'obrigatorio' => 'boolean',
            'exige_validade' => 'boolean',
            'eh_certidao' => 'boolean',
            'sem_movimento' => 'boolean',
            'aceito_por_validade' => 'boolean',
            'enviado_em' => 'datetime',
            'avaliado_em' => 'datetime',
        ];
    }

    public function competencia(): BelongsTo
    {
        return $this->belongsTo(Competencia::class);
    }

    public function tipoDocumento(): BelongsTo
    {
        return $this->belongsTo(TipoDocumento::class);
    }

    public function documentos(): MorphMany
    {
        return $this->morphMany(Documento::class, 'documentavel');
    }

    public function avaliacoes(): HasMany
    {
        return $this->hasMany(AvaliacaoItem::class)->latest('id');
    }

    public function avaliadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'avaliado_por_id');
    }

    /** RN-625 — atrasado é calculado, não é situação. */
    public function estaAtrasado(): bool
    {
        return in_array($this->situacao, SituacaoItem::cobraveis(), true) && $this->prazo->lt(today());
    }

    public function diasDeAtraso(): int
    {
        return $this->estaAtrasado() ? (int) $this->prazo->diffInDays(today()) : 0;
    }

    public function scopeAtrasados(Builder $query): Builder
    {
        return $query->where('tipo', TipoItem::Documento)
            ->whereIn('situacao', SituacaoItem::cobraveis())
            ->whereDate('prazo', '<', today());
    }

    public function scopeSomenteDocumentos(Builder $query): Builder
    {
        return $query->where('tipo', TipoItem::Documento);
    }
}
