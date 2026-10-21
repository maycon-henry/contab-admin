<?php

namespace App\Models;

use App\Models\Concerns\PertenceAEmpresa;
use App\Models\Concerns\PertenceAoEscritorio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Despesa da empresa cliente — regras.md seção 19. */
class Despesa extends Model
{
    use PertenceAEmpresa;
    use PertenceAoEscritorio;
    use SoftDeletes;

    protected $table = 'despesas';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
            'data_vencimento' => 'date',
            'valor_pago' => 'decimal:2',
            'data_pagamento' => 'date',
            'pagamento_registrado_em' => 'datetime',
        ];
    }

    public const DISCO = 'local';

    public function estaPaga(): bool
    {
        return $this->data_pagamento !== null;
    }

    /** RN-1605 — "vencida" é calculada: vencimento passou e não há pagamento. */
    public function estaVencida(): bool
    {
        return ! $this->estaPaga() && $this->data_vencimento->lt(today());
    }

    public function situacao(): string
    {
        return match (true) {
            $this->estaPaga() => 'Paga',
            $this->estaVencida() => 'Vencida',
            default => 'Em aberto',
        };
    }

    public function cadastradaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cadastrada_por_id');
    }

    /** RN-1609 */
    public function categoria(): BelongsTo
    {
        return $this->belongsTo(CategoriaDespesa::class, 'categoria_despesa_id');
    }

    /** RN-1606 — boletos, recibos, notas fiscais e cheques anexados. */
    public function documentos(): MorphMany
    {
        return $this->morphMany(Documento::class, 'documentavel');
    }
}
