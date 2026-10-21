<?php

namespace App\Actions\Usuarios;

use App\Enums\Atribuicao;
use App\Enums\Permissao;
use App\Enums\TipoUsuario;
use App\Exceptions\RegraDeNegocioException;
use App\Models\User;
use App\Notifications\ConviteNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** regras.md RN-230, RN-231, RN-232, RN-242. */
class ConvidarUsuario
{
    public function __construct(private readonly DefinirAtribuicoes $definirAtribuicoes) {}

    /**
     * @param  array<int>  $empresas  ids das empresas (usuário da empresa)
     * @param  array<Atribuicao|string>  $atribuicoes  atribuições do colaborador (RN-220)
     */
    public function executar(User $convidante, string $nome, string $email, TipoUsuario $tipo, array $empresas = [], array $atribuicoes = []): User
    {
        if ($tipo->ehEquipe()) {
            RegraDeNegocioException::se(! $convidante->can(Permissao::EquipeGerenciar->value), 'Somente administradores convidam pessoas da equipe (RN-230).');
        } else {
            RegraDeNegocioException::se(! $convidante->can(Permissao::UsuariosEmpresaGerenciar->value), 'Você não tem permissão para convidar usuários de empresas.');
            RegraDeNegocioException::se($empresas === [], 'Vincule o usuário a pelo menos uma empresa (RN-240).');
        }

        RegraDeNegocioException::se(User::where('email', $email)->exists(), 'Este e-mail já está em uso (RN-202).');

        return DB::transaction(function () use ($convidante, $nome, $email, $tipo, $empresas, $atribuicoes) {
            $user = User::create([
                'escritorio_id' => $convidante->escritorio_id,
                'name' => $nome,
                'email' => $email,
                'tipo' => $tipo,
                'ativo' => true,
            ]);

            if ($tipo === TipoUsuario::Colaborador) {
                $this->definirAtribuicoes->executar($user, $atribuicoes);
            }

            if ($tipo === TipoUsuario::UsuarioEmpresa) {
                $user->empresas()->sync($empresas);
            }

            $this->enviarConvite($user);

            return $user;
        });
    }

    /** RN-232 — novo link invalida o anterior; validade de 7 dias. */
    public function enviarConvite(User $user): void
    {
        RegraDeNegocioException::se($user->conviteAceito(), 'Este usuário já aceitou o convite.');

        $token = Str::random(64);
        $user->forceFill(['convite_token' => hash('sha256', $token), 'convite_expira_em' => now()->addDays(7)])->save();

        $user->notify(new ConviteNotification($token));
    }
}
