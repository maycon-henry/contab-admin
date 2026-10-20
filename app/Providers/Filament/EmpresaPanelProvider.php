<?php

namespace App\Providers\Filament;

use App\Http\Middleware\DefinirEquipePermissoes;
use App\Models\Empresa;
use Filament\Actions\Action;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Contexto de uma empresa cliente para a equipe do escritório — AGENTS.md seção 3.3.
 * Ao selecionar uma empresa, o menu lateral passa a ser só dela: notas de entrada, notas de saída,
 * funcionários e cadastro. A empresa faz parte da URL (/admin/empresa/{empresa}/...) e o seletor
 * no topo troca de empresa sem sair do contexto.
 */
class EmpresaPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('empresa')
            ->path('admin/empresa')
            ->tenant(Empresa::class)
            ->searchableTenantMenu()
            ->brandName('Contab Admin')
            // o logo "Contab Admin" leva à tela inicial do escritório (só afeta o link do logo)
            ->homeUrl(fn () => url('/admin'))
            ->colors(['primary' => Color::Sky])
            ->font('Inter')
            ->sidebarCollapsibleOnDesktop()
            ->userMenuItems([
                Action::make('escritorio')->label('Voltar ao painel do escritório')->icon('heroicon-o-arrow-uturn-left')->url(fn () => url('/admin')),
            ])
            ->discoverResources(in: app_path('Filament/Empresa/Resources'), for: 'App\Filament\Empresa\Resources')
            ->discoverPages(in: app_path('Filament/Empresa/Pages'), for: 'App\Filament\Empresa\Pages')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                DefinirEquipePermissoes::class,
            ]);
    }
}
