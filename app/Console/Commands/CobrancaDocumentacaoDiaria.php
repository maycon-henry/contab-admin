<?php

namespace App\Console\Commands;

use App\Actions\Cobranca\EnviarCobrancaDocumentacao;
use App\Domain\Cobranca\SelecionadorEmpresasParaCobranca;
use App\Jobs\EnviarCobrancaJob;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/** regras.md seção 16.1 — AGENTS.md seção 3.6 */
class CobrancaDocumentacaoDiaria extends Command
{
    protected $signature = 'cobranca:documentacao-diaria
        {--data= : Simula outro dia (AAAA-MM-DD)}
        {--empresa= : Restringe a uma empresa}
        {--simular : Lista o que seria feito, sem enviar nem gravar}
        {--sincrono : Envia na hora, sem passar pela fila}';

    protected $description = 'Envia às empresas a cobrança diária de documentação pendente';

    public function handle(SelecionadorEmpresasParaCobranca $selecionador, EnviarCobrancaDocumentacao $enviar): int
    {
        $data = CarbonImmutable::parse($this->option('data') ?? 'today');

        if (! $selecionador->ehDiaDeEnvio($data)) {
            $this->info("{$data->format('d/m/Y')} não é dia útil: nenhuma cobrança enviada (RN-1312).");

            return self::SUCCESS;
        }

        $empresas = $selecionador->selecionar($data, $this->option('empresa') ? (int) $this->option('empresa') : null);
        $this->info("Competência de referência: {$selecionador->competenciaReferencia($data)->paraTela()} — {$empresas->count()} empresa(s) a cobrar.");

        foreach ($empresas as $empresa) {
            $itens = $selecionador->itensCobraveis($empresa, $data);
            $this->line("- {$empresa->razao_social}: {$itens->count()} documento(s)");

            if ($this->option('simular')) {
                continue;
            }

            if ($this->option('sincrono')) {
                $this->line('  resultado: '.$enviar->executar($empresa, $data));
            } else {
                EnviarCobrancaJob::dispatch($empresa->id, $data->toDateString());
            }
        }

        return self::SUCCESS;
    }
}
