<?php

namespace App\Models;

use App\Enums\RegimeTributario;
use Illuminate\Database\Eloquent\Model;

class EmpresaRegime extends Model
{
    protected $table = 'empresa_regimes';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'regime_tributario' => RegimeTributario::class,
            'vigencia_inicio' => 'date',
            'vigencia_fim' => 'date',
        ];
    }
}
