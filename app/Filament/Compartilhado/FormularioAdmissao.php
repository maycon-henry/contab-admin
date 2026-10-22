<?php

namespace App\Filament\Compartilhado;

use App\Actions\Admissao\PreencherComDocumento;
use App\Domain\Admissao\PreenchimentoAutomatico;
use App\Domain\Admissao\ResumoAdmissao;
use App\Domain\Admissao\VerificadorAdmissao;
use App\Domain\DocumentoFiscal;
use App\Domain\Opcoes;
use App\Enums\DocumentoAdmissao;
use App\Enums\Permissao;
use App\Enums\SituacaoContrato;
use App\Enums\TipoContrato;
use App\Exceptions\RegraDeNegocioException;
use App\Models\Contrato;
use App\Models\Empresa;
use App\Models\Estabelecimento;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Illuminate\Support\Arr;
use Illuminate\Support\HtmlString;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Formulário de admissão usado no portal (empresa) e no painel do escritório — regras.md RN-582.
 * Os dados técnicos do eSocial (RN-597) só aparecem para o escritório. As páginas de criação
 * usam o trait FormularioDeAdmissao (aviso do preenchimento automático).
 */
final class FormularioAdmissao
{
    /**
     * @param  callable(): ?int  $empresaId  empresa da admissão (para listar os estabelecimentos)
     * @param  Action|null  $acaoEnviar  botão de envio ao escritório; informado, mostra a aba "Resumo e envio" (RN-586)
     */
    public static function componentes(bool $escritorio, callable $empresaId, ?Action $acaoEnviar = null): array
    {
        return [
            Callout::make('Pendências para solicitar a admissão')
                ->warning()
                ->description(fn (?Contrato $record) => self::listaPendencias($record))
                ->visible(fn (?Contrato $record) => $record && $record->situacao->editavelPelaEmpresa() && app(VerificadorAdmissao::class)->pendenciasDaEmpresa($record) !== []),

            Callout::make('Admissão devolvida pelo escritório')
                ->danger()
                ->description(fn (?Contrato $record) => $record?->motivo_devolucao)
                ->visible(fn (?Contrato $record) => filled($record?->motivo_devolucao)),

            Callout::make('Funcionário admitido')
                ->info()
                ->description('Aqui você atualiza o cadastro pessoal, dependentes e dados bancários. Contrato, documentos e dados do eSocial ficam só para consulta: mudanças de cargo, salário e jornada são feitas por histórico com vigência (RN-525).')
                ->visible(fn (?Contrato $record) => self::somenteCadastro($record)),

            // abas trocadas no navegador: todas ficam na página, e os anexos em andamento não se perdem.
            // O Resumo pede ao servidor a versão atual do formulário quando aparece (resumo-admissao.blade.php)
            Tabs::make('admissao')->columnSpanFull()->persistTabInQueryString('aba')->tabs([
                Tab::make('Dados pessoais')->key('dados-pessoais')->icon('heroicon-o-user')->schema([
                    Grid::make(3)->schema([
                        TextInput::make('funcionario.nome')->label('Nome completo')->required()->columnSpan(2),
                        TextInput::make('funcionario.cpf')->label('CPF')->required()->mask('999.999.999-99')
                            ->disabled(fn (?Contrato $record) => $record !== null)
                            ->dehydrateStateUsing(fn ($state) => DocumentoFiscal::somenteDigitos($state))
                            ->rule(fn () => function ($attribute, $value, $fail) {
                                if (! DocumentoFiscal::cpfValido($value)) {
                                    $fail('CPF inválido.');
                                }
                            }),
                        TextInput::make('funcionario.nome_social')->label('Nome social'),
                        DatePicker::make('funcionario.data_nascimento')->label('Data de nascimento'),
                        Select::make('funcionario.sexo')->label('Sexo')->options(Opcoes::SEXO),
                        Select::make('funcionario.raca_cor')->label('Raça/cor')->options(Opcoes::RACA_COR),
                        Select::make('funcionario.estado_civil')->label('Estado civil')->options(Opcoes::ESTADO_CIVIL),
                        Select::make('funcionario.grau_instrucao')->label('Grau de instrução')->options(Opcoes::GRAU_INSTRUCAO),
                        TextInput::make('funcionario.pais_nascimento')->label('País de nascimento')->default('Brasil'),
                        TextInput::make('funcionario.nacionalidade')->label('Nacionalidade')->default('Brasileira'),
                        TextInput::make('funcionario.rg')->label('RG / CNH'),
                        TextInput::make('funcionario.pis')->label('PIS/NIS'),
                        TextInput::make('funcionario.telefone')->label('Telefone')->tel(),
                        TextInput::make('funcionario.email')->label('E-mail')->email(),
                    ]),
                    Section::make('Endereço')->compact()->columns(4)->schema([
                        TextInput::make('funcionario.cep')->label('CEP')->mask('99999-999')
                            ->dehydrateStateUsing(fn ($state) => DocumentoFiscal::somenteDigitos($state)),
                        TextInput::make('funcionario.logradouro')->label('Logradouro')->columnSpan(2),
                        TextInput::make('funcionario.numero')->label('Número'),
                        TextInput::make('funcionario.complemento')->label('Complemento'),
                        TextInput::make('funcionario.bairro')->label('Bairro'),
                        TextInput::make('funcionario.municipio')->label('Município'),
                        Select::make('funcionario.uf')->label('UF')->options(Opcoes::ufs()),
                    ]),
                    Section::make('Situações especiais')->compact()->columns(2)->collapsed()->schema([
                        Toggle::make('funcionario.pessoa_com_deficiencia')->label('Pessoa com deficiência')->live(),
                        TextInput::make('funcionario.tipo_deficiencia')->label('Tipo(s) de deficiência')
                            ->visible(fn (Get $get) => (bool) $get('funcionario.pessoa_com_deficiencia')),
                        Toggle::make('funcionario.estrangeiro')->label('Trabalhador estrangeiro')->live(),
                        TextInput::make('funcionario.condicao_ingresso')->label('Condição de ingresso no país')
                            ->visible(fn (Get $get) => (bool) $get('funcionario.estrangeiro')),
                    ]),
                ]),

                Tab::make('Dependentes')->key('dependentes')->icon('heroicon-o-users')->schema([
                    Repeater::make('funcionario.dependentes')->label('Dependentes')->defaultItems(0)->addActionLabel('Adicionar dependente')
                        ->columns(3)->schema([
                            TextInput::make('nome')->label('Nome')->required()->columnSpan(2),
                            DatePicker::make('data_nascimento')->label('Nascimento')->required(),
                            Select::make('tipo')->label('Parentesco')->options(Opcoes::TIPO_DEPENDENTE)->required(),
                            TextInput::make('cpf')->label('CPF')->mask('999.999.999-99')
                                ->dehydrateStateUsing(fn ($state) => DocumentoFiscal::somenteDigitos($state) ?: null)
                                ->required(fn (Get $get) => (bool) $get('dependente_irrf'))
                                ->helperText('Obrigatório se for dependente para IRRF (RN-512).'),
                            Grid::make(1)->schema([
                                Checkbox::make('dependente_irrf')->label('Dependente para IRRF')->live(),
                                Checkbox::make('dependente_salario_familia')->label('Dependente para salário-família'),
                            ]),
                        ]),
                ]),

                Tab::make('Dados bancários')->key('dados-bancarios')->icon('heroicon-o-banknotes')->columns(4)
                    ->visible(fn () => self::podeVerSensiveis($escritorio))->schema([
                        TextInput::make('funcionario.banco')->label('Banco'),
                        TextInput::make('funcionario.agencia')->label('Agência'),
                        TextInput::make('funcionario.conta')->label('Conta'),
                        TextInput::make('funcionario.chave_pix')->label('ou chave Pix'),
                    ]),

                Tab::make('Contrato')->key('contrato')->icon('heroicon-o-briefcase')->columns(3)
                    ->disabled(fn (?Contrato $record) => self::somenteCadastro($record))->schema([
                        DatePicker::make('data_admissao_prevista')->label('Data de admissão prevista')->required(),
                        TextInput::make('cargo')->label('Cargo'),
                        Select::make('estabelecimento_id')->label('Local de trabalho')
                            ->options(fn () => Estabelecimento::where('empresa_id', $empresaId())->get()->pluck('rotulo', 'id')),
                        TextInput::make('salario')->label('Salário')->numeric()->prefix('R$')
                            ->visible(fn () => self::podeVerSensiveis($escritorio)),
                        Select::make('unidade_salario')->label('Unidade do salário')->options(Opcoes::UNIDADE_SALARIO)->default('mes'),
                        TextInput::make('jornada_semanal')->label('Jornada semanal (horas)')->numeric()->default(44),
                        Select::make('tipo_contrato')->label('Tipo de contrato')->options(TipoContrato::class)->live(),
                        TextInput::make('experiencia_dias_1')->label('Experiência — 1º período (dias)')->numeric()
                            ->visible(fn (Get $get) => $get('tipo_contrato') === TipoContrato::Experiencia || $get('tipo_contrato') === TipoContrato::Experiencia->value),
                        TextInput::make('experiencia_dias_2')->label('Prorrogação (dias)')->numeric()
                            ->helperText('Total máximo de 90 dias (RN-522).')
                            ->visible(fn (Get $get) => $get('tipo_contrato') === TipoContrato::Experiencia || $get('tipo_contrato') === TipoContrato::Experiencia->value),
                        DatePicker::make('data_termino')->label('Data de término')
                            ->visible(fn (Get $get) => $get('tipo_contrato') === TipoContrato::Determinado || $get('tipo_contrato') === TipoContrato::Determinado->value),
                        TextInput::make('horario')->label('Horário de trabalho')->placeholder('Seg a sex, 08h às 17h')->columnSpan(2),
                    ]),

                Tab::make('Documentos')->key('documentos')->icon('heroicon-o-paper-clip')->columns(2)
                    ->disabled(fn (?Contrato $record) => self::somenteCadastro($record))->schema([
                        Callout::make('Preenchimento automático')
                            ->info()->icon('heroicon-o-sparkles')->columnSpanFull()
                            ->description('Ao anexar o documento com foto, o comprovante de residência e os demais documentos, os dados encontrados preenchem os campos que ainda estão vazios. Campos já preenchidos não são alterados. Confira tudo no Resumo antes de enviar.')
                            ->visible(fn () => app(PreencherComDocumento::class)->disponivel()),
                        ...self::camposDocumentos($empresaId),
                    ]),

                Tab::make('Dados do eSocial (escritório)')->key('esocial')->icon('heroicon-o-building-office')->visible($escritorio)->columns(3)
                    ->disabled(fn (?Contrato $record) => self::somenteCadastro($record))->schema([
                        TextInput::make('matricula')->label('Matrícula'),
                        DatePicker::make('data_admissao')->label('Data de admissão efetiva'),
                        Select::make('categoria')->label('Categoria do trabalhador')->options(Opcoes::CATEGORIA),
                        TextInput::make('cbo')->label('CBO')->length(6),
                        Select::make('natureza_atividade')->label('Natureza da atividade')->options(Opcoes::NATUREZA_ATIVIDADE),
                        Select::make('regime_jornada')->label('Regime de jornada')->options(Opcoes::REGIME_JORNADA),
                        TextInput::make('sindicato_cnpj')->label('CNPJ do sindicato')->mask('99.999.999/9999-99')
                            ->dehydrateStateUsing(fn ($state) => DocumentoFiscal::somenteDigitos($state) ?: null),
                        TextInput::make('recibo_esocial')->label('Recibo do eSocial (S-2200)'),
                    ]),

                // RN-575 — histórico de cada etapa, com quem fez e quando
                Tab::make('Linha do tempo')->key('linha-do-tempo')->icon('heroicon-o-clock')
                    ->visible(fn (?Contrato $record) => $escritorio && $record !== null)
                    ->schema([View::make('filament.compartilhado.linha-do-tempo')]),

                // RN-586 — última aba: confere tudo e envia ao escritório pelo botão no final
                Tab::make('Resumo e envio')->key('resumo')->icon('heroicon-o-clipboard-document-check')
                    ->visible($acaoEnviar !== null)
                    ->schema([
                        View::make('filament.compartilhado.resumo-admissao')->viewData(fn (Get $get, ?Contrato $record) => [
                            'resumo' => self::resumo($get, $empresaId()),
                            'salvo' => $record?->formulario_salvo_em
                                ? 'Última versão salva por '.($record->formularioSalvoPor?->name ?? '—').' em '.$record->formulario_salvo_em->format('d/m/Y H:i').'.'
                                : null,
                        ]),
                        Actions::make(array_filter([$acaoEnviar]))->key('envio')->alignEnd(),
                    ]),
            ]),
        ];
    }

