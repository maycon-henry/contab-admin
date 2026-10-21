<?php

namespace App\Actions\Despesas;

use App\Exceptions\RegraDeNegocioException;
use App\Models\Despesa;
use App\Models\User;
use Carbon\CarbonImmutable;

/** regras.md RN-1605 — registro do pagamento da despesa. */
class RegistrarPagamentoDespesa
{
    public function executar(Despesa $despesa, User $user, mixed $valorPago, mixed $dataPagamento): Despesa
    {
        RegraDeNegocioException::se(! $user->podeGerenciarDespesasDa($despesa->empresa), 'Você não tem permissão para registrar pagamentos desta empresa.');
        RegraDeNegocioException::se($despesa->estaPaga(), 'O pagamento desta despesa já foi registrado.');
        RegraDeNegocioException::se(! is_numeric($valorPago) || (float) $valorPago <= 0, 'O valor pago deve ser maior que zero.');
        RegraDeNegocioException::se(blank($dataPagamento), 'Informe a data do pagamento.');

        $data = CarbonImmutable::parse($dataPagamento)->startOfDay();
        RegraDeNegocioException::se($data->isFuture(), 'A data do pagamento não pode ser futura.');

        $despesa->update([
            'valor_pago' => $valorPago,
            'data_pagamento' => $data->toDateString(),
            'pagamento_registrado_por_id' => $user->id,
            'pagamento_registrado_em' => now(),
        ]);

        return $despesa;
    }
}
