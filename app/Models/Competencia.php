<?php

namespace App\Models;

use App\Domain\Competencia as Mes;
use App\Enums\SituacaoCompetencia;
use App\Models\Concerns\PertenceAEmpresa;
use App\Models\Concerns\PertenceAoEscritorio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Mês de trabalho de uma empresa — regras.md seção 9. */
class Competencia extends Model
{
    use PertenceAEmpresa;
    use PertenceAoEscritorio;

    protected $table = 'competencias';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'competencia' => 'date',
            'situacao' => SituacaoCompetencia::class,
            'fechada_em' => 'datetime',
        ];
    }

    public function mes(): Mes
    {
        return Mes::daData($this->competencia);
    }

    public function getRotuloAttribute(): string
    {
        return $this->mes()->paraTela();
    }

    public function estaFechada(): bool
    {
        return $this->situacao === SituacaoCompetencia::Fechada;
    }

    public function itens(): HasMany
    {
        return $this->hasMany(ItemCompetencia::class);
    }

    public function fechadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fechada_por_id');
    }

    /** Notas fiscais da empresa nesta competência (seção 10). */
    public function notasFiscais(): HasMany
    {
        return $this->hasMany(NotaFiscal::class, 'empresa_id', 'empresa_id')
            ->whereDate('notas_fiscais.competencia', $this->competencia?->toDateString());
    }

    /** RN-604 / RN-605 — admissões e desligamentos que acontecem no mês. */
    public function movimentosFuncionarios(): HasMany
    {
        $inicio = $this->competencia?->toDateString();
        $fim = $this->competencia?->copy()->endOfMonth()->toDateString();

        return $this->hasMany(Contrato::class, 'empresa_id', 'empresa_id')
            ->where(fn ($q) => $q->whereBetween('data_admissao_prevista', [$inicio, $fim])
                ->orWhereBetween('data_desligamento', [$inicio, $fim]));
    }
}
