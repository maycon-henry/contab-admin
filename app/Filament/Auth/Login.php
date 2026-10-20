<?php

namespace App\Filament\Auth;

use Filament\Auth\Pages\Login as LoginBase;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Tela de login única para os três tipos de usuário — regras.md RN-300 a RN-304.
 * A verificação de quem pode entrar (RN-303) é a mesma para qualquer painel;
 * o redirecionamento por tipo é feito em App\Http\Responses\LoginResponse.
 */
class Login extends LoginBase
{
    public function mount(): void
    {
        if (Filament::auth()->check()) {
            redirect()->to(Filament::auth()->user()->urlInicial());

            return;
        }

        parent::mount();
    }

    protected function isUserAllowedToAccessPanel(Authenticatable $user): bool
    {
        return method_exists($user, 'podeEntrar') && $user->podeEntrar();
    }
}
