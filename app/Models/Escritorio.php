<?php

namespace App\Models;

use App\Enums\SituacaoEscritorio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Escritorio extends Model
{
    protected $table = 'escritorios';

    protected $fillable = [
        'razao_social', 'cnpj', 'crc', 'situacao', 'cobranca_automatica_ativa', 'dia_corte_cobranca',
    ];

    protected function casts(): array
    {
        return [
            'situacao' => SituacaoEscritorio::class,
            'cobranca_automatica_ativa' => 'boolean',
            'dia_corte_cobranca' => 'integer',
        ];
    }

    public function usuarios(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function empresas(): HasMany
    {
        return $this->hasMany(Empresa::class);
    }

    public function tiposDocumento(): HasMany
    {
        return $this->hasMany(TipoDocumento::class);
    }
}
