<?php

namespace App\Console\Commands;

use App\Actions\Competencias\ObterCompetencia;
use App\Domain\Competencia;
use App\Enums\SituacaoEmpresa;
use App\Models\Empresa;
use Illuminate\Console\Command;

/** regras.md RN-620 — cria a competência do mês para cada empresa ativa (idempotente). */
class GerarCompetenciasMensal extends Command
{
    protected $signature = 'competencias:gerar-mensal {--competencia= : AAAA-MM (padrão: mês atual)} {--simular}';

    protected $description = 'Cria a competência do mês, com os itens da rotina, para cada empresa ativa';

    public function handle(ObterCompetencia $obter): int
    {
        $mes = $this->option('competencia') ? Competencia::parse($this->option('competencia')) : Competencia::atual();
        $empresas = Empresa::withoutGlobalScopes()->where('situacao', SituacaoEmpresa::Ativa)->get();

        foreach ($empresas as $empresa) {
            if (! $this->option('simular')) {
                $obter->executar($empresa, $mes);
            }
            $this->line("- {$empresa->razao_social}: {$mes->paraTela()}");
        }

        $this->info("{$empresas->count()} empresa(s) processada(s).");

        return self::SUCCESS;
    }
}
