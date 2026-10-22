<?php

namespace App\Filament\Compartilhado\Concerns;

use App\Actions\Admissao\PreencherComDocumento;
use Filament\Actions\Action;
use Illuminate\Support\HtmlString;

/**
 * Páginas de criação que usam o FormularioAdmissao: aviso do preenchimento automático por
 * documento (regras.md RN-579), aberto ao carregar a página.
 */
trait FormularioDeAdmissao
{
    /** Abre o aviso ao carregar a página (só com a leitura automática ligada e sem aba escolhida no endereço). */
    protected function abrirAvisoPreenchimentoAutomatico(): void
    {
        if (app(PreencherComDocumento::class)->disponivel() && ! request()->has('aba')) {
            $this->defaultAction = 'avisoPreenchimentoAutomatico';
        }
    }

    public function avisoPreenchimentoAutomaticoAction(): Action
    {
        return Action::make('avisoPreenchimentoAutomatico')
            ->modalIcon('heroicon-o-sparkles')
            ->modalHeading('Preencha mais rápido anexando os documentos')
            ->modalDescription(new HtmlString(
                'Na aba <strong>Documentos</strong>, anexe o documento de identificação com foto (RG, CNH ou CIN) e o comprovante de residência. '
                .'Os dados pessoais encontrados (nome, CPF, nascimento, endereço...) preenchem automaticamente os campos que ainda estão vazios.<br><br>'
                .'O que você já tiver preenchido <strong>não é alterado</strong>. A leitura é feita por inteligência artificial e pode errar: '
                .'confira tudo na aba <strong>Resumo e envio</strong> antes de enviar ao escritório.'
            ))
            ->modalSubmitActionLabel('Ir para Documentos')
            ->modalCancelActionLabel('Preencher manualmente')
            // a página acabou de abrir, nada digitado se perde
            ->action(function () {
                $endereco = (string) (request()->header('Referer') ?: static::getUrl());

                $this->redirect($endereco.(str_contains($endereco, '?') ? '&' : '?').'aba=documentos');
            });
    }
}
