<?php

namespace App\Actions\Despesas;

use App\Enums\Permissao;
use App\Exceptions\RegraDeNegocioException;
use App\Models\CategoriaDespesa;
use App\Models\User;

/** regras.md RN-1609 — categorias de despesa, mantidas pelo administrador; inativadas, nunca excluídas. */
class SalvarCategoriaDespesa
{
    public function criar(User $user, string $nome): CategoriaDespesa
    {
        $nome = $this->validar($user, $nome);

        return CategoriaDespesa::create(['escritorio_id' => $user->escritorio_id, 'nome' => $nome, 'ativo' => true]);
    }

    public function atualizar(CategoriaDespesa $categoria, User $user, string $nome, bool $ativo): CategoriaDespesa
    {
        RegraDeNegocioException::se($categoria->escritorio_id !== $user->escritorio_id, 'Categoria de outro escritório.');
        $categoria->update(['nome' => $this->validar($user, $nome, $categoria->id), 'ativo' => $ativo]);

        return $categoria;
    }

    private function validar(User $user, string $nome, ?int $ignorar = null): string
    {
        RegraDeNegocioException::se(! $user->can(Permissao::EscritorioConfigurar->value), 'Somente o administrador gerencia as categorias de despesa.');

        $nome = trim($nome);
        RegraDeNegocioException::se($nome === '', 'Informe o nome da categoria.');
        RegraDeNegocioException::se(
            CategoriaDespesa::withoutGlobalScopes()->where('escritorio_id', $user->escritorio_id)->whereRaw('lower(nome) = ?', [mb_strtolower($nome)])
                ->when($ignorar, fn ($q) => $q->whereKeyNot($ignorar))->exists(),
            'Já existe uma categoria com este nome.',
        );

        return $nome;
    }
}
