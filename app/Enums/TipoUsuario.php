<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** regras.md RN-200 */
enum TipoUsuario: string implements HasColor, HasLabel
{
    case Administrador = 'administrador';
    case Colaborador = 'colaborador';
    case UsuarioEmpresa = 'usuario_empresa';

    public function getLabel(): string
    {
        return match ($this) {
            self::Administrador => 'Administrador',
            self::Colaborador => 'Colaborador',
            self::UsuarioEmpresa => 'Usuário da empresa',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Administrador => 'primary',
            self::Colaborador => 'info',
            self::UsuarioEmpresa => 'gray',
        };
    }

    public function ehEquipe(): bool
    {
        return $this !== self::UsuarioEmpresa;
    }
}
