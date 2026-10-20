<x-filament-panels::page>
    {{ $this->form }}

    @php($linhas = $this->linhas())

    <x-filament::section>
        <x-slot name="heading">Por colaborador</x-slot>
        <x-slot name="description">"Admissões concluídas" é o número principal. Registros de quem iniciou e de quem admitiu não podem ser alterados (RN-551).</x-slot>

        @if ($linhas->isEmpty())
            <p class="text-sm text-gray-500">Nenhuma admissão no período.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-left dark:border-white/10">
                            <th class="py-2 pr-4">Colaborador</th>
                            <th class="py-2 pr-4 text-center">Admissões concluídas</th>
                            <th class="py-2 pr-4 text-center">Atendimentos iniciados</th>
                            <th class="py-2 pr-4 text-center">Devoluções feitas</th>
                            <th class="py-2 pr-4 text-center">Tempo médio (solicitação → admissão)</th>
                            <th class="py-2 pr-4 text-center">Tempo médio (assumiu → admissão)</th>
                            <th class="py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($linhas as $linha)
                            <tr class="border-b border-gray-100 dark:border-white/5">
                                <td class="py-2 pr-4 font-medium">{{ $linha->nome }}</td>
                                <td class="py-2 pr-4 text-center text-lg font-semibold text-primary-600">{{ $linha->concluidas }}</td>
                                <td class="py-2 pr-4 text-center">{{ $linha->iniciadas }}</td>
                                <td class="py-2 pr-4 text-center">{{ $linha->devolucoes }}</td>
                                <td class="py-2 pr-4 text-center">{{ $linha->dias_solicitacao !== null ? number_format($linha->dias_solicitacao, 1, ',', '.').' dia(s)' : '—' }}</td>
                                <td class="py-2 pr-4 text-center">{{ $linha->dias_atendimento !== null ? number_format($linha->dias_atendimento, 1, ',', '.').' dia(s)' : '—' }}</td>
                                <td class="py-2 text-right">
                                    @if ($linha->concluidas > 0)
                                        <x-filament::link tag="button" wire:click="detalhar({{ $linha->id }})">
                                            {{ $this->detalheUserId === $linha->id ? 'Ocultar' : 'Ver admitidos' }}
                                        </x-filament::link>
                                    @endif
                                </td>
                            </tr>
                            @if ($this->detalheUserId === $linha->id)
                                <tr>
                                    <td colspan="7" class="bg-gray-50 p-3 dark:bg-white/5">
                                        <table class="w-full text-xs">
                                            <tr class="text-left text-gray-500"><th>Funcionário</th><th>Empresa</th><th>Cargo</th><th>Admissão</th><th>Solicitada em</th><th>Atendimento iniciado por</th><th>Concluída em</th></tr>
                                            @foreach ($this->admissoesDoColaborador() as $c)
                                                <tr>
                                                    <td class="py-1">{{ $c->funcionario->nome }}</td>
                                                    <td>{{ $c->empresa->razao_social }}</td>
                                                    <td>{{ $c->cargo }}</td>
                                                    <td>{{ $c->data_admissao?->format('d/m/Y') }}</td>
                                                    <td>{{ $c->solicitado_em?->format('d/m/Y H:i') }}</td>
                                                    <td>{{ $c->atendimentoIniciadoPor?->name }}</td>
                                                    <td>{{ $c->admitido_em?->format('d/m/Y H:i') }}</td>
                                                </tr>
                                            @endforeach
                                        </table>
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
