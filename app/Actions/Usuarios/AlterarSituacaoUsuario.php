<?php

namespace App\Actions\Usuarios;

use App\Enums\Permissao;
use App\Enums\TipoUsuario;
use App\Exceptions\RegraDeNegocioException;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** regras.md RN-210, RN-213, RN-233. */
class AlterarSituacaoUsuario
{
    public function desativar(User $user, User $alvo): void
    {
        $this->autorizar($user, $alvo);
        RegraDeNegocioException::se($alvo->id === $user->id, 'Você não pode desativar a si mesmo (RN-213).');
        RegraDeNegocioException::se($alvo->titular, 'O administrador titular não pode ser desativado. Transfira a titularidade antes (RN-210).');

        $alvo->update(['ativo' => false]);

        // RN-233 — revoga sessões imediatamente
        DB::table('sessions')->where('user_id', $alvo->id)->delete();
        $alvo->setRememberToken(null);
        $alvo->save();
    }

    public function ativar(User $user, User $alvo): void
    {
        $this->autorizar($user, $alvo);
        $alvo->update(['ativo' => true]);
    }

    /** RN-211 — somente o titular transfere a titularidade, para outro administrador. */
    public function transferirTitularidade(User $titular, User $novo): void
    {
        RegraDeNegocioException::se(! $titular->titular, 'Somente o administrador titular pode transferir a titularidade (RN-211).');
        RegraDeNegocioException::se($novo->tipo !== TipoUsuario::Administrador || ! $novo->ativo || $novo->escritorio_id !== $titular->escritorio_id,
            'A titularidade só pode ser transferida para outro administrador ativo do escritório.');

        DB::transaction(function () use ($titular, $novo) {
            $titular->update(['titular' => false]);
            $novo->update(['titular' => true]);
        });
    }

    private function autorizar(User $user, User $alvo): void
    {
        RegraDeNegocioException::se($alvo->escritorio_id !== $user->escritorio_id, 'Usuário de outro escritório.');

        $permissao = $alvo->ehDaEquipe() ? Permissao::EquipeGerenciar : Permissao::UsuariosEmpresaGerenciar;
        RegraDeNegocioException::se(! $user->can($permissao->value), 'Você não tem permissão para alterar este usuário.');
    }
}
