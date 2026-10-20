<?php

namespace App\Filament\Empresa\Widgets;

use App\Actions\Cobranca\EnviarCobrancaDocumentacao;
use App\Actions\Despesas\RegistrarPagamentoDespesa;
use App\Actions\Itens\ConcluirTarefa;
use App\Domain\Consultas\PendenciasDaEmpresa;
use App\Enums\DocumentoAdmissao;
use App\Enums\Permissao;
use App\Enums\SituacaoContrato;
use App\Filament\Compartilhado\CampoMoeda;
use App\Filament\Empresa\Resources\Funcionarios\CadastroFuncionarioResource;
use App\Filament\Escritorio\Resources\FilaAvaliacao\FilaAvaliacaoResource;
use App\Filament\Support\Acao;
use App\Models\Contrato;
use App\Models\Despesa;
use App\Models\Documento;
use App\Models\ItemCompetencia;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Ações pendentes da empresa em uma tabela única — regras.md RN-1520 a RN-1525.
 * Cada linha termina com o botão que resolve a pendência.
 */
class AcoesPendentes extends TableWidget
{
    protected static ?int $sort = 2;

    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    /** Tipos de linha — definem o botão de solução. */
    private const DOCUMENTO_EMPRESA = 'documento_empresa';

    private const AVALIAR = 'avaliar';

    private const TAREFA = 'tarefa';

    private const FUNCIONARIO = 'funcionario';

    private const DESPESA = 'despesa';

