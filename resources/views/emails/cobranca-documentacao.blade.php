<x-mail::message>
# Olá, {{ $nome }}!

A empresa **{{ $empresa }}** tem **{{ $total }} {{ $total === 1 ? 'documento pendente' : 'documentos pendentes' }}** de entrega ao escritório (competência de referência: {{ $competencia }}).

<x-mail::table>
| Documento | Competência | Prazo | Situação |
|:--|:--|:--|:--|
@foreach ($itens as $item)
| {{ $item['nome'] }} | {{ $item['competencia'] }} | {{ $item['prazo'] }} | {{ $item['motivo_recusa'] ? 'Recusado: '.$item['motivo_recusa'] : ($item['dias_atraso'] > 0 ? 'Atrasado há '.$item['dias_atraso'].' dia(s)' : 'Pendente') }} |
@endforeach
</x-mail::table>

@foreach ($itens as $item)
@if ($item['instrucoes'])
**{{ $item['nome'] }}:** {{ $item['instrucoes'] }}

@endif
@endforeach

<x-mail::button :url="$url">
Enviar documentos
</x-mail::button>

Você recebe este lembrete diariamente enquanto houver pendências.
</x-mail::message>
