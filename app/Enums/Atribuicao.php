<?php

namespace App\Enums;

use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

/**
 * Atribuições da equipe do escritório — regras.md RN-220 a RN-224.
 * Cada atribuição é um role (Spatie) criado em cada escritório, com o nome igual ao valor.
 * O administrador marca as atribuições de cada colaborador; as permissões efetivas são a soma
 * delas. O código continua verificando permissões, nunca o nome da atribuição (AGENTS.md 6.2).
 */
enum Atribuicao: string implements HasDescription, HasLabel
{
    case Empresas = 'empresas';
    case UsuariosPortal = 'usuarios_portal';
    case Admissoes = 'admissoes';
    case DadosSensiveis = 'dados_sensiveis';
    case Documentos = 'documentos';
    case Catalogo = 'catalogo';
    case ReabrirCompetencia = 'reabrir_competencia';
    case NotasFiscais = 'notas_fiscais';
    case Cobranca = 'cobranca';
    case Despesas = 'despesas';
    case Relatorios = 'relatorios';

    /** Permissões de consulta que todo colaborador tem, qualquer que seja a atribuição (RN-224). */
    public const PERMISSOES_BASICAS = [Permissao::EmpresasVisualizar, Permissao::NotasVisualizar];

    public function getLabel(): string
    {
        return match ($this) {
            self::Empresas => 'Cadastro de empresas',
            self::UsuariosPortal => 'Usuários do portal',
            self::Admissoes => 'Admissões e desligamentos',
            self::DadosSensiveis => 'Dados sensíveis de funcionários',
            self::Documentos => 'Avaliação de documentos',
            self::Catalogo => 'Catálogo de documentos',
            self::ReabrirCompetencia => 'Reabrir competência',
            self::NotasFiscais => 'Notas fiscais',
            self::Cobranca => 'Financeiro (cobrança de documentação)',
            self::Despesas => 'Despesas',
            self::Relatorios => 'Relatórios',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::Empresas => 'Cadastrar e alterar empresas clientes.',
            self::UsuariosPortal => 'Convidar e gerenciar os usuários das empresas no portal.',
            self::Admissoes => 'Fila de admissões, análise, conclusão e desligamentos de funcionários.',
            self::DadosSensiveis => 'Ver salário, dados bancários e demais dados sensíveis de funcionários (RN-570).',
            self::Documentos => 'Avaliar documentos enviados, enviar documentos em nome da empresa e concluir tarefas da rotina mensal.',
            self::Catalogo => 'Gerenciar o catálogo de documentos e tarefas mensais do escritório.',
            self::ReabrirCompetencia => 'Reabrir competências fechadas (RN-633).',
            self::NotasFiscais => 'Enviar XMLs em nome da empresa e mover notas de competência.',
            self::Cobranca => 'Cobranças de documentação das empresas (seção 16.1).',
            self::Despesas => 'Cadastrar despesas das empresas e registrar pagamentos.',
            self::Relatorios => 'Relatório de admissões por colaborador.',
        };
    }

    /** @return array<Permissao> permissões próprias da atribuição, além das básicas */
    public function permissoes(): array
    {
        return match ($this) {
            self::Empresas => [Permissao::EmpresasGerenciar],
            self::UsuariosPortal => [Permissao::UsuariosEmpresaGerenciar],
            self::Admissoes => [Permissao::FuncionariosVisualizar, Permissao::AdmissoesAnalisar, Permissao::DesligamentosGerenciar],
            self::DadosSensiveis => [Permissao::FuncionariosVisualizar, Permissao::FuncionariosDadosSensiveis],
            self::Documentos => [Permissao::DocumentosAvaliar, Permissao::CompetenciasGerenciar],
            self::Catalogo => [Permissao::CatalogoGerenciar],
            self::ReabrirCompetencia => [Permissao::CompetenciasReabrir],
            self::NotasFiscais => [Permissao::NotasGerenciar],
            self::Cobranca => [Permissao::CobrancaGerenciar],
            self::Despesas => [Permissao::DespesasGerenciar],
            self::Relatorios => [Permissao::RelatoriosAdmissoes],
        };
    }
}
