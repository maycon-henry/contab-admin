<?php

namespace App\Auditoria;

use App\Models\Acesso;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;

/** regras.md RN-330 — login (sucesso e falha), logout e redefinição de senha. */
class RegistrarAcesso
{
    public function handle(Login|Failed|Logout|PasswordReset $evento): void
    {
        $user = $evento->user ?? null;

        Acesso::withoutGlobalScopes()->create([
            'escritorio_id' => $user?->escritorio_id,
            'user_id' => $user?->id,
            'email' => $user?->email ?? ($evento instanceof Failed ? ($evento->credentials['email'] ?? null) : null),
            'evento' => match (true) {
                $evento instanceof Login => 'login',
                $evento instanceof Failed => 'falha_login',
                $evento instanceof Logout => 'logout',
                $evento instanceof PasswordReset => 'senha_redefinida',
            },
            'ip' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 500),
        ]);
    }
}
