<?php

namespace App\Filament\Escritorio\Resources\Admissoes\Pages;

use App\Actions\Admissao\SalvarAdmissao;
use App\Actions\Desligamento\IniciarDesligamento;
use App\Domain\Desligamento\CalculadoraAvisoPrevio;
use App\Domain\Opcoes;
use App\Enums\MotivoDesligamento;
use App\Enums\Permissao;
use App\Enums\SituacaoContrato;
use App\Enums\TipoAviso;
use App\Exceptions\RegraDeNegocioException;
use App\Filament\Compartilhado\Concerns\VoltaParaOrigem;
use App\Filament\Compartilhado\FormularioAdmissao;
use App\Filament\Escritorio\Resources\Admissoes\AdmissaoResource;
use App\Filament\Support\Acao;
use App\Models\Contrato;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;

/**
 * Edição da admissão (RN-594 — correções durante a análise ficam no histórico) e,
 * para funcionário já admitido, do cadastro pessoal e do registro de desligamento.
 */
class EditAdmissao extends EditRecord
{
    use VoltaParaOrigem;

    protected static string $resource = AdmissaoResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->definirVoltarPara(request()->query('voltar'));
    }

    public function getTitle(): string|Htmlable
    {
        return $this->getRecord()->situacao === SituacaoContrato::Admitido
            ? 'Editar funcionário — '.$this->getRecord()->funcionario->nome
            : 'Editar admissão — '.$this->getRecord()->funcionario->nome;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components(FormularioAdmissao::componentes(escritorio: true, empresaId: fn () => $this->getRecord()->empresa_id));
    }

    protected function getHeaderActions(): array
    {
        /** @var Contrato $c */
        $c = $this->getRecord();
        $user = auth()->user();

        return [
            Action::make('voltar')->label('Voltar')->icon('heroicon-o-arrow-left')->color('gray')
                ->url(fn () => $this->urlVisualizacao()),

            // RN-540 a RN-542 — desligamento
            Action::make('iniciarDesligamento')->label('Registrar desligamento')->icon('heroicon-o-user-minus')->color('danger')
                ->visible($c->situacao === SituacaoContrato::Admitido && $user->can(Permissao::DesligamentosGerenciar->value))
                ->modalWidth('3xl')
                ->schema([
                    Grid::make(2)->schema([
                        Select::make('motivo_desligamento')->label('Motivo (tabela 19 do eSocial)')->options(MotivoDesligamento::class)->required()->live()->columnSpanFull()
                            ->afterStateUpdated(fn (Get $get, Set $set) => $this->calcularAviso($c, $get, $set)),
                        DatePicker::make('data_desligamento')->label('Data de desligamento (último dia)')->required()->live()
                            ->afterStateUpdated(fn (Get $get, Set $set) => $this->calcularAviso($c, $get, $set)),
                        Select::make('tipo_aviso')->label('Aviso prévio')->options(TipoAviso::class)->required()->live()
                            ->afterStateUpdated(fn (Get $get, Set $set) => $this->calcularAviso($c, $get, $set)),
                        DatePicker::make('data_aviso')->label('Data do aviso'),
                        TextInput::make('dias_aviso')->label('Dias de aviso (calculado — RN-542)')->numeric()
                            ->helperText('30 dias + 3 por ano completo, até 90, na dispensa sem justa causa. Pode ser ajustado.'),
                        Select::make('pensao_alimenticia')->label('Pensão alimentícia')->options(Opcoes::PENSAO_ALIMENTICIA)->default('nao_ha')->required(),
                        Textarea::make('observacoes_desligamento')->label('Observações')->columnSpanFull(),
                    ]),
                ])
                ->action(fn (array $data, Action $a) => Acao::rodar(fn () => app(IniciarDesligamento::class)->executar($c, $user, array_map(
                    fn ($v) => $v instanceof \BackedEnum ? $v->value : $v, $data)), 'Desligamento iniciado.', $a)
                    && $this->redirect($this->urlVisualizacao())),
        ];
    }

    /** Documentos e linha do tempo já são abas do formulário; não repetir embaixo. */
    public function getRelationManagers(): array
    {
        return [];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return FormularioAdmissao::preencher($this->getRecord());
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return app(SalvarAdmissao::class)->atualizar($record, auth()->user(), $data);
        } catch (RegraDeNegocioException $e) {
            Notification::make()->danger()->title($e->getMessage())->send();
            $this->halt();
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->urlVisualizacao();
    }

    private function urlVisualizacao(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->getRecord(), 'voltar' => $this->voltarPara]);
    }

    private function calcularAviso(Contrato $c, Get $get, Set $set): void
    {
        $motivo = $get('motivo_desligamento');
        $data = $get('data_desligamento');
        $tipo = $get('tipo_aviso');

        if (! $motivo || ! $data) {
            return;
        }

        $tipo = $tipo instanceof TipoAviso ? $tipo : TipoAviso::tryFrom((string) $tipo);
        if ($tipo === TipoAviso::NaoSeAplica) {
            $set('dias_aviso', 0);

            return;
        }

        $motivo = $motivo instanceof MotivoDesligamento ? $motivo : MotivoDesligamento::from($motivo);
        $set('dias_aviso', app(CalculadoraAvisoPrevio::class)->dias($motivo, $c->data_admissao ?? $c->data_admissao_prevista, Carbon::parse($data)));
    }
}
