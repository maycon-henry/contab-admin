<?php

namespace App\Filament\Compartilhado\Concerns;

/**
 * Botão "Voltar" das telas de um registro: volta para a tela de onde o usuário veio
 * (ex.: lista de funcionários da empresa), aceitando só endereços do próprio sistema.
 */
trait VoltaParaOrigem
{
    public string $voltarPara = '';

    protected function definirVoltarPara(?string $url): void
    {
        $this->voltarPara = $this->urlDeRetorno($url);
    }

    /** Destino quando não se sabe de onde o usuário veio. */
    protected function urlPadraoDeRetorno(): string
    {
        return static::getResource()::getUrl('index');
    }

    private function urlDeRetorno(?string $url): string
    {
        $proprio = static::getResource()::getUrl('view', ['record' => $this->getRecord()]);
        $caminho = $url ? strtok($url, '?') : null;

        if (! $url || ! str_starts_with($url, url('/')) || $caminho === $proprio || str_starts_with($caminho, $proprio.'/')) {
            return $this->urlPadraoDeRetorno();
        }

        return $url;
    }
}
