<?php

namespace App\Filament\Portal\Resources\DocumentosDoMes\Pages;

use App\Actions\Competencias\ObterCompetencia;
use App\Domain\Competencia as Mes;
use App\Filament\Portal\Resources\DocumentosDoMes\DocumentoDoMesResource;
use Filament\Resources\Pages\ListRecords;

class ListDocumentosDoMes extends ListRecords
{
    protected static string $resource = DocumentoDoMesResource::class;

    protected ?string $subheading = 'Envie aqui os documentos que o escritório precisa receber de você todo mês.';

    public function mount(): void
    {
        // RN-621 — garante que a competência padrão e a atual existam (criadas sob demanda)
        $empresa = filament()->getTenant();
        foreach ([Mes::padrao(), Mes::atual()] as $mes) {
            if ($mes->primeiroDia()->gte($empresa->competenciaInicial()->primeiroDia())) {
                app(ObterCompetencia::class)->executar($empresa, $mes);
            }
        }

        parent::mount();
    }
}
