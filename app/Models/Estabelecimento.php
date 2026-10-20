<?php

namespace App\Models;

use App\Domain\DocumentoFiscal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Estabelecimento extends Model
{
    protected $table = 'estabelecimentos';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'matriz' => 'boolean',
            'aliquota_rat' => 'decimal:1',
            'fap' => 'decimal:4',
            'grau_risco' => 'integer',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function getRotuloAttribute(): string
    {
        return ($this->matriz ? 'Matriz' : 'Filial').' — '.DocumentoFiscal::formatarCnpj($this->cnpj)
            .($this->municipio ? " ({$this->municipio}/{$this->uf})" : '');
    }
}
