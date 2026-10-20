<?php

namespace App\Jobs;

use App\Actions\NotasFiscais\ProcessarArquivoXml;
use App\Models\ArquivoImportacaoXml;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Um job por arquivo XML — AGENTS.md seção 5.3 (idempotente pela situação do arquivo e pela chave). */
class ProcessarArquivoXmlJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $arquivoId) {}

    public function handle(ProcessarArquivoXml $processar): void
    {
        $arquivo = ArquivoImportacaoXml::find($this->arquivoId);

        if ($arquivo) {
            $processar->executar($arquivo);
        }
    }
}
