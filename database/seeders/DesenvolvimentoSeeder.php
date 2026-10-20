<?php

namespace Database\Seeders;

use App\Actions\Admissao\AssumirAdmissao;
use App\Actions\Admissao\AvaliarDocumentoAdmissao;
use App\Actions\Admissao\ConcluirAdmissao;
use App\Actions\Admissao\DevolverAdmissao;
use App\Actions\Admissao\SalvarAdmissao;
use App\Actions\Admissao\SolicitarAdmissao;
use App\Actions\Cobranca\EnviarCobrancaDocumentacao;
use App\Actions\Competencias\FecharCompetencia;
use App\Actions\Competencias\ObterCompetencia;
use App\Actions\Desligamento\ConcluirDesligamento;
use App\Actions\Desligamento\IniciarDesligamento;
use App\Actions\Despesas\SalvarDespesa;
use App\Actions\Itens\AceitarItem;
use App\Actions\Itens\ConcluirTarefa;
use App\Actions\Itens\EnviarDocumentosDoItem;
use App\Actions\Itens\InformarSemMovimento;
use App\Actions\Itens\RecusarItem;
use App\Actions\NotasFiscais\ImportarLoteXml;
use App\Actions\Usuarios\DefinirPerfis;
use App\Domain\Competencia as Mes;
use App\Domain\DocumentoFiscal;
use App\Enums\AplicacaoTipoDocumento;
use App\Enums\DocumentoAdmissao;
use App\Enums\MotivoRecusa;
use App\Enums\Perfil;
use App\Enums\RegimeTributario;
use App\Enums\ResultadoCertidao;
use App\Enums\SituacaoEmpresa;
use App\Enums\SituacaoEscritorio;
use App\Enums\SituacaoItem;
use App\Enums\TipoContrato;
use App\Enums\TipoItem;
use App\Enums\TipoUsuario;
use App\Models\Acesso;
use App\Models\Competencia;
use App\Models\Contrato;
use App\Models\Empresa;
use App\Models\Escritorio;
use App\Models\ItemCompetencia;
use App\Models\TipoDocumento;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\Suporte\GeradorXmlNfe;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Dados de exemplo para navegar no sistema — AGENTS.md seção 5.0.
 * SOMENTE local/testing. Senha de todos os usuários: 123 (descumpre RN-310 de propósito).
 * Usa as próprias Actions do sistema, para que históricos e situações fiquem como no uso real.
 */
class DesenvolvimentoSeeder extends Seeder
{
    private const SENHA = '123';

    private Escritorio $escritorio;

    private User $admin;

    private User $bruno;

    private User $daniela;

    /** @var array<string, Empresa> */
    private array $empresas = [];