    private const DOCUMENTO_FUNCIONARIO = 'documento_funcionario';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Ações pendentes')
            ->records(fn () => $this->linhas())
            ->columns([
                TextColumn::make('assunto')->label('Assunto')->badge()->color('gray'),
                TextColumn::make('pendencia')->label('Pendência')->weight('medium')->wrap()
                    ->description(fn (array $record) => $record['detalhe']),
                TextColumn::make('data')->label('Prazo / vencimento')->date('d/m/Y')->placeholder('—')
                    ->color(fn (array $record) => $record['atrasado'] ? 'danger' : null)
                    ->weight(fn (array $record) => $record['atrasado'] ? 'bold' : null),
                TextColumn::make('situacao')->label('Situação')->badge()->color(fn (array $record) => $record['cor']),
                TextColumn::make('quem')->label('Quem age')->badge()
                    ->color(fn (string $state) => $state === 'Empresa' ? 'warning' : 'info'),
            ])
            ->recordActions([
                // documentos que a empresa deve: cobrar agora (RN-1321)
                Action::make('cobrar')->label('Cobrar empresa')->icon('heroicon-o-envelope')->button()->color('warning')
                    ->visible(fn (array $record) => $record['tipo'] === self::DOCUMENTO_EMPRESA && auth()->user()->can(Permissao::CobrancaGerenciar->value))
                    ->requiresConfirmation()
                    ->modalHeading('Enviar cobrança agora')
                    ->modalDescription('Envia às pessoas da empresa a lista de todos os documentos pendentes e recusados.')
                    ->action(function () {
                        $resultado = app(EnviarCobrancaDocumentacao::class)->executar(Filament::getTenant(), CarbonImmutable::today(), 'manual', auth()->user());

                        match ($resultado) {
                            'sem_pendencias' => Notification::make()->info()->title('A empresa não tem documentos pendentes.')->send(),
                            'sem_destinatario' => Notification::make()->warning()->title('Cobrança não enviada: a empresa não tem usuários no portal (RN-1318).')->send(),
                            default => Notification::make()->success()->title('Cobrança enviada.')->send(),
                        };
                    }),

                Action::make('avaliar')->label('Avaliar')->icon('heroicon-o-clipboard-document-check')->button()
                    ->visible(fn (array $record) => $record['tipo'] === self::AVALIAR && auth()->user()->can(Permissao::DocumentosAvaliar->value))
                    ->url(fn () => FilaAvaliacaoResource::getUrl('index', ['filters' => ['empresa_id' => ['value' => Filament::getTenant()->getKey()]]], panel: 'escritorio')),

                Action::make('concluir')->label('Concluir tarefa')->icon('heroicon-o-check')->button()->color('success')
                    ->visible(fn (array $record) => $record['tipo'] === self::TAREFA)
                    ->requiresConfirmation()
                    ->action(fn (array $record, Action $action) => Acao::rodar(
                        fn () => app(ConcluirTarefa::class)->executar(ItemCompetencia::findOrFail($record['id']), auth()->user()), 'Tarefa concluída.', $action)),

                // RN-567 — documento trocado de funcionário admitido: avaliar na ficha (aba Documentos)
                Action::make('avaliarDocumento')->label('Avaliar documento')->icon('heroicon-o-document-check')->button()
                    ->visible(fn (array $record) => $record['tipo'] === self::DOCUMENTO_FUNCIONARIO && CadastroFuncionarioResource::canViewAny())
                    ->url(fn (array $record) => CadastroFuncionarioResource::getUrl('view', ['record' => $record['id']])),

                Action::make('cadastro')->label('Abrir cadastro')->icon('heroicon-o-identification')->button()
                    ->visible(fn (array $record) => $record['tipo'] === self::FUNCIONARIO && CadastroFuncionarioResource::canViewAny())
                    ->url(fn (array $record) => CadastroFuncionarioResource::getUrl('view', ['record' => $record['id']])),

                // RN-1605 — pagamento direto daqui
                Action::make('pagar')->label('Registrar pagamento')->icon('heroicon-o-banknotes')->button()->color('success')
                    ->visible(fn (array $record) => $record['tipo'] === self::DESPESA && auth()->user()->can(Permissao::DespesasGerenciar->value))
                    ->modalHeading(fn (array $record) => 'Registrar pagamento — '.$record['pendencia'])
                    ->modalSubmitActionLabel('Registrar pagamento')
                    ->modalWidth('lg')
                    ->fillForm(fn (array $record) => ['valor_pago' => $record['valor'], 'data_pagamento' => today()->toDateString()])
                    ->schema([
                        CampoMoeda::make('valor_pago')->label('Valor pago')->required(),
                        DatePicker::make('data_pagamento')->label('Data do pagamento')->displayFormat('d/m/Y')->native(false)->maxDate(today())->required(),
                    ])
                    ->action(fn (array $record, array $data, Action $action) => Acao::rodar(
                        fn () => app(RegistrarPagamentoDespesa::class)->executar(Despesa::findOrFail($record['id']), auth()->user(), $data['valor_pago'], $data['data_pagamento']),
                        'Pagamento registrado.', $action)),
            ])
            ->emptyStateHeading('Nenhuma pendência para esta empresa')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->paginated([10, 25, 50]);
    }

    /**
     * Linhas da tabela, das mais urgentes (atrasadas, prazo mais antigo) para as demais.
     *
     * @return array<string, array<string, mixed>>
     */
    private function linhas(): array
    {
        $pendencias = PendenciasDaEmpresa::para(Filament::getTenant());

        $linhas = collect()
            ->merge($pendencias->documentosDaEmpresa()->with('competencia')->get()->map(fn (ItemCompetencia $i) => $this->linha(
                self::DOCUMENTO_EMPRESA, $i->id, 'Documento', $i->nome,
                'Competência '.$i->competencia->rotulo.($i->motivo_recusa ? ' · Recusado: '.$i->motivo_recusa->getLabel() : ''),
                $i->prazo, $i->estaAtrasado(),
                $i->estaAtrasado() ? 'Atrasado há '.$i->diasDeAtraso().' dia(s)' : $i->situacao->getLabel(),
                $i->estaAtrasado() ? 'danger' : $i->situacao->getColor(), 'Empresa',
            )))
            ->merge($pendencias->documentosParaAvaliar()->with('competencia')->get()->map(fn (ItemCompetencia $i) => $this->linha(
                self::AVALIAR, $i->id, 'Documento', 'Avaliar: '.$i->nome,
                'Competência '.$i->competencia->rotulo.($i->enviado_em ? ' · enviado em '.$i->enviado_em->format('d/m/Y') : ''),
                $i->prazo, false, $i->situacao->getLabel(), $i->situacao->getColor(), 'Escritório',
            )))
            ->merge($pendencias->tarefasDoEscritorio()->with('competencia')->get()->map(fn (ItemCompetencia $i) => $this->linha(
                self::TAREFA, $i->id, 'Tarefa', $i->nome, 'Competência '.$i->competencia->rotulo,
                $i->prazo, $i->prazo?->lt(today()) ?? false, $i->situacao->getLabel(), $i->situacao->getColor(), 'Escritório',
            )))
            ->merge($pendencias->movimentosDeFuncionarios()->with('funcionario')->get()->map(fn (Contrato $c) => $this->linha(
                self::FUNCIONARIO, $c->id, 'Funcionário', $c->funcionario->nome.($c->cargo ? ' — '.$c->cargo : ''),
                match ($c->situacao) {
                    SituacaoContrato::Rascunho => 'Admissão em preenchimento pela empresa',
                    SituacaoContrato::Devolvida => 'Admissão devolvida: '.$c->motivo_devolucao,
                    SituacaoContrato::EmAnalise => $c->responsavel_id ? 'Admissão em análise pelo escritório' : 'Admissão aguardando um responsável no escritório',
                    default => 'Desligamento em andamento',
                },
                $c->situacao === SituacaoContrato::EmDesligamento ? $c->data_desligamento : $c->data_admissao_prevista,
                $c->situacao->emAdmissao() && ($c->data_admissao_prevista?->lt(today()) ?? false),
                $c->situacao->getLabel(), $c->situacao->getColor(), PendenciasDaEmpresa::responsavelPeloContrato($c),
            )))
            ->merge($pendencias->documentosDeFuncionariosParaAvaliar()->with(['documentavel.funcionario', 'enviadoPor'])->get()->map(fn (Documento $d) => $this->linha(
                self::DOCUMENTO_FUNCIONARIO, $d->documentavel_id, 'Funcionário',
                'Avaliar documento: '.(DocumentoAdmissao::tryFrom((string) $d->tipo)?->getLabel() ?? $d->tipo),
                $d->documentavel->funcionario->nome.' · enviado em '.$d->created_at->format('d/m/Y').($d->enviadoPor ? ' por '.$d->enviadoPor->name : ''),
                $d->created_at, false, 'Em análise', 'info', 'Escritório', ['chave' => 'docfunc-'.$d->id],
            )))
            ->merge($pendencias->despesasVencidas()->get()->merge($pendencias->despesasAVencer()->get())->map(fn (Despesa $d) => $this->linha(
                self::DESPESA, $d->id, 'Conta a pagar', $d->descricao,
                'R$ '.number_format((float) $d->valor, 2, ',', '.').($d->estaVencida() ? ' · vencida há '.(int) $d->data_vencimento->diffInDays(today()).' dia(s)' : ' · a vencer'),
                $d->data_vencimento, $d->estaVencida(), $d->situacao(), $d->estaVencida() ? 'danger' : 'warning', 'Empresa',
                ['valor' => $d->valor],
            )));

        return $linhas
            ->sortBy([['atrasado', 'desc'], ['data', 'asc']])
            ->keyBy('chave')
            ->all();
    }

    private function linha(string $tipo, int $id, string $assunto, string $pendencia, ?string $detalhe, $data, bool $atrasado,
        string $situacao, string $cor, string $quem, array $extra = []): array
    {
        return [
            'chave' => "{$tipo}-{$id}",
            'tipo' => $tipo,
            'id' => $id,
            'assunto' => $assunto,
            'pendencia' => $pendencia,
            'detalhe' => $detalhe,
            'data' => $data?->toDateString(),
            'atrasado' => $atrasado,
            'situacao' => $situacao,
            'cor' => $cor,
            'quem' => $quem,
            ...$extra,
        ];
    }
}
