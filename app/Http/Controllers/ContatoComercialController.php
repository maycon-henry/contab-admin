<?php

namespace App\Http\Controllers;

use App\Models\ContatoComercial;
use App\Notifications\NovoContatoComercialNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

/** regras.md RN-004 — formulário "Agende uma demonstração" da página pública. */
class ContatoComercialController extends Controller
{
    public const FAIXAS_EMPRESAS = ['ate_30' => 'Até 30', '31_100' => '31 a 100', '101_300' => '101 a 300', 'mais_300' => 'Mais de 300'];

    public function store(Request $request): RedirectResponse
    {
        // anti-spam: campo invisível preenchido por robôs
        if (filled($request->input('site'))) {
            return redirect()->to(route('landing').'#contato')->with('contato_enviado', true);
        }

        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:120'],
            'escritorio' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:160'],
            'telefone' => ['nullable', 'string', 'max:30'],
            'quantidade_empresas' => ['nullable', 'in:'.implode(',', array_keys(self::FAIXAS_EMPRESAS))],
            'mensagem' => ['nullable', 'string', 'max:2000'],
        ]);

        $contato = ContatoComercial::create(array_merge($dados, [
            'quantidade_empresas' => self::FAIXAS_EMPRESAS[$dados['quantidade_empresas'] ?? ''] ?? null,
            'ip' => $request->ip(),
        ]));

        Notification::route('mail', config('services.comercial.email'))->notify(new NovoContatoComercialNotification($contato));

        return redirect()->to(route('landing').'#contato')->with('contato_enviado', true);
    }
}
