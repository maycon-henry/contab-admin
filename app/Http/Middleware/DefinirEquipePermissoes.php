<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Perfis e permissões isolados por escritório (Spatie "teams") — AGENTS.md seção 6.2. */
class DefinirEquipePermissoes
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($user = $request->user()) {
            setPermissionsTeamId($user->escritorio_id);
        }

        return $next($request);
    }
}
