<?php

namespace App\Actions\Despesas;

use App\Enums\Permissao;
use App\Exceptions\RegraDeNegocioException;
use App\Models\Despesa;
use App\Models\Empresa;
use App\Models\User;
use Illuminate\Support\Arr;

/** Cadastro e alteração de despesa da empresa — regras.md RN-1600 a RN-1602. */
class SalvarDespesa
{
    private const CAMPOS = ['descricao', 'valor', 'data_vencimento', 'boleto_caminho', 'boleto_nome'];

    public function criar(Empresa $empresa, User $user, array $dados): Despesa
    {
        $this->autorizar($user, $empresa);
        $dados = $this->validar($dados);

        return Despesa::create($dados + [
            'escritorio_id' => $empresa->escritorio_id,
            'empresa_id' => $empresa->id,
            'cadastrada_por_id' => $user->id,
        ]);
    }

    public function atualizar(Despesa $despesa, User $user, array $dados): Despesa
    {
        $this->autorizar($user, $despesa->empresa);
        $despesa->update($this->validar($dados));

        return $despesa;
    }

    private function autorizar(User $user, Empresa $empresa): void
    {
        RegraDeNegocioException::se(
            $empresa->escritorio_id !== $user->escritorio_id || ! $user->can(Permissao::DespesasGerenciar->value),
            'Você não tem permissão para gerenciar despesas desta empresa.',
        );
    }

    private function validar(array $dados): array
    {
        $dados = Arr::only($dados, self::CAMPOS);
        $dados['descricao'] = trim((string) ($dados['descricao'] ?? ''));

        RegraDeNegocioException::se($dados['descricao'] === '', 'Informe a descrição da despesa.');
        RegraDeNegocioException::se(! is_numeric($dados['valor'] ?? null) || (float) $dados['valor'] <= 0, 'O valor da despesa deve ser maior que zero (RN-1601).');
        RegraDeNegocioException::se(blank($dados['data_vencimento'] ?? null), 'Informe a data de vencimento.');

        return $dados;
    }
}
