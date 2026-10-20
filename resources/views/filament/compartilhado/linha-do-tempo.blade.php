{{-- regras.md RN-575 — linha do tempo da admissão --}}
@php($historicos = $getRecord()?->historicos()->with('user')->get() ?? collect())

@if ($historicos->isEmpty())
    <p class="text-sm text-gray-500 dark:text-gray-400">Nenhum registro ainda.</p>
@else
    <div class="overflow-x-auto">
        <table class="w-full text-start text-sm">
            <thead>
                <tr class="border-b border-gray-200 dark:border-white/10">
                    <th class="px-3 py-2 text-start font-semibold">Quando</th>
                    <th class="px-3 py-2 text-start font-semibold">Etapa</th>
                    <th class="px-3 py-2 text-start font-semibold">Situação</th>
                    <th class="px-3 py-2 text-start font-semibold">Por</th>
                    <th class="px-3 py-2 text-start font-semibold">Detalhe</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                @foreach ($historicos as $h)
                    <tr>
                        <td class="whitespace-nowrap px-3 py-2">{{ $h->created_at->format('d/m/Y H:i') }}</td>
                        <td class="whitespace-nowrap px-3 py-2">{{ ucfirst(str_replace('_', ' ', $h->acao)) }}</td>
                        <td class="whitespace-nowrap px-3 py-2">
                            @if ($h->situacao_nova)
                                <x-filament::badge :color="$h->situacao_nova->getColor()">
                                    {{ $h->situacao_nova->getLabel() }}
                                </x-filament::badge>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-3 py-2">{{ $h->user?->name ?? 'Sistema' }}</td>
                        <td class="px-3 py-2">{{ $h->descricao }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
