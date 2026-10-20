<?php

namespace App\Models;

use App\Models\Concerns\PertenceAEmpresa;
use App\Models\Concerns\PertenceAoEscritorio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** RN-703 — lote de importação (relatório do envio). */
class LoteImportacaoXml extends Model
{
    use PertenceAEmpresa;
    use PertenceAoEscritorio;

    protected $table = 'lotes_importacao_xml';

    protected $guarded = ['id'];

    public function arquivos(): HasMany
    {
        return $this->hasMany(ArquivoImportacaoXml::class, 'lote_id');
    }

    public function enviadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enviado_por_id');
    }

    /** Atualiza os totais a partir dos arquivos já processados. */
    public function recalcularTotais(): void
    {
        $contagem = $this->arquivos()->selectRaw('resultado, count(*) as total')->groupBy('resultado')->pluck('total', 'resultado');

        $this->update([
            'importadas' => (int) ($contagem['importada'] ?? 0),
            'duplicadas' => (int) ($contagem['duplicada'] ?? 0),
            'rejeitadas' => (int) ($contagem['rejeitada'] ?? 0),
            'situacao' => ($contagem['pendente'] ?? 0) > 0 ? 'processando' : 'concluido',
        ]);
    }
}
