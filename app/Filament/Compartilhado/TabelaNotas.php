<?php

namespace App\Filament\Compartilhado;

use App\Domain\DocumentoFiscal;
use App\Enums\ClassificacaoNota;
use App\Enums\SituacaoNota;
use App\Models\NotaFiscal;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

/** Tabela de notas fiscais (escritório e portal) — regras.md RN-740 a RN-744. */
final class TabelaNotas
{
    public static function configurar(Table $table, bool $mostrarCompetencia = true, bool $mostrarEmpresa = false): Table
    {
        return $table
            ->columns(array_values(array_filter([
                $mostrarEmpresa ? TextColumn::make('empresa.razao_social')->label('Empresa')->searchable()->toggleable() : null,
                $mostrarCompetencia ? TextColumn::make('competencia')->label('Competência')->date('m/Y')->sortable() : null,
                TextColumn::make('numero')->label('Número')->searchable()->description(fn (NotaFiscal $r) => 'Série '.$r->serie.' · '.($r->modelo === '65' ? 'NFC-e' : 'NF-e')),
                TextColumn::make('data_emissao')->label('Emissão')->date('d/m/Y')->sortable(),
                TextColumn::make('classificacao')->label('Classificação')->badge(),
                TextColumn::make('contraparte')->label('Emitente / destinatário')
                    ->state(fn (NotaFiscal $r) => $r->classificacao === ClassificacaoNota::EntradaTerceiros ? $r->emitente_nome : ($r->destinatario_nome ?? 'Consumidor'))
                    ->description(fn (NotaFiscal $r) => DocumentoFiscal::formatar($r->classificacao === ClassificacaoNota::EntradaTerceiros ? $r->emitente_documento : $r->destinatario_documento)),
                TextColumn::make('valor_total')->label('Valor')->money('BRL')->sortable()
                    ->summarize(Sum::make()->label('Total (sem canceladas)')->money('BRL')
                        ->query(fn ($query) => $query->where('situacao', SituacaoNota::Autorizada->value))),
                TextColumn::make('situacao')->label('Situação')->badge(),
                TextColumn::make('chave_acesso')->label('Chave de acesso')->searchable()->toggleable(isToggledHiddenByDefault: true)->copyable(),
            ])))
            ->groups([
                Group::make('classificacao')->label('Classificação')->getTitleFromRecordUsing(fn (NotaFiscal $r) => $r->classificacao->getLabel()),
                Group::make('competencia')->label('Competência')->date(),
            ])
            ->filters([
                SelectFilter::make('classificacao')->label('Classificação')->options(ClassificacaoNota::class),
                SelectFilter::make('situacao')->label('Situação')->options(SituacaoNota::class),
                SelectFilter::make('modelo')->label('Modelo')->options(['55' => 'NF-e', '65' => 'NFC-e']),
                Filter::make('periodo')->label('Período de emissão')->schema([
                    DatePicker::make('de')->label('Emitidas de'),
                    DatePicker::make('ate')->label('até'),
                ])->query(fn (Builder $query, array $data) => $query
                    ->when($data['de'] ?? null, fn ($q, $d) => $q->whereDate('data_emissao', '>=', $d))
                    ->when($data['ate'] ?? null, fn ($q, $d) => $q->whereDate('data_emissao', '<=', $d))),
                Filter::make('contraparte')->label('Emitente/destinatário')->schema([
                    TextInput::make('termo')->label('Emitente/destinatário (nome ou CNPJ/CPF)'),
                ])->query(fn (Builder $query, array $data) => $query->when($data['termo'] ?? null, function ($q, $termo) {
                    $doc = DocumentoFiscal::somenteDigitos($termo);
                    $q->where(fn ($q) => $q->where('emitente_nome', 'ilike', "%{$termo}%")->orWhere('destinatario_nome', 'ilike', "%{$termo}%")
                        ->when($doc !== '', fn ($q) => $q->orWhere('emitente_documento', $doc)->orWhere('destinatario_documento', $doc)));
                })),
                Filter::make('cfop')->label('CFOP')->schema([TextInput::make('cfop')->label('CFOP')])
                    ->query(fn (Builder $query, array $data) => $query->when($data['cfop'] ?? null, fn ($q, $c) => $q->whereJsonContains('cfops', $c))),
                Filter::make('valor')->label('Faixa de valor')->schema([
                    TextInput::make('min')->label('Valor mínimo')->numeric(),
                    TextInput::make('max')->label('Valor máximo')->numeric(),
                ])->query(fn (Builder $query, array $data) => $query
                    ->when($data['min'] ?? null, fn ($q, $v) => $q->where('valor_total', '>=', $v))
                    ->when($data['max'] ?? null, fn ($q, $v) => $q->where('valor_total', '<=', $v))),
            ])
            ->recordActions([
                // RN-743 — ver a nota (DANFE/DANFCE) montada a partir do XML, em nova aba
                Action::make('danfe')->label('Ver nota')->icon('heroicon-o-eye')->color('primary')
                    ->url(fn (NotaFiscal $record) => route('notas.danfe', $record))->openUrlInNewTab(),
                Action::make('xml')->label('XML')->icon('heroicon-o-arrow-down-tray')->color('gray')
                    ->action(fn (NotaFiscal $record) => Storage::disk(NotaFiscal::DISCO)->download($record->arquivo, $record->chave_acesso.'.xml')),
            ])
            ->toolbarActions([
                // RN-743 — baixar todos os XMLs selecionados em um .zip
                BulkAction::make('zip')->label('Baixar XMLs (.zip)')->icon('heroicon-o-archive-box-arrow-down')
                    ->action(fn (Collection $records) => self::baixarZip($records)),
            ])
            ->defaultSort('data_emissao', 'desc');
    }

    /**
     * Abas "Todas / Entrada / Saída" acima da lista (RN-716): entrada reúne terceiros e emissão própria.
     *
     * @return array<string, Tab>
     */
    public static function abas(): array
    {
        $entradas = [ClassificacaoNota::EntradaTerceiros, ClassificacaoNota::EntradaPropria];

        return [
            'todas' => Tab::make('Todas'),
            'entrada' => Tab::make('Entrada')->icon('heroicon-o-arrow-down-tray')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('classificacao', $entradas)),
            'saida' => Tab::make('Saída')->icon('heroicon-o-arrow-up-tray')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('classificacao', ClassificacaoNota::Saida)),
        ];
    }

    public static function baixarZip(Collection $notas)
    {
        $caminho = storage_path('app/private/tmp/notas-'.now()->format('YmdHis').'.zip');
        @mkdir(dirname($caminho), 0775, true);

        $zip = new ZipArchive;
        $zip->open($caminho, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach ($notas as $nota) {
            $zip->addFromString($nota->chave_acesso.'.xml', (string) Storage::disk(NotaFiscal::DISCO)->get($nota->arquivo));
        }
        $zip->close();

        return response()->download($caminho, 'notas-fiscais.zip')->deleteFileAfterSend();
    }
}
