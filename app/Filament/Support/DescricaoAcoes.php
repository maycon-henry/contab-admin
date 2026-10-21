<?php

namespace App\Filament\Support;

use Filament\Actions\Action;
use Filament\Support\Enums\Size;

/**
 * Ações das linhas das tabelas aparecem só como ícone; o tooltip mostra o nome da ação
 * e esta descrição curta do que ela faz. A chave é o nome da ação (Action::make('...')).
 */
final class DescricaoAcoes
{
    private const DESCRICOES = [
        // genéricas do Filament
        'view' => 'Abre os detalhes do registro.',
        'edit' => 'Altera os dados do registro.',
        'delete' => 'Exclui o registro (fica guardado no histórico).',

        // documentos do mês e fila de avaliação
        'enviar' => 'Anexa o arquivo do documento deste mês.',
        'semMovimento' => 'Informa que não houve movimento no mês.',
        'arquivos' => 'Mostra os arquivos enviados e as avaliações.',
        'aceitar' => 'Aceita o documento como correto e válido.',
        'recusar' => 'Recusa o documento e avisa a empresa do motivo.',
        'dispensar' => 'Marca o item como não aplicável neste mês.',
        'concluirTarefa' => 'Marca a tarefa do escritório como feita.',
        'concluir' => 'Marca a tarefa do escritório como feita.',
        'avaliar' => 'Abre a fila de avaliação desta empresa.',
        'cobrar' => 'Envia agora à empresa a lista do que falta entregar.',
        'quais' => 'Lista os documentos em atraso desta empresa.',
        'detalhe' => 'Mostra os itens incluídos nesta cobrança.',

        // notas fiscais
        'danfe' => 'Abre a nota (DANFE) em PDF numa nova aba.',
        'xml' => 'Baixa o arquivo XML original da nota.',
        'mover' => 'Muda a nota de competência, com justificativa.',

        // despesas
        'pagar' => 'Registra o pagamento: valor pago e data.',
        'documentos' => 'Mostra os boletos, recibos, notas e cheques anexados.',

        // funcionários e admissões
        'contrato' => 'Abre o cadastro completo do funcionário.',
        'cadastro' => 'Abre o cadastro do funcionário.',
        'avaliarDocumento' => 'Abre a ficha para aceitar ou recusar o documento enviado.',
        'assumir' => 'Torna você o responsável por esta admissão.',
        'visualizar' => 'Mostra o cadastro do funcionário, sem alterar.',
        'atualizar' => 'Troca ou inclui documentos do funcionário.',

        // empresas e equipe
        'abrir' => 'Entra na empresa: pendências, notas, funcionários e cadastro.',
        'ajustar' => 'Dispensa o item ou muda prazo e obrigatoriedade só para esta empresa.',
        'reenviarConvite' => 'Envia um novo link de convite (o anterior deixa de valer).',
        'desativar' => 'Corta o acesso da pessoa imediatamente.',
        'ativar' => 'Devolve o acesso da pessoa ao sistema.',
        'titularidade' => 'Passa a administração titular do escritório para esta pessoa.',
    ];

    public static function para(string $nomeDaAcao): ?string
    {
        return self::DESCRICOES[$nomeDaAcao] ?? null;
    }

    /** Ação de linha só com ícone; nome e descrição vão para o tooltip. Sem ícone, mantém o texto. */
    public static function aplicar(Action $action): void
    {
        // Editar/Excluir do Filament declaram o ícone como "ícone de tabela"
        if (blank($action->getIcon()) && ! $action->hasTableIcon()) {
            return;
        }

        // tooltip que a própria ação já tinha (pode depender do registro): avaliado só na hora de exibir
        $tooltipProprio = (fn () => $this->tooltip)->call($action);

        // o Filament clona a ação para cada linha: usa a ação injetada (já com o registro), não a original
        $action->iconButton()->size(Size::Large)->tooltip(function (Action $action) use ($tooltipProprio) {
            $descricao = $action->evaluate($tooltipProprio) ?: self::para($action->getName());

            return trim(strip_tags((string) $action->getLabel()).($descricao ? ': '.$descricao : ''));
        });
    }
}
