<?php

namespace App\Models;

use App\Enums\AplicacaoTipoDocumento;
use App\Enums\TipoItem;
use App\Models\Concerns\PertenceAoEscritorio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Catálogo de documentos do escritório — regras.md seção 9.2. */
class TipoDocumento extends Model
{
    use PertenceAoEscritorio;

    public const CHAVE_NOTAS_SAIDA = 'notas_saida';

    public const CHAVE_NOTAS_ENTRADA = 'notas_entrada';

    protected $table = 'tipos_documento';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'tipo' => TipoItem::class,
            'aplicacao' => AplicacaoTipoDocumento::class,
            'formatos' => 'array',
            'regimes' => 'array',
            'multiplos_arquivos' => 'boolean',
            'obrigatorio' => 'boolean',
            'exige_validade' => 'boolean',
            'eh_certidao' => 'boolean',
            'aproveitar_valido' => 'boolean',
            'ativo' => 'boolean',
            'prazo_dia' => 'integer',
        ];
    }

    public function empresasSelecionadas(): BelongsToMany
    {
        return $this->belongsToMany(Empresa::class, 'empresa_tipo_documento')->withTimestamps();
    }
}
