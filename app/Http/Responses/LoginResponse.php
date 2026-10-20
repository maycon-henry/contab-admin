<?php

namespace App\Http\Responses;

use Filament\Auth\Http\Responses\Contracts\LoginResponse as Contrato;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;

/** regras.md RN-304 — equipe vai para o painel do escritório; usuário da empresa para o portal. */
class LoginResponse implements Contrato
{
    public function toResponse($request): RedirectResponse|Redirector
    {
        return redirect()->to($request->user()->urlInicial());
    }
}
