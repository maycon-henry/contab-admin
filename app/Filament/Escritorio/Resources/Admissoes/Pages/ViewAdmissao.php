<?php

namespace App\Filament\Escritorio\Resources\Admissoes\Pages;

use App\Actions\Admissao\AssumirAdmissao;
use App\Actions\Admissao\CancelarAdmissao;
use App\Actions\Admissao\ConcluirAdmissao;
use App\Actions\Admissao\DevolverAdmissao;
use App\Actions\Admissao\SolicitarAdmissao;
use App\Actions\Admissao\TransferirResponsavelAdmissao;
use App\Actions\Desligamento\CancelarDesligamento;
use App\Actions\Desligamento\ConcluirDesligamento;
use App\Domain\Admissao\VerificadorAdmissao;
use App\Domain\Opcoes;
use App\Enums\Permissao;
use App\Enums\SituacaoContrato;
use App\Filament\Compartilhado\Concerns\VoltaParaOrigem;
use App\Filament\Escritorio\Resources\Admissoes\AdmissaoResource;
use App\Filament\Support\Acao;
use App\Models\Contrato;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Text;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class ViewAdmissao extends ViewRecord
{
    use VoltaParaOrigem;

    protected static string $resource = AdmissaoResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->definirVoltarPara(request()->query('voltar') ?: url()->previous());
    }

    public function getTitle(): string|Htmlable
    {
        return $this->getRecord()->funcionario->nome;
    }

    public function getSubheading(): ?string
    {
        $c = $this->getRecord();

        return $c->empresa->razao_social.' — '.$c->situacao->getLabel();
    }

    protected function getHeaderActions(): array
    {
        /** @var Contrato $c */
        $c = $this->getRecord();
        $user = auth()->user();
        $recarregar = fn () => $this->redirect(static::getResource()::getUrl('view', ['record' => $c]));

        return [
            Action::make('assumir')->label('Assumir')->icon('heroicon-o-hand-raised')
                ->visible($c->situacao === SituacaoContrato::EmAnalise && ! $c->responsavel_id)
                ->requiresConfirmation()->modalDescription('Você passa a ser o responsável e fica registrado que iniciou o atendimento (RN-592).')
                ->action(fn (Action $a) => Acao::rodar(fn () => app(AssumirAdmissao::class)->executar($c, $user), 'Admissão assumida.', $a) && $recarregar()),

            // RN-595 — devolver à empresa
            Action::make('devolver')->label('Devolver à empresa')->icon('heroicon-o-arrow-uturn-left')->color('warning')
                ->visible($c->situacao === SituacaoContrato::EmAnalise && $c->responsavel_id)
                ->schema([Textarea::make('motivo')->label('Motivo (a empresa verá este texto)')->required()->rows(3)
                    ->default(fn () => $c->documentos()->where('avaliacao', 'recusado')->get()->map(fn ($d) => "{$d->tipo}: {$d->motivo_recusa}")->implode("\n"))])
                ->action(fn (array $data, Action $a) => Acao::rodar(fn () => app(DevolverAdmissao::class)->executar($c, $user, $data['motivo']), 'Admissão devolvida. A empresa foi avisada.', $a) && $recarregar()),

            // RN-597 / RN-598 — concluir
            Action::make('concluir')->label('Concluir admissão')->icon('heroicon-o-check-badge')->color('success')
                ->visible($c->situacao === SituacaoContrato::EmAnalise && ($c->responsavel_id === $user->id || $user->ehAdministrador()))
                ->modalHeading('Concluir admissão')->modalWidth('3xl')
                ->fillForm(fn () => [
                    'matricula' => $c->matricula, 'data_admissao' => ($c->data_admissao ?? $c->data_admissao_prevista)?->toDateString(),
                    'categoria' => $c->categoria ?? '101', 'cbo' => $c->cbo, 'natureza_atividade' => $c->natureza_atividade ?? 'urbana',
                    'regime_jornada' => $c->regime_jornada ?? 'controle_horario', 'sindicato_cnpj' => $c->sindicato_cnpj, 'recibo_esocial' => $c->recibo_esocial,
                ])
                ->schema([
                    Text::make(fn () => new HtmlString($this->pendenciasEscritorio($c))),
                    Grid::make(3)->schema([
                        TextInput::make('matricula')->label('Matrícula')->required(),
                        DatePicker::make('data_admissao')->label('Data de admissão efetiva')->required(),
                        Select::make('categoria')->label('Categoria')->options(Opcoes::CATEGORIA)->required(),
                        TextInput::make('cbo')->label('CBO')->required()->length(6),
                        Select::make('natureza_atividade')->label('Natureza da atividade')->options(Opcoes::NATUREZA_ATIVIDADE)->required(),
                        Select::make('regime_jornada')->label('Regime de jornada')->options(Opcoes::REGIME_JORNADA)->required(),
                        TextInput::make('sindicato_cnpj')->label('CNPJ do sindicato'),
                        TextInput::make('recibo_esocial')->label('Recibo do eSocial (S-2200)'),
                    ]),
                ])
                ->action(fn (array $data, Action $a) => Acao::rodar(fn () => app(ConcluirAdmissao::class)->executar($c, $user, $data), 'Funcionário admitido.', $a) && $recarregar()),

            // RN-581 — admissão iniciada pelo escritório: o próprio colaborador solicita
            Action::make('solicitar')->label('Solicitar admissão')->icon('heroicon-o-paper-airplane')
                ->visible($c->situacao->editavelPelaEmpresa())
                ->requiresConfirmation()
                ->action(fn (Action $a) => Acao::rodar(fn () => app(SolicitarAdmissao::class)->executar($c, $user), 'Admissão enviada para a fila.', $a) && $recarregar()),

            Action::make('voltar')->label('Voltar')->icon('heroicon-o-arrow-left')->color('gray')->url($this->voltarPara),

            EditAction::make()->label('Editar')->icon('heroicon-o-pencil-square')->visible(static::getResource()::canEdit($c))
                ->url(static::getResource()::getUrl('edit', ['record' => $c, 'voltar' => $this->voltarPara])),

            Action::make('concluirDesligamento')->label('Concluir desligamento')->icon('heroicon-o-check')->color('danger')
                ->visible($c->situacao === SituacaoContrato::EmDesligamento && $user->can(Permissao::DesligamentosGerenciar->value))
                ->modalWidth('2xl')
                ->schema([
                    ...collect(ConcluirDesligamento::DOCUMENTOS_OBRIGATORIOS)->map(fn ($rotulo, $chave) => FileUpload::make("documentos.{$chave}")->label($rotulo)->required()
                        ->disk('local')->directory('escritorios/'.$user->escritorio_id.'/desligamentos')->visibility('private'))->values()->all(),
                    TextInput::make('valor_liquido_rescisao')->label('Valor líquido da rescisão')->numeric()->prefix('R$')
                        ->helperText('O cálculo das verbas é feito no sistema de folha (RN-549).'),
                ])
                ->action(fn (array $data, Action $a) => Acao::rodar(fn () => app(ConcluirDesligamento::class)->executar($c, $user, $data['documentos'] ?? [], $data['valor_liquido_rescisao'] ?? null), 'Desligamento concluído.', $a) && $recarregar()),

            ActionGroup::make([
                Action::make('transferir')->label('Transferir responsável')->icon('heroicon-o-arrows-right-left')
                    ->visible($c->situacao === SituacaoContrato::EmAnalise && $c->responsavel_id && ($user->ehAdministrador() || $c->responsavel_id === $user->id))
                    ->schema([Select::make('user_id')->label('Novo responsável')->required()
                        ->options(User::where('escritorio_id', $user->escritorio_id)->daEquipe()->where('ativo', true)->pluck('name', 'id'))])
                    ->action(fn (array $data, Action $a) => Acao::rodar(fn () => app(TransferirResponsavelAdmissao::class)->executar($c, $user, User::findOrFail($data['user_id'])), 'Responsável alterado.', $a) && $recarregar()),
                Action::make('cancelar')->label('Cancelar admissão')->icon('heroicon-o-x-mark')->color('danger')
                    ->visible($c->situacao->emAdmissao())
                    ->schema([Textarea::make('justificativa')->label('Justificativa')->required()])
                    ->action(fn (array $data, Action $a) => Acao::rodar(fn () => app(CancelarAdmissao::class)->executar($c, $user, $data['justificativa']), 'Admissão cancelada.', $a) && $recarregar()),
                Action::make('cancelarDesligamento')->label('Cancelar desligamento')->icon('heroicon-o-arrow-uturn-left')
                    ->visible($c->situacao === SituacaoContrato::EmDesligamento)
                    ->schema([Textarea::make('justificativa')->label('Justificativa (ex.: reconsideração do aviso)')->required()])
                    ->action(fn (array $data, Action $a) => Acao::rodar(fn () => app(CancelarDesligamento::class)->executar($c, $user, $data['justificativa']), 'Desligamento cancelado.', $a) && $recarregar()),
            ])->label('Mais')->icon('heroicon-o-ellipsis-vertical')->button()->color('gray'),
        ];
    }

    private function pendenciasEscritorio(Contrato $c): string
    {
        $docs = collect(app(VerificadorAdmissao::class)->pendenciasDoEscritorio($c->load('documentos')))
            ->filter(fn ($p) => str_starts_with($p, 'Documento'));

        return $docs->isEmpty()
            ? '<p style="color:#15803d">✔ Todos os documentos obrigatórios foram aceitos.</p>'
            : '<p style="color:#b91c1c"><strong>Antes de concluir, aceite os documentos na aba "Documentos":</strong><br>'.$docs->map(fn ($d) => '• '.e($d))->implode('<br>').'</p>';
    }
}
