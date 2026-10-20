<?php

namespace App\Actions\Usuarios;

use App\Enums\Perfil;
use App\Enums\Permissao;
use App\Enums\TipoUsuario;
use App\Exceptions\RegraDeNegocioException;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/** Perfis dos colaboradores — regras.md RN-220 a RN-224 e RN-234. */
class DefinirPerfis
{
    /** Garante que os perfis do catálogo existem no escritório, com as permissões atuais. */
    public function garantirNoEscritorio(int $escritorioId): void
    {
        setPermissionsTeamId($escritorioId);

        foreach (Perfil::cases() as $perfil) {
            $role = Role::firstOrCreate(['name' => $perfil->value, 'guard_name' => 'web', 'escritorio_id' => $escritorioId]);
            $permissoes = array_filter($perfil->permissoes(), fn (Permissao $p) => ! $p->exclusivaDoAdministrador()); // RN-223
            $role->syncPermissions(array_map(fn (Permissao $p) => Permission::findOrCreate($p->value, 'web'), $permissoes));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Atribui os perfis ao usuário (substitui os anteriores). Administrador não tem perfis:
     * já tem acesso total. Colaborador precisa de ao menos um perfil (RN-220).
     *
     * @param  array<Perfil|string>  $perfis
     */
    public function executar(User $user, array $perfis): void
    {
        $perfis = collect($perfis)->map(fn ($p) => $p instanceof Perfil ? $p : Perfil::from($p))->unique()->values();

        if ($user->tipo === TipoUsuario::Colaborador) {
            RegraDeNegocioException::se($perfis->isEmpty(), 'Escolha pelo menos um perfil para o colaborador (RN-220).');
        } else {
            $perfis = collect();
        }

        $this->garantirNoEscritorio($user->escritorio_id);
        $user->syncRoles($perfis->map(fn (Perfil $p) => $p->value)->all()); // RN-234 — vale imediatamente
    }

    /** @return array<Perfil> */
    public static function perfisDe(User $user): array
    {
        setPermissionsTeamId($user->escritorio_id);

        return $user->roles()->orderBy('roles.id')->pluck('name')->map(fn ($nome) => Perfil::tryFrom($nome))->filter()->values()->all();
    }
}
