<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

/**
 * Perfis da equipe do escritório — regras.md RN-220 a RN-224.
 * O valor é o nome do role (Spatie) criado em cada escritório. Um colaborador pode
 * acumular perfis: as permissões efetivas são a soma das permissões de cada um.
 * O código continua verificando permissões, nunca o nome do perfil (AGENTS.md 6.2).
 */
enum Perfil: string implements HasColor, HasDescription, HasLabel
{
    case Operacional = 'Operacional';
    case Financeiro = 'Financeiro';

    public function getLabel(): string
    {
        return $this->value;
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::Operacional => 'Empresas, funcionários (admissões e desligamentos), avaliação de documentos, notas fiscais e usuários do portal.',
            self::Financeiro => 'Despesas das empresas e cobranças de documentação, com consulta às empresas e às notas fiscais.',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Operacional => 'info',
            self::Financeiro => 'success',
        };
    }

    /** @return array<Permissao> */
    public function permissoes(): array
    {
        return match ($this) {
            self::Operacional => [
                Permissao::EmpresasVisualizar,
                Permissao::EmpresasGerenciar,
                Permissao::UsuariosEmpresaGerenciar,
                Permissao::FuncionariosVisualizar,
                Permissao::AdmissoesAnalisar,
                Permissao::DesligamentosGerenciar,
                Permissao::DocumentosAvaliar,
                Permissao::CompetenciasGerenciar,
                Permissao::NotasVisualizar,
                Permissao::NotasGerenciar,
            ],
            self::Financeiro => [
                Permissao::EmpresasVisualizar,
                Permissao::NotasVisualizar,
                Permissao::DespesasGerenciar,
                Permissao::CobrancaGerenciar,
            ],
        };
    }
}
