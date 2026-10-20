<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\EmpresaPanelProvider;
use App\Providers\Filament\EscritorioPanelProvider;
use App\Providers\Filament\PortalPanelProvider;

return [
    AppServiceProvider::class,
    EscritorioPanelProvider::class,
    EmpresaPanelProvider::class,
    PortalPanelProvider::class,
];
