<?php

namespace App\Jobs;

use App\Actions\Cobranca\EnviarCobrancaDocumentacao;
use App\Models\Empresa;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class EnviarCobrancaJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $empresaId, public readonly string $data) {}

    public function handle(EnviarCobrancaDocumentacao $enviar): void
    {
        $empresa = Empresa::withoutGlobalScopes()->find($this->empresaId);

        if ($empresa) {
            $enviar->executar($empresa, CarbonImmutable::parse($this->data));
        }
    }
}
