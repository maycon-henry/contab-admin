<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** RN-731 — cancelamento recebido antes da nota. */
class CancelamentoPendente extends Model
{
    protected $table = 'cancelamentos_pendentes';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['cancelada_em' => 'datetime'];
    }
}
