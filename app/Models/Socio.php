<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Socio extends Model
{
    protected $table = 'socios';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['percentual' => 'decimal:2', 'data_entrada' => 'date'];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }
}