    /**
     * Resumo e pendências a partir do formulário como está na tela (RN-583, RN-586).
     *
     * @return array{secoes: array<string, list<array{rotulo: string, valor: ?string, falta: bool}>>, documentos: list<array{rotulo: string, anexado: bool, obrigatorio: bool}>, pendencias: list<string>}
     */
    public static function resumo(Get $get, ?int $empresaId): array
    {
        $dados = collect(['funcionario', 'documentos', 'data_admissao_prevista', 'cargo', 'estabelecimento_id', 'salario', 'tipo_contrato',
            'jornada_semanal', 'horario', 'experiencia_dias_1', 'data_termino'])->mapWithKeys(fn (string $campo) => [$campo => $get($campo)])->all();

        return app(ResumoAdmissao::class)->montar($dados, Estabelecimento::where('empresa_id', $empresaId)->get()->pluck('rotulo', 'id')->all());
    }

    /**
     * RN-579 — ao anexar um documento, a IA lê os dados pessoais e preenche só os campos vazios.
     *
     * @param  callable(): ?int  $empresaId
     */
    private static function preencherComDocumento(DocumentoAdmissao $tipo, mixed $arquivos, Get $get, Set $set, callable $empresaId): void
    {
        $arquivo = collect(Arr::wrap($arquivos))->first(fn ($a) => $a instanceof TemporaryUploadedFile);
        $acao = app(PreencherComDocumento::class);

        if (! $arquivo || ! $acao->disponivel() || app(PreenchimentoAutomatico::class)->camposDoDocumento($tipo) === []) {
            return;
        }

        try {
            $campos = $acao->executar(auth()->user(), Empresa::find($empresaId()), $tipo, (string) $arquivo->get(), (string) $arquivo->getMimeType(), (array) $get('funcionario'));
        } catch (RegraDeNegocioException $e) {
            Notification::make()->warning()->title($e->getMessage())->send();

            return;
        }

        foreach ($campos as $campo => $valor) {
            $set("funcionario.{$campo}", match ($campo) {
                'cpf' => DocumentoFiscal::formatarCpf($valor),
                'cep' => substr($valor, 0, 5).'-'.substr($valor, 5),
                default => $valor,
            });
        }

        $campos === []
            ? Notification::make()->info()->title('Documento lido')->body('Nenhum campo vazio a preencher com os dados deste documento.')->send()
            : Notification::make()->success()->title('Preenchemos '.count($campos).' '.(count($campos) === 1 ? 'campo' : 'campos').' com os dados do documento')
                ->body(new HtmlString(collect($campos)->keys()->map(fn ($c) => '• '.e(PreenchimentoAutomatico::ROTULOS[$c]))->implode('<br>')
                    .'<br><br>Confira os dados antes de enviar.'))
                ->persistent()->send();
    }

