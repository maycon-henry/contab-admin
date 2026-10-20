<?php

namespace App\Http\Controllers;

use App\Actions\Usuarios\AceitarConvite;
use App\Exceptions\RegraDeNegocioException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/** regras.md RN-231 — o convidado cria a própria senha e já entra no sistema. */
class ConviteController extends Controller
{
    public function mostrar(string $token, AceitarConvite $aceitar): View
    {
        return view('convite', ['token' => $token, 'user' => $aceitar->buscarPorToken($token)]);
    }

    public function aceitar(Request $request, string $token, AceitarConvite $aceitar): RedirectResponse
    {
        $dados = $request->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        try {
            $user = $aceitar->executar($token, $dados['password']);
        } catch (RegraDeNegocioException $e) {
            return back()->withErrors(['password' => $e->getMessage()]);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect($user->urlInicial());
    }
}
