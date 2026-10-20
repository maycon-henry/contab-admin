<?php

namespace App\Filament\Escritorio\Pages;

use App\Domain\Consultas\RelatorioAdmissoesPorColaborador;
use App\Enums\Permissao;
use App\Models\Contrato;
use App\Models\Empresa;
use App\Models\User;
use BackedEnum;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use UnitEnum;

/** regras.md seção 8.8 — relatório de admissões por colaborador */
class RelatorioAdmissoes extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Relatórios';

    protected static ?string $navigationLabel = 'Admissões por colaborador';

    protected static ?string $title = 'Relatório de admissões por colaborador';

    protected static ?string $slug = 'relatorios/admissoes';

    protected string $view = 'filament.escritorio.relatorio-admissoes';

    public ?array $filtros = [];

    public ?int $detalheUserId = null;

    public static function canAccess(): bool
    {
        return auth()->user()->can(Permissao::RelatoriosAdmissoes->value);
    }

    public function mount(): void
    {
        // RN-554 — período padrão: mês atual
        $this->form->fill(['de' => today()->startOfMonth()->toDateString(), 'ate' => today()->toDateString()]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('filtros')->components([
            Grid::make(4)->schema([
                DatePicker::make('de')->label('Admitidos de')->required()->live(),
                DatePicker::make('ate')->label('até')->required()->live(),
                Select::make('empresa_id')->label('Empresa')->options(Empresa::orderBy('razao_social')->pluck('razao_social', 'id'))->searchable()->live(),
                Select::make('user_id')->label('Colaborador')->options(User::where('escritorio_id', auth()->user()->escritorio_id)->daEquipe()->pluck('name', 'id'))->live(),
            ]),
        ]);
    }

    public function linhas(): Collection
    {
        $f = $this->filtros;

        return app(RelatorioAdmissoesPorColaborador::class)->gerar(
            auth()->user()->escritorio_id,
            Carbon::parse($f['de'] ?? today()->startOfMonth()),
            Carbon::parse($f['ate'] ?? today()),
            $f['empresa_id'] ?? null,
            $f['user_id'] ?? null,
        );
    }

    /** RN-555 — admissões do colaborador no período */
    public function admissoesDoColaborador(): Collection
    {
        if (! $this->detalheUserId) {
            return collect();
        }

        $f = $this->filtros;

        return Contrato::with(['funcionario', 'empresa', 'atendimentoIniciadoPor'])
            ->where('admitido_por_id', $this->detalheUserId)
            ->whereBetween('admitido_em', [Carbon::parse($f['de'])->startOfDay(), Carbon::parse($f['ate'])->endOfDay()])
            ->when($f['empresa_id'] ?? null, fn ($q, $e) => $q->where('empresa_id', $e))
            ->orderByDesc('admitido_em')->get();
    }

    public function detalhar(int $userId): void
    {
        $this->detalheUserId = $this->detalheUserId === $userId ? null : $userId;
    }
}
