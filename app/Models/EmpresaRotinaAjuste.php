<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** RN-616 — ajuste da rotina mensal de uma empresa. */
class EmpresaRotinaAjuste extends Model
{
    protected $table = 'empresa_rotina_ajustes';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['dispensado' => 'boolean', 'obrigatorio' => 'boolean', 'prazo_dia' => 'integer'];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function tipoDocumento(): BelongsTo
    {
        return $this->belongsTo(TipoDocumento::class);
    }
}
