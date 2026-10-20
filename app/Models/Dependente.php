<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Dependente extends Model
{
    protected $table = 'dependentes';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'data_nascimento' => 'date',
            'dependente_irrf' => 'boolean',
            'dependente_salario_familia' => 'boolean',
        ];
    }

    public function funcionario(): BelongsTo
    {
        return $this->belongsTo(Funcionario::class);
    }
}
