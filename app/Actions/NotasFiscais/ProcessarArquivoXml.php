<?php

namespace App\Actions\NotasFiscais;

use App\Actions\Competencias\ObterCompetencia;
use App\Domain\NotasFiscais\ClassificadorNota;
use App\Domain\NotasFiscais\DadosCancelamento;
use App\Domain\NotasFiscais\DadosNotaFiscal;
use App\Domain\NotasFiscais\LeitorNFe;
use App\Enums\ClassificacaoNota;
use App\Enums\SituacaoItem;
use App\Enums\SituacaoNota;
use App\Exceptions\RegraDeNegocioException;
use App\Models\ArquivoImportacaoXml;
use App\Models\CancelamentoPendente;
use App\Models\Empresa;
use App\Models\ItemCompetencia;
use App\Models\NotaFiscal;
use App\Models\TipoDocumento;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** Lê um XML do lote e importa a nota (ou aplica o cancelamento) — regras.md seções 10.2 a 10.5. */
class ProcessarArquivoXml
{
    public function __construct(
        private readonly LeitorNFe $leitor,
        private readonly ClassificadorNota $classificador,
        private readonly ObterCompetencia $obterCompetencia,
    ) {}

    public function executar(ArquivoImportacaoXml $arquivo): void
    {
        if ($arquivo->resultado !== 'pendente') {
            return; // idempotente
        }

        $lote = $arquivo->lote()->withoutGlobalScopes()->first();
        $empresa = Empresa::withoutGlobalScopes()->with('estabelecimentos')->findOrFail($lote->empresa_id);

        try {
            $conteudo = Storage::disk(ImportarLoteXml::DISCO)->get($arquivo->arquivo);
            $dados = $this->leitor->ler((string) $conteudo);

            $dados instanceof DadosCancelamento
                ? $this->aplicarCancelamento($arquivo, $empresa, $dados)
                : $this->importarNota($arquivo, $empresa, $dados, (string) $conteudo, $lote->enviado_por_id);
        } catch (RegraDeNegocioException $e) {
            $arquivo->update(['resultado' => 'rejeitada', 'motivo' => $e->getMessage()]);
        }

        $lote->recalcularTotais();
    }

    private function importarNota(ArquivoImportacaoXml $arquivo, Empresa $empresa, DadosNotaFiscal $dados, string $conteudo, ?int $enviadoPor): void
    {
        RegraDeNegocioException::se(! $dados->autorizada(), 'Nota sem protocolo de autorização da SEFAZ (RN-712).');

        $classificacao = $this->classificador->classificar($dados, $empresa->estabelecimentos->pluck('cnpj')->all());
        RegraDeNegocioException::se($classificacao === null, 'A empresa não é emitente nem destinatária desta nota (RN-713).');

        // RN-714 — duplicidade pela chave de acesso
        $existente = NotaFiscal::withoutGlobalScopes()->where('empresa_id', $empresa->id)->where('chave_acesso', $dados->chave)->first();
        if ($existente) {
            $arquivo->update(['resultado' => 'duplicada', 'motivo' => 'Nota já importada anteriormente.', 'nota_fiscal_id' => $existente->id, 'competencia' => $existente->competencia]);

            return;
        }

        $mes = $this->classificador->competencia($dados);
        $competencia = $this->obterCompetencia->executar($empresa, $mes);
        RegraDeNegocioException::se($competencia->estaFechada(), "Competência {$mes->paraTela()} fechada — fale com o escritório (RN-724).");

        DB::transaction(function () use ($arquivo, $empresa, $dados, $conteudo, $classificacao, $mes, $competencia, $enviadoPor) {
            // RN-750 — XML original guardado sem alteração, com hash
            $destino = "escritorios/{$empresa->escritorio_id}/empresas/{$empresa->id}/notas/{$mes->paraUrl()}/{$dados->chave}.xml";
            Storage::disk(NotaFiscal::DISCO)->put($destino, $conteudo);

            $cancelamento = CancelamentoPendente::where('empresa_id', $empresa->id)->where('chave_acesso', $dados->chave)->first();

            $nota = NotaFiscal::withoutGlobalScopes()->create([
                'escritorio_id' => $empresa->escritorio_id,
                'empresa_id' => $empresa->id,
                'competencia' => $mes->paraBanco(),
                'chave_acesso' => $dados->chave,
                'modelo' => $dados->modelo,
                'serie' => $dados->serie,
                'numero' => $dados->numero,
                'data_emissao' => $dados->dataEmissao,
                'tipo_operacao' => $dados->tipoOperacao,
                'natureza_operacao' => $dados->naturezaOperacao,
                'emitente_documento' => $dados->emitenteDocumento,
                'emitente_nome' => $dados->emitenteNome,
                'emitente_uf' => $dados->emitenteUf,
                'destinatario_documento' => $dados->destinatarioDocumento,
                'destinatario_nome' => $dados->destinatarioNome,
                'destinatario_uf' => $dados->destinatarioUf,
                'cfops' => $dados->cfops,
                'valor_total' => $dados->valorTotal,
                'valor_produtos' => $dados->valorProdutos,
                'valor_icms' => $dados->valorIcms,
                'valor_ipi' => $dados->valorIpi,
                'protocolo' => $dados->protocolo,
                'autorizada_em' => $dados->autorizadaEm,
                'classificacao' => $classificacao,
                // RN-731 — cancelamento recebido antes da nota
                'situacao' => $cancelamento ? SituacaoNota::Cancelada : SituacaoNota::Autorizada,
                'cancelada_em' => $cancelamento?->cancelada_em,
                'protocolo_cancelamento' => $cancelamento?->protocolo,
                'arquivo' => $destino,
                'hash_sha256' => hash('sha256', $conteudo),
                'nome_original' => $arquivo->nome_original,
                'lote_id' => $arquivo->lote_id,
                'enviado_por_id' => $enviadoPor,
            ]);
            $cancelamento?->delete();

            $arquivo->update(['resultado' => 'importada', 'nota_fiscal_id' => $nota->id, 'competencia' => $mes->paraBanco()]);

            $this->vincularAoItem($competencia->id, $classificacao, $enviadoPor);
        });
    }

