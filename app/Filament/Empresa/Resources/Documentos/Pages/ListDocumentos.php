<?php

namespace App\Filament\Empresa\Resources\Documentos\Pages;

use App\Actions\Competencias\ObterCompetencia;
use App\Domain\Competencia as Mes;
use App\Filament\Empresa\Resources\Documentos\DocumentoResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ListRecords;

class ListDocumentos extends ListRecords
{
    protected static string $resource = DocumentoResource::class;

    public function getSubheading(): ?string
    {
        return Filament::getTenant()->razao_social.' — documentos do mês. O que o escritório envia aqui já fica aceito, sem passar pela avaliação.';
    }

    public function mount(): void
    {
        // RN-621 — garante que a competência padrão e a atual existam (criadas sob demanda)
        $empresa = Filament::getTenant();
        foreach ([Mes::padrao(), Mes::atual()] as $mes) {
            if ($mes->primeiroDia()->gte($empresa->competenciaInicial()->primeiroDia())) {
                app(ObterCompetencia::class)->executar($empresa, $mes);
            }
        }

        parent::mount();
    }
}
