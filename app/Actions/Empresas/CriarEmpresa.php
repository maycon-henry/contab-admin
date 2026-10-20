<?php

namespace App\Actions\Empresas;

use App\Domain\DocumentoFiscal;
use App\Enums\Permissao;
use App\Exceptions\RegraDeNegocioException;
use App\Models\Empresa;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** regras.md RN-400, RN-401 e RN-411 (matriz criada automaticamente). */
class CriarEmpresa
{
    public function executar(User $user, array $dados): Empresa
    {
        RegraDeNegocioException::se(! $user->can(Permissao::EmpresasGerenciar->value), 'Você não tem permissão para cadastrar empresas.');

        $dados['cnpj'] = DocumentoFiscal::somenteDigitos($dados['cnpj'] ?? '');
        RegraDeNegocioException::se(! DocumentoFiscal::cnpjValido($dados['cnpj']), 'CNPJ inválido (RN-400).');
        RegraDeNegocioException::se(
            Empresa::withoutGlobalScopes()->where('escritorio_id', $user->escritorio_id)->where('cnpj', $dados['cnpj'])->exists(),
            'Já existe uma empresa com este CNPJ no escritório (RN-400).',
        );

        return DB::transaction(function () use ($user, $dados) {
            $empresa = Empresa::create($dados + ['escritorio_id' => $user->escritorio_id]);

            $empresa->estabelecimentos()->create([
                'cnpj' => $empresa->cnpj,
                'matriz' => true,
                'nome' => 'Matriz',
                'municipio' => $empresa->municipio,
                'uf' => $empresa->uf,
                'cnae_preponderante' => $empresa->cnae_principal,
            ]);

            return $empresa;
        });
    }
}
