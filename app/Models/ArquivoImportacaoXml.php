<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArquivoImportacaoXml extends Model
{
    protected $table = 'arquivos_importacao_xml';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['competencia' => 'date'];
    }

    public function lote(): BelongsTo
    {
        return $this->belongsTo(LoteImportacaoXml::class, 'lote_id');
    }

    public function notaFiscal(): BelongsTo
    {
        return $this->belongsTo(NotaFiscal::class);
    }
}
