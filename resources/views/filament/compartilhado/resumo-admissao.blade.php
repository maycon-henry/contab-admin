{{-- regras.md RN-586 — resumo da admissão antes do envio ao escritório.
     Ao ficar visível (troca para a aba), pede ao servidor um novo render, que leva junto o que foi digitado. --}}
<div class="flex flex-col gap-6 text-sm" x-data x-intersect="$wire.$refresh()">
    @if ($resumo['pendencias'] === [])
        <x-filament::callout color="success" icon="heroicon-o-check-circle" heading="Tudo pronto para enviar">
            <x-slot name="description">
                Confira os dados abaixo. Ao enviar, a admissão vai para a análise do escritório e não poderá mais ser alterada por você.
            </x-slot>
        </x-filament::callout>
    @else
        <x-filament::callout color="warning" icon="heroicon-o-exclamation-triangle" heading="Falta preencher antes de enviar">
            <x-slot name="description">
                <ul class="list-disc ps-5">
                    @foreach ($resumo['pendencias'] as $pendencia)
                        <li>{{ $pendencia }}</li>
                    @endforeach
                </ul>
            </x-slot>
        </x-filament::callout>
    @endif

    @foreach ($resumo['secoes'] as $titulo => $linhas)
        <section>
            <h3 class="mb-2 text-base font-semibold">{{ $titulo }}</h3>
            <dl class="grid grid-cols-1 gap-x-6 gap-y-2 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($linhas as $linha)
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">{{ $linha['rotulo'] }}</dt>
                        <dd @class(['font-medium', 'text-danger-600 dark:text-danger-400' => $linha['falta']])>
                            {{ $linha['valor'] ?? ($linha['falta'] ? 'Não informado (obrigatório)' : '—') }}
                        </dd>
                    </div>
                @endforeach
            </dl>
        </section>
    @endforeach

    <section>
        <h3 class="mb-2 text-base font-semibold">Documentos</h3>
        <ul class="flex flex-col gap-1">
            @foreach ($resumo['documentos'] as $documento)
                <li @class(['text-danger-600 dark:text-danger-400' => $documento['obrigatorio'] && ! $documento['anexado']])>
                    {{ $documento['anexado'] ? '✔' : ($documento['obrigatorio'] ? '✖' : '—') }}
                    {{ $documento['rotulo'] }}{{ $documento['obrigatorio'] ? ' *' : '' }}
                    @unless ($documento['anexado'])
                        <span class="text-gray-500 dark:text-gray-400">({{ $documento['obrigatorio'] ? 'obrigatório, não anexado' : 'não anexado' }})</span>
                    @endunless
                </li>
            @endforeach
        </ul>
    </section>

    @if ($salvo)
        <p class="text-gray-500 dark:text-gray-400">{{ $salvo }}</p>
    @endif
</div>
