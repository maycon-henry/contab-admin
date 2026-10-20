<?php

namespace App\Enums;

/**
 * Catálogo de permissões — AGENTS.md seção 6.2.
 * O código verifica permissões, nunca o nome do perfil.
 */
enum Permissao: string
{
    case EmpresasVisualizar = 'empresas.visualizar';
    case EmpresasGerenciar = 'empresas.gerenciar';
    case UsuariosEmpresaGerenciar = 'usuarios_empresa.gerenciar';
    case FuncionariosVisualizar = 'funcionarios.visualizar';
    case FuncionariosDadosSensiveis = 'funcionarios.dados_sensiveis';
    case AdmissoesAnalisar = 'admissoes.analisar';
    case DesligamentosGerenciar = 'desligamentos.gerenciar';
    case DocumentosAvaliar = 'documentos.avaliar';
    case CompetenciasGerenciar = 'competencias.gerenciar';
    case CompetenciasReabrir = 'competencias.reabrir';
    case CatalogoGerenciar = 'catalogo.gerenciar';
    case NotasVisualizar = 'notas.visualizar';
    case NotasGerenciar = 'notas.gerenciar';
    case CobrancaGerenciar = 'cobranca.gerenciar';
    case DespesasGerenciar = 'despesas.gerenciar';
    case RelatoriosAdmissoes = 'relatorios.admissoes';
    case DashboardAdministrador = 'dashboard.administrador';
    case AcessosVisualizar = 'acessos.visualizar';
    // Exclusivas do administrador (RN-223) — nunca atribuídas a perfis
    case EquipeGerenciar = 'equipe.gerenciar';
    case EscritorioConfigurar = 'escritorio.configurar';

    public function exclusivaDoAdministrador(): bool
    {
        return in_array($this, [self::EquipeGerenciar, self::EscritorioConfigurar], true);
    }
}
