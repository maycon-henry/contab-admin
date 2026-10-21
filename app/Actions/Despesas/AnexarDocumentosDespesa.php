<?php

namespace App\Actions\Despesas;

use App\Domain\Opcoes;
use App\Enums\TipoDocumentoDespesa;
use App\Exceptions\RegraDeNegocioException;
use App\Models\ConfigDocumentoDespesa;
use App\Models\Despesa;
use App\Models\Documento;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * regras.md RN-1606 e RN-1610 — anexa boletos, recibos, notas fiscais e cheques à despesa,
 * conferindo o formato e o tamanho configurados pelo escritório para cada tipo.
 */
class AnexarDocumentosDespesa
{
    /**
     * @param  array<array{tipo: string|TipoDocumentoDespesa, arquivo: string, nome?: string|null}>  $documentos
     */
    public function executar(Despesa $despesa, User $user, array $documentos): void
    {
        RegraDeNegocioException::se(! $user->podeGerenciarDespesasDa($despesa->empresa), 'Você não tem permissão para anexar documentos a esta despesa.');

        $config = ConfigDocumentoDespesa::doEscritorio($despesa->escritorio_id);

        foreach ($documentos as $documento) {
            $tipo = $documento['tipo'] instanceof TipoDocumentoDespesa ? $documento['tipo'] : TipoDocumentoDespesa::tryFrom((string) ($documento['tipo'] ?? ''));
            RegraDeNegocioException::se($tipo === null, 'Escolha o tipo de cada documento (boleto, recibo, nota fiscal ou cheque).');
            RegraDeNegocioException::se(blank($documento['arquivo'] ?? null), "Anexe o arquivo do documento \"{$tipo->getLabel()}\".");

            $regra = $config[$tipo->value];
            $caminho = $documento['arquivo'];
            $nome = $documento['nome'] ?? basename($caminho);
            $disco = Storage::disk(Documento::DISCO);

            RegraDeNegocioException::se(! $disco->exists($caminho), "O arquivo \"{$nome}\" não foi encontrado. Envie de novo.");
            RegraDeNegocioException::se(! in_array($disco->mimeType($caminho), Opcoes::mimes($regra->formatos), true),
                "{$tipo->getLabel()}: formato de \"{$nome}\" não aceito (aceitos: ".collect($regra->formatos)->map(fn ($f) => Opcoes::FORMATOS[$f] ?? $f)->implode(', ').').');
            RegraDeNegocioException::se($disco->size($caminho) > $regra->tamanho_maximo_mb * 1024 * 1024,
                "{$tipo->getLabel()}: \"{$nome}\" passa do limite de {$regra->tamanho_maximo_mb} MB.");

            Documento::withoutGlobalScopes()->create([
                'escritorio_id' => $despesa->escritorio_id,
                'empresa_id' => $despesa->empresa_id,
                'documentavel_type' => $despesa->getMorphClass(),
                'documentavel_id' => $despesa->id,
                'tipo' => $tipo->value,
                'arquivo' => $caminho,
                'nome_original' => $nome,
                'origem' => $user->ehDaEquipe() ? 'escritorio' : 'empresa',
                'visivel_empresa' => true,
                'enviado_por_id' => $user->id,
            ]);
        }
    }
}