    /** @var array<string, User> */
    private array $usuariosEmpresa = [];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('DesenvolvimentoSeeder só pode rodar nos ambientes local e testing (AGENTS.md seção 5.0).');
        }

        Notification::fake(); // não dispara e-mails durante a carga

        $this->call(PermissoesSeeder::class);

        $this->criarEscritorio();
        $this->criarEquipe();
        $this->criarCatalogo();
        $this->criarEmpresas();
        $this->criarUsuariosDoPortal();
        $this->criarCompetencias();
        $this->importarNotasFiscais();
        $this->criarAdmissoes();
        $this->criarHistoricoDeCobrancas();
        $this->criarAcessos();
        $this->criarDespesas();
        $this->criarSegundoEscritorio();

        $this->command?->info('Dados de exemplo criados. Logins (senha 123): admin@contab.test, colaborador@contab.test, empresa@contab.test');
    }

    // ------------------------------------------------------------------ escritório e equipe

    private function criarEscritorio(): void
    {
        $this->escritorio = Escritorio::create([
            'razao_social' => 'Escritório Demo Contabilidade Ltda',
            'cnpj' => '11222333000181',
            'crc' => 'SP-123456/O-7',
            'situacao' => SituacaoEscritorio::Ativo,
            'cobranca_automatica_ativa' => true,
            'dia_corte_cobranca' => 5,
        ]);

        app(DefinirPerfis::class)->garantirNoEscritorio($this->escritorio->id);
    }

    private function criarEquipe(): void
    {
        $this->admin = $this->usuario('Maria Administradora', 'admin@contab.test', TipoUsuario::Administrador, titular: true);
        $this->bruno = $this->usuario('Bruno Colaborador', 'colaborador@contab.test', TipoUsuario::Colaborador);
        $this->daniela = $this->usuario('Daniela Souza', 'daniela@contab.test', TipoUsuario::Colaborador, perfis: [Perfil::Operacional, Perfil::Financeiro]);
        $this->usuario('Fernanda Lima', 'fernanda@contab.test', TipoUsuario::Administrador);
        // convite ainda não aceito — para ver "Reenviar convite"
        $this->usuario('Eduardo Ramos', 'eduardo@contab.test', TipoUsuario::Colaborador, conviteAceito: false, perfis: [Perfil::Financeiro]);
    }

    private function usuario(string $nome, string $email, TipoUsuario $tipo, bool $titular = false, bool $conviteAceito = true, ?Escritorio $escritorio = null, array $perfis = [Perfil::Operacional]): User
    {
        $escritorio ??= $this->escritorio;

        $user = User::create([
            'escritorio_id' => $escritorio->id,
            'name' => $nome,
            'email' => $email,
            'tipo' => $tipo,
            'titular' => $titular,
            'ativo' => true,
        ]);

        if ($conviteAceito) {
            // grava o hash direto, sem passar pela política de senha (AGENTS.md seção 5.0)
            $user->forceFill(['password' => Hash::make(self::SENHA), 'convite_aceito_em' => now(), 'email_verified_at' => now()])->save();
        } else {
            $user->forceFill(['convite_token' => hash('sha256', Str::random(64)), 'convite_expira_em' => now()->addDays(7)])->save();
        }

        if ($tipo === TipoUsuario::Colaborador) {
            app(DefinirPerfis::class)->executar($user, $perfis);
        }

        return $user;
    }

    // ------------------------------------------------------------------ catálogo (RN-613 — sugestão D13)

    private function criarCatalogo(?Escritorio $escritorio = null): void
    {
        $escritorio ??= $this->escritorio;
        $base = ['escritorio_id' => $escritorio->id, 'ativo' => true, 'tipo' => TipoItem::Documento, 'aplicacao' => AplicacaoTipoDocumento::Todas];

        $itens = [
            ['nome' => 'Notas fiscais de saída', 'chave_sistema' => TipoDocumento::CHAVE_NOTAS_SAIDA, 'prazo_dia' => 5, 'formatos' => ['xml'], 'multiplos_arquivos' => true,
                'instrucoes' => 'Envie os XMLs pela área "Notas fiscais". O sistema identifica o mês automaticamente.'],
            ['nome' => 'Notas fiscais de entrada', 'chave_sistema' => TipoDocumento::CHAVE_NOTAS_ENTRADA, 'prazo_dia' => 5, 'formatos' => ['xml'], 'multiplos_arquivos' => true,
                'instrucoes' => 'XMLs das notas de compra recebidas no mês, pela área "Notas fiscais".'],
            ['nome' => 'Extrato bancário', 'prazo_dia' => 10, 'formatos' => ['pdf', 'planilha'], 'multiplos_arquivos' => true,
                'instrucoes' => 'Extrato completo do mês de todas as contas da empresa (PDF ou planilha exportada do banco).'],
            ['nome' => 'Comprovantes de despesas', 'prazo_dia' => 10, 'formatos' => ['pdf', 'imagem'], 'multiplos_arquivos' => true, 'obrigatorio' => false,
                'instrucoes' => 'Boletos pagos, recibos e comprovantes de despesas do mês.'],
            ['nome' => 'CND Federal — Receita/PGFN', 'prazo_dia' => 15, 'formatos' => ['pdf'], 'exige_validade' => true, 'eh_certidao' => true, 'aproveitar_valido' => true,
                'instrucoes' => 'Emita em servicos.receitafazenda.gov.br (Certidão de Débitos Relativos a Créditos Tributários Federais e à Dívida Ativa da União).'],
            ['nome' => 'CRF — Regularidade do FGTS', 'prazo_dia' => 15, 'formatos' => ['pdf'], 'exige_validade' => true, 'eh_certidao' => true,
                'instrucoes' => 'Emita em consulta-crf.caixa.gov.br. Validade de 30 dias.'],
            ['nome' => 'CNDT — Débitos Trabalhistas', 'prazo_dia' => 15, 'formatos' => ['pdf'], 'exige_validade' => true, 'eh_certidao' => true, 'aproveitar_valido' => true,
                'instrucoes' => 'Emita em cndt-certidao.tst.jus.br.'],
            ['nome' => 'CND Estadual', 'prazo_dia' => 15, 'formatos' => ['pdf'], 'exige_validade' => true, 'eh_certidao' => true,
                'aplicacao' => AplicacaoTipoDocumento::PorRegime, 'regimes' => [RegimeTributario::LucroPresumido->value, RegimeTributario::LucroReal->value],
                'instrucoes' => 'Certidão negativa de débitos estaduais (Sefaz do estado da empresa).'],
            ['nome' => 'CND Municipal', 'prazo_dia' => 15, 'formatos' => ['pdf'], 'exige_validade' => true, 'eh_certidao' => true,
                'aplicacao' => AplicacaoTipoDocumento::Selecionadas, 'instrucoes' => 'Certidão negativa de tributos mobiliários da prefeitura.'],
            ['nome' => 'Conciliação bancária', 'tipo' => TipoItem::Tarefa, 'prazo_dia' => 20],
        ];

        foreach ($itens as $item) {
            TipoDocumento::create(array_merge($base, ['obrigatorio' => true], $item));
        }
    }

    // ------------------------------------------------------------------ empresas (seção 7)

    private function criarEmpresas(): void
    {
        $dados = [
            'demo' => ['22333444', 'Empresa Demo Ltda', 'Empresa Demo', RegimeTributario::SimplesNacional, '01', '2024-01-01', 'Comércio de produtos em geral', '4712100'],
            'padaria' => ['33444555', 'Padaria Pão Quente Ltda', 'Pão Quente', RegimeTributario::SimplesNacional, '01', '2023-06-01', 'Padaria e confeitaria', '1091102'],
            'construtora' => ['44555666', 'Construtora Alicerce S.A.', 'Alicerce', RegimeTributario::LucroReal, '99', '2022-03-01', 'Construção de edifícios', '4120400'],
            'clinica' => ['55666777', 'Clínica Bem Estar Ltda', 'Bem Estar', RegimeTributario::LucroPresumido, '99', '2025-02-01', 'Atividade médica ambulatorial', '8630503'],
            'tech' => ['66777888', 'Tech Soluções em Software Ltda', 'Tech Soluções', RegimeTributario::SimplesNacional, '01', '2026-07-01', 'Desenvolvimento de software', '6201501'],
        ];

        $socios = [
            'demo' => [['Ricardo Demo', 60, 'Sócio-administrador'], ['Juliana Demo', 40, 'Sócia']],
            'padaria' => [['Antônio Padeiro', 100, 'Sócio-administrador']],
            'construtora' => [['Marcos Engenheiro', 50, 'Diretor'], ['Holding Alicerce Participações', 50, 'Sócia PJ']],
            'clinica' => [['Dra. Helena Saúde', 70, 'Sócia-administradora'], ['Dr. Paulo Saúde', 30, 'Sócio']],
            'tech' => [['Tiago Dev', 100, 'Sócio-administrador']],
        ];

        foreach ($dados as $chave => [$base8, $razao, $fantasia, $regime, $classificacao, $inicio, $natureza, $cnae]) {
            $cnpj = $this->cnpj($base8.'0001');

            $empresa = Empresa::create([
                'escritorio_id' => $this->escritorio->id,
                'cnpj' => $cnpj,
                'razao_social' => $razao,
                'nome_fantasia' => $fantasia,
                'natureza_juridica' => $regime === RegimeTributario::LucroReal ? '205-4 — Sociedade Anônima Fechada' : '206-2 — Sociedade Empresária Limitada',
                'regime_tributario' => $regime,
                'data_inicio_atendimento' => $inicio,
                'situacao' => SituacaoEmpresa::Ativa,
                'data_abertura' => CarbonImmutable::parse($inicio)->subYears(2),
                'porte' => $regime === RegimeTributario::SimplesNacional ? 'me' : 'demais',
                'cnae_principal' => $cnae,
                'capital_social' => 50000,
                'inscricao_estadual' => $chave === 'clinica' ? 'ISENTO' : '1234567'.substr($base8, -4),
                'inscricao_municipal' => '9876'.substr($base8, -3),
                'cep' => '01310100', 'logradouro' => 'Avenida Paulista', 'numero' => (string) (1000 + strlen($razao)), 'bairro' => 'Bela Vista',
                'municipio' => 'São Paulo', 'uf' => 'SP', 'telefone' => '(11) 3000-'.substr($base8, -4), 'email' => "contato@{$chave}.test",
                'contato_nome' => $socios[$chave][0][0], 'contato_cpf' => $this->cpf(), 'contato_email' => "financeiro@{$chave}.test",
                'classificacao_tributaria' => $classificacao,
            ]);

            $empresa->estabelecimentos()->create([
                'cnpj' => $cnpj, 'matriz' => true, 'nome' => 'Matriz', 'municipio' => 'São Paulo', 'uf' => 'SP',
                'cnae_preponderante' => $cnae, 'aliquota_rat' => $chave === 'construtora' ? 3 : 1, 'fap' => $regime === RegimeTributario::SimplesNacional ? 1 : 0.85,
                'grau_risco' => $chave === 'construtora' ? 4 : 1,
            ]);

            foreach ($socios[$chave] as [$nome, $percentual, $qualificacao]) {
                $empresa->socios()->create([
                    'nome' => $nome, 'documento' => str_contains($nome, 'Holding') ? $this->cnpj('77888999'.'0001') : $this->cpf(),
                    'qualificacao' => $qualificacao, 'percentual' => $percentual, 'data_entrada' => $empresa->data_abertura,
                ]);
            }

            $this->empresas[$chave] = $empresa;
        }

        // filial (RN-411) e CND Municipal só para a clínica (aplicação "empresas selecionadas")
        $this->empresas['padaria']->estabelecimentos()->create([
            'cnpj' => $this->cnpj('33444555'.'0002'), 'matriz' => false, 'nome' => 'Filial Vila Mariana', 'municipio' => 'São Paulo', 'uf' => 'SP',
            'cnae_preponderante' => '1091102', 'aliquota_rat' => 1, 'fap' => 1, 'grau_risco' => 2,
        ]);
        TipoDocumento::where('nome', 'CND Municipal')->first()->empresasSelecionadas()->sync([$this->empresas['clinica']->id]);

        // RN-616 — ajuste: Tech Soluções dispensada do extrato (conta digital integrada)
        $this->empresas['tech']->ajustesRotina()->create([
            'tipo_documento_id' => TipoDocumento::where('nome', 'Comprovantes de despesas')->value('id'),
            'dispensado' => true, 'justificativa' => 'Despesas integradas pelo app do banco',
        ]);
    }

    private function criarUsuariosDoPortal(): void
    {
        $definicoes = [
            // usuária ligada a duas empresas — para ver a troca de empresa no portal (RN-241)
            'empresa@contab.test' => ['Carla Gerente', ['demo', 'padaria'], true],
            'antonio@padaria.test' => ['Antônio Padeiro', ['padaria'], true],
            'marcos@alicerce.test' => ['Marcos Engenheiro', ['construtora'], true],
            'helena@bemestar.test' => ['Helena Saúde', ['clinica'], true],
            // convite pendente: a Tech Soluções fica sem destinatário de cobrança (RN-1318)
            'tiago@tech.test' => ['Tiago Dev', ['tech'], false],
        ];

        foreach ($definicoes as $email => [$nome, $empresas, $aceito]) {
            $user = $this->usuario($nome, $email, TipoUsuario::UsuarioEmpresa, conviteAceito: $aceito);
            $user->empresas()->sync(collect($empresas)->map(fn ($e) => $this->empresas[$e]->id)->all());
            $this->usuariosEmpresa[$email] = $user;
        }
    }

    // ------------------------------------------------------------------ competências (seção 9)

    private function criarCompetencias(): void
    {
        $atual = Mes::atual();
        $meses = [$atual->anterior()->anterior(), $atual->anterior(), $atual];
        $obter = app(ObterCompetencia::class);
        $carla = $this->usuariosEmpresa['empresa@contab.test'];

        foreach ($meses as $indice => $mes) {
            foreach ($this->empresas as $chave => $empresa) {
                if ($mes->primeiroDia()->lt($empresa->competenciaInicial()->primeiroDia())) {
                    continue;
                }

                $competencia = $obter->executar($empresa, $mes);
                $enviante = $carla->empresasIds() && in_array($empresa->id, $carla->empresasIds(), true)
                    ? $carla
                    : ($empresa->usuarios()->whereNotNull('convite_aceito_em')->first() ?? $this->admin);

                match ($indice) {
                    0 => $this->cenarioMesAntigo($competencia, $chave, $enviante),
                    1 => $this->cenarioMesPadrao($competencia, $chave, $enviante),
                    default => $this->cenarioMesAtual($competencia, $chave, $enviante),
                };
            }
        }
    }

    /** Dois meses atrás: quase tudo resolvido; a Empresa Demo tem o mês fechado. */
    private function cenarioMesAntigo(Competencia $competencia, string $empresa, User $enviante): void
    {
        foreach ($competencia->itens()->get() as $item) {
            $deixarPendente = $empresa === 'construtora' && $item->nome === 'Extrato bancário';
            $recusar = $empresa === 'padaria' && $item->nome === 'Comprovantes de despesas';
            // certidão positiva (RN-653) — como a CNDT aproveita documento válido, o alerta segue nos meses seguintes
            $positiva = $empresa === 'padaria' && $item->nome === 'CNDT — Débitos Trabalhistas';

            if ($deixarPendente) {
                continue; // atraso antigo — aparece no dashboard
            }

            $this->resolverItem($item, $enviante, recusar: $recusar, positiva: $positiva);
        }

        if ($empresa === 'demo') {
            app(FecharCompetencia::class)->executar($competencia, $this->admin);
        }
    }

    /** Mês anterior (competência padrão): cenário misto para avaliar a fila e o dashboard. */
    private function cenarioMesPadrao(Competencia $competencia, string $empresa, User $enviante): void
    {
        foreach ($competencia->itens()->get() as $i => $item) {
            if ($item->situacao !== SituacaoItem::Pendente) {
                continue; // ex.: CND aceita por validade (RN-656)
            }

            match (true) {
                // notas: vêm da importação de XML
                in_array($item->tipoDocumento->chave_sistema, [TipoDocumento::CHAVE_NOTAS_SAIDA, TipoDocumento::CHAVE_NOTAS_ENTRADA], true) => null,
                $empresa === 'demo' && $item->nome === 'Extrato bancário' => $this->enviar($item, $enviante),
                $empresa === 'demo' && $item->nome === 'CRF — Regularidade do FGTS' => $this->enviar($item, $enviante, validade: today()->addDays(25)),
                $empresa === 'demo' && $item->nome === 'Comprovantes de despesas' => app(InformarSemMovimento::class)->executar($item, $enviante, 'Não tivemos despesas com comprovante neste mês.'),
                $empresa === 'padaria' && $item->nome === 'Extrato bancário' => $this->resolverItem($item, $enviante, recusar: true),
                $empresa === 'padaria' && $item->eh_certidao => $this->resolverItem($item, $enviante),
                $empresa === 'clinica' => $this->resolverItem($item, $enviante),
                $empresa === 'construtora' && $item->eh_certidao => $this->enviar($item, $enviante, validade: today()->addDays(60)),
                default => null, // fica pendente (e atrasado, pois os prazos do mês seguinte já passaram)
            };
        }
    }

    /** Mês atual: tudo pendente, prazos ainda no futuro. Um envio antecipado. */
    private function cenarioMesAtual(Competencia $competencia, string $empresa, User $enviante): void
    {
        if ($empresa === 'clinica') {
            $item = $competencia->itens()->where('nome', 'Extrato bancário')->first();
            $item && $this->enviar($item, $enviante);
        }
    }

    private function resolverItem(ItemCompetencia $item, User $enviante, bool $recusar = false, bool $positiva = false): void
    {
        if ($item->situacao !== SituacaoItem::Pendente) {
            return;
        }

        if ($item->tipo === TipoItem::Tarefa) {
            app(ConcluirTarefa::class)->executar($item, $this->bruno);

            return;
        }

        $this->enviar($item, $enviante, validade: $item->exige_validade ? today()->addDays(120) : null);
        $item->refresh();

        if ($recusar) {
            app(RecusarItem::class)->executar($item, $this->bruno, MotivoRecusa::Ilegivel, 'A imagem está cortada; envie o PDF completo.');

            return;
        }

        app(AceitarItem::class)->executar(
            $item, $this->bruno, $item->validade?->toDateString(),
            $item->eh_certidao ? ($positiva ? ResultadoCertidao::Positiva : ResultadoCertidao::Negativa) : null,
        );
    }

    private function enviar(ItemCompetencia $item, User $enviante, $validade = null): void
    {
        $caminho = $this->pdfDeExemplo("escritorios/{$item->escritorio_id}/empresas/{$item->empresa_id}/documentos", Str::slug($item->nome));
        app(EnviarDocumentosDoItem::class)->executar($item, $enviante, [$caminho => Str::slug($item->nome).'.pdf'], $validade?->toDateString());
    }

    // ------------------------------------------------------------------ notas fiscais (seção 10)

    private function importarNotasFiscais(): void
    {
        $padrao = Mes::padrao();
        $atual = Mes::atual();
        $carla = $this->usuariosEmpresa['empresa@contab.test'];

        foreach (['demo' => 'Fornecedor Atacadista S.A.', 'padaria' => 'Moinho Trigo Bom Ltda'] as $chave => $fornecedor) {
            $empresa = $this->empresas[$chave];
            $cnpjFornecedor = $this->cnpj(($chave === 'demo' ? '88999000' : '99000111').'0001');
            $xmls = [];
            $numero = $chave === 'demo' ? 1000 : 5000;

            // saídas e entradas do mês padrão
            foreach ([[3, 1520.50], [8, 980.00], [15, 2340.75], [22, 455.10], [28, 3100.00]] as [$dia, $valor]) {
                $xmls[] = GeradorXmlNfe::nota($empresa->cnpj, $empresa->razao_social, $this->cpf(), 'Cliente Consumidor', $padrao->primeiroDia()->setDay($dia)->setTime(10, 30)->setTimezone('America/Sao_Paulo'), ++$numero, $valor);
            }
            foreach ([[5, 4200.00], [19, 1875.30]] as [$dia, $valor]) {
                $xmls[] = GeradorXmlNfe::nota($cnpjFornecedor, $fornecedor, $empresa->cnpj, $empresa->razao_social, $padrao->primeiroDia()->setDay($dia)->setTime(14, 0), ++$numero, $valor, '1', '55', '5101', '100', 'Venda de mercadoria a contribuinte');
            }
            // RN-721 — emitida às 23h30 do último dia do mês (fuso de Brasília): pertence ao mês padrão
            $xmls[] = GeradorXmlNfe::nota($empresa->cnpj, $empresa->razao_social, null, null, $padrao->ultimoDia()->setTime(23, 30), ++$numero, 89.90, '1', '65');
            // mês atual
            $xmls[] = GeradorXmlNfe::nota($empresa->cnpj, $empresa->razao_social, $this->cpf(), 'Cliente Novo', $atual->primeiroDia()->setDay(2)->setTime(9, 0), ++$numero, 610.00);
            $cancelada = GeradorXmlNfe::nota($empresa->cnpj, $empresa->razao_social, $this->cpf(), 'Cliente Desistente', $atual->primeiroDia()->setDay(3)->setTime(11, 0), ++$numero, 199.00);
            $xmls[] = $cancelada;

            $arquivos = [];
            foreach ($xmls as $i => $nota) {
                $arquivos[$this->arquivoTemporario($nota['xml'])] = "nfe-{$nota['chave']}.xml";
            }
            // repetida (RN-714), de outra empresa (RN-713) e cancelamento (RN-730)
            $arquivos[$this->arquivoTemporario($xmls[0]['xml'])] = 'nota-repetida.xml';
            $outra = GeradorXmlNfe::nota($this->cnpj('12121212'.'0001'), 'Empresa Estranha Ltda', $this->cnpj('13131313'.'0001'), 'Outra Ltda', $padrao->primeiroDia()->setDay(10), 999, 50.0);
            $arquivos[$this->arquivoTemporario($outra['xml'])] = 'nota-de-outra-empresa.xml';
            $arquivos[$this->arquivoTemporario(GeradorXmlNfe::cancelamento($cancelada['chave'], $atual->primeiroDia()->setDay(4)))] = 'cancelamento.xml';
            $arquivos[$this->arquivoTemporario('isto não é um xml')] = 'arquivo-invalido.xml';

            app(ImportarLoteXml::class)->executar($empresa, $chave === 'demo' ? $carla : $this->usuariosEmpresa['antonio@padaria.test'], $arquivos, sincrono: true);
        }

        // escritório aceita as notas da Empresa Demo no mês padrão (RN-717 → avaliação normal)
        ItemCompetencia::withoutGlobalScopes()->where('empresa_id', $this->empresas['demo']->id)
            ->where('situacao', SituacaoItem::EmAvaliacao)
            ->whereHas('competencia', fn ($q) => $q->withoutGlobalScopes()->whereDate('competencia', $padrao->paraBanco()))
            ->whereHas('tipoDocumento', fn ($q) => $q->withoutGlobalScopes()->where('chave_sistema', TipoDocumento::CHAVE_NOTAS_SAIDA))
            ->get()->each(fn ($item) => app(AceitarItem::class)->executar($item, $this->daniela));
    }

    // ------------------------------------------------------------------ admissões e desligamentos (seção 8)

    private function criarAdmissoes(): void
    {
        $carla = $this->usuariosEmpresa['empresa@contab.test'];
        $antonio = $this->usuariosEmpresa['antonio@padaria.test'];
        $marcos = $this->usuariosEmpresa['marcos@alicerce.test'];
        $proximaSegunda = CarbonImmutable::today()->next('Monday')->addWeek();

        // 1. Rascunho incompleto — a empresa ainda está preenchendo
        $this->admissao('demo', $carla, 'João Pereira', $proximaSegunda, completo: false);

        // 2. Em análise, sem responsável — aparece na fila
        $maria = $this->admissao('demo', $carla, 'Maria Oliveira', $proximaSegunda);
        app(SolicitarAdmissao::class)->executar($maria, $carla);

        // 3. Em análise, assumida pelo Bruno, com dois documentos já aceitos
        $pedro = $this->admissao('padaria', $antonio, 'Pedro Santos', today()->addDays(2));
        app(SolicitarAdmissao::class)->executar($pedro, $antonio);
        app(AssumirAdmissao::class)->executar($pedro, $this->bruno);
        foreach ($pedro->documentos()->take(2)->get() as $doc) {
            app(AvaliarDocumentoAdmissao::class)->executar($doc, $this->bruno, true);
        }

        // 4. Devolvida pela Daniela — a empresa precisa corrigir
        $ana = $this->admissao('demo', $carla, 'Ana Costa', $proximaSegunda->addDays(7));
        app(SolicitarAdmissao::class)->executar($ana, $carla);
        app(AssumirAdmissao::class)->executar($ana, $this->daniela);
        $comprovante = $ana->documentos()->where('tipo', DocumentoAdmissao::ComprovanteResidencia->value)->first();
        app(AvaliarDocumentoAdmissao::class)->executar($comprovante, $this->daniela, false, 'Comprovante ilegível e com mais de 90 dias.');
        app(DevolverAdmissao::class)->executar($ana, $this->daniela, 'Comprovante de residência ilegível e com mais de 90 dias. Envie um comprovante recente.');

        // 5 a 8. Admitidos (alimentam o relatório por colaborador)
        $carlos = $this->admitir('demo', $carla, 'Carlos Lima', Mes::atual()->primeiroDia(), $this->bruno, 'Vendedor', '521110');
        $lucia = $this->admitir('padaria', $antonio, 'Lúcia Ferreira', Mes::padrao()->primeiroDia()->setDay(15), $this->daniela, 'Padeira', '848305');
        $this->admitir('padaria', $antonio, 'Rafael Gomes', Mes::atual()->primeiroDia()->setDay(6), $this->bruno, 'Atendente', '521140');
        $roberto = $this->admitir('demo', $carla, 'Roberto Alves', CarbonImmutable::parse('2022-03-01'), $this->bruno, 'Estoquista', '414105');
        $fernanda = $this->admitir('construtora', $marcos, 'Fernanda Rocha', CarbonImmutable::parse('2023-08-14'), $this->daniela, 'Engenheira civil', '214205');

        // Desligado — dispensa sem justa causa, 4 anos completos → 42 dias de aviso (RN-542)
        app(IniciarDesligamento::class)->executar($roberto, $this->bruno, [
            'motivo_desligamento' => '02', 'tipo_aviso' => 'indenizado', 'data_desligamento' => Mes::atual()->primeiroDia()->setDay(10)->toDateString(),
            'data_aviso' => Mes::atual()->primeiroDia()->setDay(10)->toDateString(), 'pensao_alimenticia' => 'nao_ha',
        ]);
        app(ConcluirDesligamento::class)->executar($roberto->fresh(), $this->bruno, collect(ConcluirDesligamento::DOCUMENTOS_OBRIGATORIOS)
            ->mapWithKeys(fn ($rotulo, $chave) => [$chave => $this->pdfDeExemplo("escritorios/{$this->escritorio->id}/desligamentos", $chave)])->all(), '8450.32');

        // Em desligamento — pedido de demissão, aguardando documentos
        app(IniciarDesligamento::class)->executar($fernanda, $this->daniela, [
            'motivo_desligamento' => '07', 'tipo_aviso' => 'trabalhado', 'data_desligamento' => today()->addDays(20)->toDateString(),
            'data_aviso' => today()->subDays(10)->toDateString(), 'pensao_alimenticia' => 'nao_ha',
        ]);
    }

    private function admitir(string $empresa, User $solicitante, string $nome, $dataAdmissao, User $colaborador, string $cargo, string $cbo): Contrato
    {
        $contrato = $this->admissao($empresa, $solicitante, $nome, $dataAdmissao, cargo: $cargo, verificarPrazo: false);
        app(SolicitarAdmissao::class)->executar($contrato, $solicitante);
        app(AssumirAdmissao::class)->executar($contrato, $colaborador);

        foreach ($contrato->documentos()->get() as $doc) {
            app(AvaliarDocumentoAdmissao::class)->executar($doc, $colaborador, true);
        }

        app(ConcluirAdmissao::class)->executar($contrato->fresh(), $colaborador, [
            'matricula' => (string) (100 + $contrato->id), 'data_admissao' => CarbonImmutable::parse($dataAdmissao)->toDateString(),
            'categoria' => '101', 'cbo' => $cbo, 'natureza_atividade' => 'urbana', 'regime_jornada' => 'controle_horario',
            'recibo_esocial' => '1.1.'.str_pad((string) $contrato->id, 10, '0', STR_PAD_LEFT),
        ]);

        return $contrato->fresh();
    }

    private function admissao(string $empresa, User $solicitante, string $nome, $dataAdmissao, bool $completo = true, string $cargo = 'Auxiliar administrativo', bool $verificarPrazo = true): Contrato
    {
        $empresaModel = $this->empresas[$empresa];
        $pasta = "escritorios/{$this->escritorio->id}/admissoes";
        $documentos = [];
        $nomes = [];

        if ($completo) {
            foreach (DocumentoAdmissao::sempreObrigatorios() as $doc) {
                $documentos[$doc->value] = $this->pdfDeExemplo($pasta, $doc->value);
                $nomes[$doc->value] = $doc->value.'.pdf';
            }
        }

        $dados = [
            'funcionario' => array_filter([
                'nome' => $nome, 'cpf' => $this->cpf(),
                'data_nascimento' => $completo ? today()->subYears(rand(26, 45))->subDays(rand(1, 300))->toDateString() : null,
                'sexo' => str_ends_with(explode(' ', $nome)[0], 'a') ? 'F' : 'M', 'raca_cor' => $completo ? 'parda' : null,
                'estado_civil' => 'solteiro', 'grau_instrucao' => $completo ? 'medio_completo' : null,
                'pais_nascimento' => 'Brasil', 'nacionalidade' => 'Brasileira',
                'cep' => $completo ? '04001000' : null, 'logradouro' => $completo ? 'Rua Vergueiro' : null, 'numero' => $completo ? (string) rand(10, 999) : null,
                'bairro' => 'Paraíso', 'municipio' => $completo ? 'São Paulo' : null, 'uf' => $completo ? 'SP' : null,
                'telefone' => '(11) 9'.rand(1000, 9999).'-'.rand(1000, 9999), 'email' => Str::slug($nome, '.').'@email.test',
                'banco' => '341', 'agencia' => '0001', 'conta' => rand(10000, 99999).'-'.rand(0, 9),
                'dependentes' => $nome === 'Maria Oliveira' ? [['nome' => 'Sofia Oliveira', 'data_nascimento' => today()->subYears(4)->toDateString(), 'tipo' => 'filho', 'cpf' => $this->cpf(), 'dependente_irrf' => true, 'dependente_salario_familia' => true]] : [],
            ], fn ($v) => $v !== null),
            'estabelecimento_id' => $completo ? $empresaModel->estabelecimentos()->where('matriz', true)->value('id') : null,
            'data_admissao_prevista' => CarbonImmutable::parse($dataAdmissao)->toDateString(),
            'cargo' => $completo ? $cargo : null,
            'salario' => $completo ? rand(2200, 6500) : null,
            'unidade_salario' => 'mes',
            'tipo_contrato' => $completo ? ($nome === 'Maria Oliveira' ? TipoContrato::Experiencia->value : TipoContrato::Indeterminado->value) : null,
            'experiencia_dias_1' => $nome === 'Maria Oliveira' ? 45 : null,
            'experiencia_dias_2' => $nome === 'Maria Oliveira' ? 45 : null,
            'jornada_semanal' => $completo ? 44 : null,
            'horario' => $completo ? 'Seg a sex, 08h às 17h48' : null,
            'documentos' => $documentos,
            'documentos_nomes' => $nomes,
        ];

        return app(SalvarAdmissao::class)->criar($empresaModel, $solicitante, $dados);
    }

    // ------------------------------------------------------------------ cobranças, acessos, segundo escritório

    private function criarHistoricoDeCobrancas(): void
    {
        $enviar = app(EnviarCobrancaDocumentacao::class);

        // dias úteis anteriores (o dia de hoje fica livre para testar o comando e ver os e-mails no MailHog)
        $dias = collect(range(1, 6))->map(fn ($i) => CarbonImmutable::today()->subWeekdays($i))
            ->filter(fn ($d) => $d->day >= $this->escritorio->dia_corte_cobranca)->take(3);

        foreach ($dias as $dia) {
            foreach ($this->empresas as $empresa) {
                $enviar->executar($empresa, $dia);
            }
        }
    }

    private function criarAcessos(): void
    {
        $registros = [
            [$this->admin, 'login', 1], [$this->bruno, 'login', 2], [$this->usuariosEmpresa['empresa@contab.test'], 'login', 3],
            [null, 'falha_login', 4, 'intruso@exemplo.test'], [$this->daniela, 'logout', 5], [$this->admin, 'login', 6],
        ];

        foreach ($registros as $r) {
            Acesso::create([
                'escritorio_id' => $this->escritorio->id, 'user_id' => $r[0]?->id, 'email' => $r[0]?->email ?? $r[3],
                'evento' => $r[1], 'ip' => '192.168.0.'.(10 + $r[2]), 'user_agent' => 'Mozilla/5.0 (Windows NT 10.0) Chrome/130',
                'created_at' => now()->subHours($r[2] * 5),
            ]);
        }
    }

    /** regras.md seção 19 — 5 despesas de exemplo em cada empresa. */
    private function criarDespesas(): void
    {
        $modelos = [
            ['Aluguel do imóvel', 4500, 5], ['Conta de energia elétrica', 870.45, 12], ['Internet e telefonia', 289.90, 15],
            ['Fornecedor de materiais', 2350, 20], ['Honorários contábeis', 1200, 10],
        ];

        foreach ($this->empresas as $empresa) {
            foreach ($modelos as [$descricao, $valor, $dia]) {
                app(SalvarDespesa::class)->criar($empresa, $this->admin, [
                    'descricao' => $descricao, 'valor' => $valor, 'data_vencimento' => today()->startOfMonth()->addDays($dia - 1)->toDateString(),
                ]);
            }
        }
    }

    /** Escritório separado para conferir o isolamento entre tenants (RN-100). */
    private function criarSegundoEscritorio(): void
    {
        $outro = Escritorio::create([
            'razao_social' => 'Contabilidade Horizonte Ltda', 'cnpj' => $this->cnpj('10203040'.'0001'),
            'situacao' => SituacaoEscritorio::Ativo, 'dia_corte_cobranca' => 8,
        ]);
        $this->usuario('Otávio Horizonte', 'admin2@contab.test', TipoUsuario::Administrador, titular: true, escritorio: $outro);
        $this->criarCatalogo($outro);

        $empresa = Empresa::create([
            'escritorio_id' => $outro->id, 'cnpj' => $this->cnpj('50607080'.'0001'), 'razao_social' => 'Mercadinho Horizonte Ltda',
            'natureza_juridica' => '206-2 — Sociedade Empresária Limitada', 'regime_tributario' => RegimeTributario::SimplesNacional,
            'data_inicio_atendimento' => '2025-01-01', 'situacao' => SituacaoEmpresa::Ativa, 'municipio' => 'Campinas', 'uf' => 'SP',
        ]);
        $empresa->estabelecimentos()->create(['cnpj' => $empresa->cnpj, 'matriz' => true, 'nome' => 'Matriz']);
        app(ObterCompetencia::class)->executar($empresa, Mes::padrao());

        setPermissionsTeamId($this->escritorio->id);
    }

    // ------------------------------------------------------------------ utilitários

    private function cpf(): string
    {
        $base = str_pad((string) random_int(100000000, 999999999), 9, '0', STR_PAD_LEFT);

        return $base.DocumentoFiscal::digitosCpf($base);
    }

    private function cnpj(string $base12): string
    {
        return $base12.DocumentoFiscal::digitosCnpj($base12);
    }

    private function pdfDeExemplo(string $pasta, string $nome): string
    {
        $caminho = "{$pasta}/exemplo-{$nome}-".Str::random(6).'.pdf';
        $conteudo = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n"
            ."3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 595 842]/Contents 4 0 R/Resources<</Font<</F1 5 0 R>>>>>>endobj\n"
            ."4 0 obj<</Length 60>>stream\nBT /F1 18 Tf 72 760 Td (Documento de exemplo: {$nome}) Tj ET\nendstream endobj\n"
            ."5 0 obj<</Type/Font/Subtype/Type1/BaseFont/Helvetica>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF";
        Storage::disk('local')->put($caminho, $conteudo);

        return $caminho;
    }

    private function arquivoTemporario(string $conteudo): string
    {
        $caminho = 'tmp/xml/'.Str::random(12).'.xml';
        Storage::disk('local')->put($caminho, $conteudo);

        return $caminho;
    }
}
