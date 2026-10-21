<?php

namespace App\Models;

use App\Enums\TipoDocumentoDespesa;
use App\Models\Concerns\PertenceAoEscritorio;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/** Formatos aceitos e tamanho máximo de cada tipo de documento de despesa, por escritório — regras.md RN-1610. */
class ConfigDocumentoDespesa extends Model
{
    use PertenceAoEscritorio;

    protected $table = 'config_documentos_despesa';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'tipo' => TipoDocumentoDespesa::class,
            'formatos' => 'array',
        ];
    }

    /**
     * Configuração de todos os tipos do escritório; tipos ainda não configurados vêm com o padrão.
     *
     * @return Collection<string, self> chave = valor do tipo
     */
    public static function doEscritorio(int $escritorioId): Collection
    {
        $salvas = self::withoutGlobalScopes()->where('escritorio_id', $escritorioId)->get()->keyBy(fn (self $c) => $c->tipo->value);

        return collect(TipoDocumentoDespesa::cases())->mapWithKeys(fn (TipoDocumentoDespesa $tipo) => [
            $tipo->value => $salvas[$tipo->value] ?? new self([
                'escritorio_id' => $escritorioId, 'tipo' => $tipo,
                'formatos' => $tipo->formatosPadrao(), 'tamanho_maximo_mb' => TipoDocumentoDespesa::TAMANHO_PADRAO_MB,
            ]),
        ]);
    }

    public static function para(int $escritorioId, TipoDocumentoDespesa $tipo): self
    {
        return self::doEscritorio($escritorioId)[$tipo->value];
    }
}