    /** RN-717 — o item "Notas fiscais de saída/entrada" da competência passa para em avaliação. */
    private function vincularAoItem(int $competenciaId, ClassificacaoNota $classificacao, ?int $enviadoPor): void
    {
        $chave = $classificacao === ClassificacaoNota::Saida ? TipoDocumento::CHAVE_NOTAS_SAIDA : TipoDocumento::CHAVE_NOTAS_ENTRADA;

        $item = ItemCompetencia::withoutGlobalScopes()
            ->where('competencia_id', $competenciaId)
            ->whereHas('tipoDocumento', fn ($q) => $q->withoutGlobalScopes()->where('chave_sistema', $chave))
            ->first();

        if ($item && in_array($item->situacao, [SituacaoItem::Pendente, SituacaoItem::Recusado], true)) {
            $item->update(['situacao' => SituacaoItem::EmAvaliacao, 'enviado_por_id' => $enviadoPor, 'enviado_em' => now(), 'sem_movimento' => false]);
        }
    }

    private function aplicarCancelamento(ArquivoImportacaoXml $arquivo, Empresa $empresa, DadosCancelamento $dados): void
    {
        RegraDeNegocioException::se(! $dados->registrado(), 'Evento de cancelamento não registrado pela SEFAZ.');

        $nota = NotaFiscal::withoutGlobalScopes()->where('empresa_id', $empresa->id)->where('chave_acesso', $dados->chave)->first();

        if (! $nota) {
            CancelamentoPendente::updateOrCreate(
                ['empresa_id' => $empresa->id, 'chave_acesso' => $dados->chave],
                ['protocolo' => $dados->protocolo, 'cancelada_em' => $dados->canceladaEm],
            );
            $arquivo->update(['resultado' => 'importada', 'motivo' => 'Cancelamento guardado: será aplicado quando a nota for enviada (RN-731).']);

            return;
        }

        $nota->update(['situacao' => SituacaoNota::Cancelada, 'cancelada_em' => $dados->canceladaEm, 'protocolo_cancelamento' => $dados->protocolo]);
        $arquivo->update(['resultado' => 'importada', 'motivo' => 'Cancelamento aplicado à nota.', 'nota_fiscal_id' => $nota->id, 'competencia' => $nota->competencia]);
    }
}
