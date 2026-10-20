<?php

namespace App\Actions\Desligamento;

use App\Enums\Permissao;
use App\Enums\SituacaoContrato;
use App\Exceptions\RegraDeNegocioException;
use App\Models\Contrato;
use App\Models\Documento;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** regras.md RN-546 e RN-547 — conclusão exige os documentos obrigatórios. */
class ConcluirDesligamento
{
    public const DOCUMENTOS_OBRIGATORIOS = [
        'trct' => 'TRCT assinado',
        'comprovante_pagamento' => 'Comprovante de pagamento das verbas rescisórias',
        'aso_demissional' => 'ASO demissional (ou último ASO, se dispensado — RN-545)',
        'fgts_rescisorio' => 'Comprovante de recolhimento do FGTS rescisório',
    ];

    /** @param array<string, string|null> $documentos chave => caminho */
    public function executar(Contrato $contrato, User $user, array $documentos, ?string $valorLiquido): void
    {
        RegraDeNegocioException::se(! $user->can(Permissao::DesligamentosGerenciar->value), 'Você não tem permissão para desligamentos.');
        RegraDeNegocioException::se($contrato->situacao !== SituacaoContrato::EmDesligamento, 'O desligamento não foi iniciado.');

        $faltando = collect(self::DOCUMENTOS_OBRIGATORIOS)->filter(fn ($rotulo, $chave) => blank($documentos[$chave] ?? null))->values()->all();
        if ($faltando) {
            throw RegraDeNegocioException::comPendencias('Anexe os documentos obrigatórios do desligamento (RN-546).', $faltando);
        }

        DB::transaction(function () use ($contrato, $user, $documentos, $valorLiquido) {
            foreach ($documentos as $chave => $caminho) {
                if (! $caminho) {
                    continue;
                }
                Documento::withoutGlobalScopes()->create([
                    'escritorio_id' => $contrato->escritorio_id,
                    'empresa_id' => $contrato->empresa_id,
                    'documentavel_type' => $contrato->getMorphClass(),
                    'documentavel_id' => $contrato->id,
                    'tipo' => 'desligamento_'.$chave,
                    'arquivo' => is_array($caminho) ? array_values($caminho)[0] : $caminho,
                    'nome_original' => basename(is_array($caminho) ? array_values($caminho)[0] : $caminho),
                    'origem' => 'escritorio',
                    'visivel_empresa' => true,
                    'enviado_por_id' => $user->id,
                ]);
            }

            $anterior = $contrato->situacao;
            $contrato->update([
                'situacao' => SituacaoContrato::Desligado,
                'valor_liquido_rescisao' => $valorLiquido,
                'desligado_por_id' => $user->id,
                'desligado_em' => now(),
            ]);
            $contrato->registrarHistorico('desligamento_concluido', 'Desligamento concluído.', $anterior, $user);
        });
    }
}
