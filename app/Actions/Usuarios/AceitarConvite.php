<?php

namespace App\Actions\Usuarios;

use App\Exceptions\RegraDeNegocioException;
use App\Models\User;

/** regras.md RN-231 — o convidado define a própria senha. */
class AceitarConvite
{
    public function buscarPorToken(string $token): ?User
    {
        $user = User::where('convite_token', hash('sha256', $token))->first();

        return $user && $user->convite_expira_em?->isFuture() ? $user : null;
    }

    public function executar(string $token, string $senha): User
    {
        $user = $this->buscarPorToken($token);
        RegraDeNegocioException::se($user === null, 'Convite inválido ou expirado. Peça um novo convite ao escritório.');

        $user->forceFill([
            'password' => $senha,
            'convite_token' => null,
            'convite_expira_em' => null,
            'convite_aceito_em' => now(),
            'email_verified_at' => now(),
        ])->save();

        return $user;
    }
}
