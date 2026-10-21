<?php

namespace App\Actions\Despesas;

use App\Exceptions\RegraDeNegocioException;
use App\Models\CategoriaDespesa;
use App\Models\Despesa;
use App\Models\Empresa;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/** Cadastro e alteração de despesa da empresa — regras.md RN-1600 a RN-1602, RN-1606 a RN-1609. */
class SalvarDespesa
{
    private const CAMPOS = ['descricao', 'valor', 'data_vencimento', 'categoria_despesa_id'];

    public function __construct(private readonly AnexarDocumentosDespesa $anexarDocumentos) {}

    /**
     * Com `paga` marcado, a despesa já é cadastrada com o pagamento (`valor_pago` e `data_pagamento`) — RN-1608.
     * `documentos` traz os arquivos a anexar: [['tipo' => 'boleto', 'arquivo' => caminho, 'nome' => nome original], ...] — RN-1606.
     */
    public function criar(Empresa $empresa, User $user, array $dados): Despesa
    {
        $this->autorizar($user, $empresa);
        $pagamento = ! empty($dados['paga']) ? [$dados['valor_pago'] ?? null, $dados['data_pagamento'] ?? null] : null;
        $documentos = $dados['documentos'] ?? [];
        $dados = $this->validar($dados, $empresa);

        return DB::transaction(function () use ($empresa, $user, $dados, $pagamento, $documentos) {
            $despesa = Despesa::create($dados + [
                'escritorio_id' => $empresa->escritorio_id,
                'empresa_id' => $empresa->id,
                'cadastrada_por_id' => $user->id,
            ]);

            $this->anexarDocumentos->executar($despesa, $user, $documentos);

            // mesmas regras do registro de pagamento (RN-1605)
            if ($pagamento !== null) {
                app(RegistrarPagamentoDespesa::class)->executar($despesa, $user, ...$pagamento);
            }

            return $despesa;
        });
    }

    public function atualizar(Despesa $despesa, User $user, array $dados): Despesa
    {
        $this->autorizar($user, $despesa->empresa);
        $documentos = $dados['documentos'] ?? [];
        $dados = $this->validar($dados, $despesa->empresa, $despesa);

        DB::transaction(function () use ($despesa, $user, $dados, $documentos) {
            $despesa->update($dados);
            $this->anexarDocumentos->executar($despesa, $user, $documentos);
        });

        return $despesa;
    }

    private function autorizar(User $user, Empresa $empresa): void
    {
        RegraDeNegocioException::se(! $user->podeGerenciarDespesasDa($empresa), 'Você não tem permissão para gerenciar despesas desta empresa.');
    }

    private function validar(array $dados, Empresa $empresa, ?Despesa $despesa = null): array
    {
        $dados = Arr::only($dados, self::CAMPOS);
        $dados['descricao'] = trim((string) ($dados['descricao'] ?? ''));

        RegraDeNegocioException::se($dados['descricao'] === '', 'Informe a descrição da despesa.');
        RegraDeNegocioException::se(! is_numeric($dados['valor'] ?? null) || (float) $dados['valor'] <= 0, 'O valor da despesa deve ser maior que zero (RN-1601).');
        RegraDeNegocioException::se(blank($dados['data_vencimento'] ?? null), 'Informe a data de vencimento.');

        // RN-1609 — categoria ativa do escritório; uma despesa antiga pode manter a categoria que já tinha, mesmo inativada
        $categoria = CategoriaDespesa::withoutGlobalScopes()->where('escritorio_id', $empresa->escritorio_id)->find($dados['categoria_despesa_id'] ?? null);
        RegraDeNegocioException::se($categoria === null, 'Escolha a categoria da despesa.');
        RegraDeNegocioException::se(! $categoria->ativo && $despesa?->categoria_despesa_id !== $categoria->id, 'Esta categoria está inativa.');

        return $dados;
    }
}