    /** Funcionário já admitido: só o cadastro pessoal é editável. */
    private static function somenteCadastro(?Contrato $record): bool
    {
        return $record?->situacao === SituacaoContrato::Admitido;
    }

    /** RN-570 / RN-572 — a empresa vê tudo; a equipe do escritório só com permissão. */
    private static function podeVerSensiveis(bool $escritorio): bool
    {
        return ! $escritorio || auth()->user()->can(Permissao::FuncionariosDadosSensiveis->value);
    }

    /**
     * @param  (callable(): ?int)|null  $empresaId  informado no formulário da admissão, liga o preenchimento automático (RN-579)
     */
    public static function camposDocumentos(?callable $empresaId = null): array
    {
        return collect(DocumentoAdmissao::cases())->map(fn (DocumentoAdmissao $tipo) => FileUpload::make("documentos.{$tipo->value}")
            ->label($tipo->getLabel().($tipo->obrigatorio() ? ' *' : ''))
            ->disk('local')
            ->directory(fn () => 'escritorios/'.auth()->user()->escritorio_id.'/admissoes')
            ->visibility('private')
            ->storeFileNamesIn("documentos_nomes.{$tipo->value}")
            ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
            ->maxSize(10240)
            ->downloadable()
            ->openable()
            ->helperText(fn (?Contrato $record) => self::avaliacaoDoDocumento($record, $tipo))
            ->afterStateUpdated(function ($state, Get $get, Set $set) use ($tipo, $empresaId): void {
                if ($empresaId) {
                    self::preencherComDocumento($tipo, $state, $get, $set, $empresaId);
                }
            })
        )->all();
    }

