<?php

namespace App\Models;

use App\Models\Concerns\PertenceAoEscritorio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Categoria de despesa, cadastrada pelo administrador do escritório — regras.md RN-1609. */
class CategoriaDespesa extends Model
{
    use PertenceAoEscritorio;

    protected $table = 'categorias_despesa';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['ativo' => 'boolean'];
    }

    public function despesas(): HasMany
    {
        return $this->hasMany(Despesa::class);
    }
}
