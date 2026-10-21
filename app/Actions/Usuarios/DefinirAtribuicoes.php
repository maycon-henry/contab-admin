<?php

namespace App\Actions\Usuarios;

use App\Enums\Atribuicao;
use App\Enums\Permissao;
use App\Enums\TipoUsuario;
use App\Exceptions\RegraDeNegocioException;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/** Atribuições dos colaboradores — regras.md RN-220 a RN-224 e RN-234. */
class DefinirAtribuicoes
{
    /** Garante que as atribuições do catálogo existem no escritório, com as permissões atuais. */
    public function garantirNoEscritorio(int $escritorioId): void
    {
        setPermissionsTeamId($escritorioId);

        foreach (Atribuicao::cases() as $atribuicao) {
            $role = Role::firstOrCreate(['name' => $atribuicao->value, 'guard_name' => 'web', 'escritorio_id' => $escritorioId]);
            $permissoes = array_filter([...Atribuicao::PERMISSOES_BASICAS, ...$atribuicao->permissoes()], fn (Permissao $p) => ! $p->exclusivaDoAdministrador()); // RN-223
            $role->syncPermissions(array_map(fn (Permissao $p) => Permission::findOrCreate($p->value, 'web'), $permissoes));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Define as atribuições do usuário (substitui as anteriores). Administrador não tem atribuições:
     * já tem acesso total. Colaborador precisa de ao menos uma (RN-220).
     *
     * @param  array<Atribuicao|string>  $atribuicoes
     */
    public function executar(User $user, array $atribuicoes): void
    {
        $atribuicoes = collect($atribuicoes)->map(fn ($a) => $a instanceof Atribuicao ? $a : Atribuicao::from($a))->unique()->values();

        if ($user->tipo === TipoUsuario::Colaborador) {
            RegraDeNegocioException::se($atribuicoes->isEmpty(), 'Marque pelo menos uma atribuição para o colaborador (RN-220).');
        } else {
            $atribuicoes = collect();
        }

        $this->garantirNoEscritorio($user->escritorio_id);
        $user->syncRoles($atribuicoes->map(fn (Atribuicao $a) => $a->value)->all()); // RN-234 — vale imediatamente
    }

    /** @return array<Atribuicao> na ordem do catálogo */
    public static function atribuicoesDe(User $user): array
    {
        setPermissionsTeamId($user->escritorio_id);
        $nomes = $user->roles()->pluck('name')->all();

        return array_values(array_filter(Atribuicao::cases(), fn (Atribuicao $a) => in_array($a->value, $nomes, true)));
    }
}
