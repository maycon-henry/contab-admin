<?php

namespace App\Providers\Filament;

use App\Filament\Auth\Login;
use App\Http\Middleware\DefinirEquipePermissoes;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/** Painel da equipe do escritório (administradores e colaboradores) — AGENTS.md seção 3.3. */
class EscritorioPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('escritorio')
            ->path('admin')
            ->login(Login::class)
            ->passwordReset()
            ->profile(isSimple: false)
            ->brandName('Contab Admin')
            ->colors(['primary' => Color::Sky])
            ->font('Inter')
            ->sidebarCollapsibleOnDesktop()
            ->navigationGroups([
                NavigationGroup::make('Rotina mensal'),
                NavigationGroup::make('Departamento pessoal'),
                NavigationGroup::make('Cadastros'),
                NavigationGroup::make('Relatórios'),
                NavigationGroup::make('Configurações'),
            ])
            ->discoverResources(in: app_path('Filament/Escritorio/Resources'), for: 'App\Filament\Escritorio\Resources')
            ->discoverPages(in: app_path('Filament/Escritorio/Pages'), for: 'App\Filament\Escritorio\Pages')
            ->discoverWidgets(in: app_path('Filament/Escritorio/Widgets'), for: 'App\Filament\Escritorio\Widgets')
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