    private static function avaliacaoDoDocumento(?Contrato $record, DocumentoAdmissao $tipo): ?HtmlString
    {
        $doc = $record?->documentoDoChecklist($tipo);

        return match ($doc?->avaliacao) {
            'aceito' => new HtmlString('<span style="color:#15803d">✔ Aceito pelo escritório</span>'),
            'recusado' => new HtmlString('<span style="color:#b91c1c">✖ Recusado: '.e($doc->motivo_recusa).'</span>'),
            // RN-567 — documento trocado de funcionário admitido aguarda o escritório
            default => $doc && $record->situacao === SituacaoContrato::Admitido
                ? new HtmlString('<span style="color:#2563eb">⏳ Em análise pelo escritório</span>')
                : null,
        };
    }

    private static function listaPendencias(?Contrato $record): ?HtmlString
    {
        if (! $record) {
            return null;
        }

        $pendencias = app(VerificadorAdmissao::class)->pendenciasDaEmpresa($record);

        return new HtmlString(collect($pendencias)->map(fn ($p) => '• '.e($p))->implode('<br>'));
    }

    /** Converte o contrato no estado do formulário (edição). */
    public static function preencher(Contrato $contrato): array
    {
        $contrato->load(['funcionario.dependentes', 'documentos']);
        $funcionario = $contrato->funcionario;

        $dadosFuncionario = $funcionario->attributesToArray();
        $dadosFuncionario['data_nascimento'] = $funcionario->data_nascimento?->toDateString();
        foreach (['banco', 'agencia', 'conta', 'chave_pix'] as $campo) {
            $dadosFuncionario[$campo] = $funcionario->{$campo};
        }
        $dadosFuncionario['dependentes'] = $funcionario->dependentes->map(fn ($d) => [
            'nome' => $d->nome, 'cpf' => $d->cpf, 'data_nascimento' => $d->data_nascimento?->toDateString(), 'tipo' => $d->tipo,
            'dependente_irrf' => $d->dependente_irrf, 'dependente_salario_familia' => $d->dependente_salario_familia,
        ])->all();

        $documentos = [];
        $nomes = [];
        foreach (DocumentoAdmissao::cases() as $tipo) {
            $doc = $contrato->documentoDoChecklist($tipo);
            $documentos[$tipo->value] = $doc?->arquivo;
            $nomes[$tipo->value] = $doc?->nome_original;
        }

        return array_merge($contrato->attributesToArray(), [
            'funcionario' => $dadosFuncionario,
            'documentos' => $documentos,
            'documentos_nomes' => $nomes,
            'data_admissao_prevista' => $contrato->data_admissao_prevista?->toDateString(),
            'data_admissao' => $contrato->data_admissao?->toDateString(),
            'data_termino' => $contrato->data_termino?->toDateString(),
            'tipo_contrato' => $contrato->tipo_contrato?->value,
        ]);
    }

    /** Empresa corrente do formulário: tenant no portal; registro/escolha no escritório. */
    public static function empresaDoTenant(): ?int
    {
        return Filament::getTenant()?->getKey();
    }
}
