<?php

namespace App\Providers;

use App\Auditoria\RegistrarAcesso;
use App\Enums\TipoUsuario;
use App\Http\Responses\LoginResponse;
use Filament\Actions\Action;
use Filament\Auth\Http\Responses\Contracts\LoginResponse as LoginResponseContract;
use Filament\Tables\Table;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // RN-304 — após o login, cada tipo de usuário vai para a sua área
        $this->app->bind(LoginResponseContract::class, LoginResponse::class);
    }

    public function boot(): void
    {
        // AGENTS.md seção 6.2 — administrador tem acesso total ao seu escritório
        Gate::before(fn ($user) => $user->tipo === TipoUsuario::Administrador && $user->ativo ? true : null);

        // RN-310 / RN-311 — política de senha única
        Password::defaults(fn () => Password::min(8)->letters()->numbers()->uncompromised());

        // RN-330 — registro de acessos
        foreach ([Login::class, Failed::class, Logout::class, PasswordReset::class] as $evento) {
            Event::listen($evento, RegistrarAcesso::class);
        }

        // Em todas as tabelas: "Aplicar filtros" também fecha o painel de filtros (quando ele é um dropdown)
        Table::configureUsing(fn (Table $table) => $table->filtersApplyAction(
            // o handler Alpine substitui o wire:click, por isso ele mesmo chama applyTableFilters
            fn (Action $action) => $action->alpineClickHandler("\$wire.applyTableFilters(); \$el.closest('.fi-dropdown') && close()"),
        ));
    }
}
