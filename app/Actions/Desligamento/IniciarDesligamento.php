<?php

namespace App\Actions\Desligamento;

use App\Domain\Desligamento\CalculadoraAvisoPrevio;
use App\Enums\MotivoDesligamento;
use App\Enums\Permissao;
use App\Enums\SituacaoContrato;
use App\Enums\TipoAviso;
use App\Exceptions\RegraDeNegocioException;
use App\Models\Contrato;
use App\Models\User;
use Carbon\Carbon;

/** regras.md RN-540 a RN-543. */
class IniciarDesligamento
{
    public function __construct(private readonly CalculadoraAvisoPrevio $calculadora) {}

    public function executar(Contrato $contrato, User $user, array $dados): void
    {
        RegraDeNegocioException::se(! $user->can(Permissao::DesligamentosGerenciar->value), 'Você não tem permissão para desligamentos.');
        RegraDeNegocioException::se($contrato->situacao !== SituacaoContrato::Admitido, 'Somente funcionários admitidos podem ser desligados.');

        $motivo = MotivoDesligamento::from($dados['motivo_desligamento']);
        $tipoAviso = TipoAviso::from($dados['tipo_aviso']);
        $desligamento = Carbon::parse($dados['data_desligamento']);
        $admissao = $contrato->data_admissao ?? $contrato->data_admissao_prevista;

        RegraDeNegocioException::se($desligamento->lt($admissao), 'A data de desligamento não pode ser anterior à admissão (RN-543).');

        $dias = filled($dados['dias_aviso'] ?? null)
            ? (int) $dados['dias_aviso']
            : ($tipoAviso === TipoAviso::NaoSeAplica ? 0 : $this->calculadora->dias($motivo, $admissao, $desligamento));

        $anterior = $contrato->situacao;
        $contrato->update([
            'situacao' => SituacaoContrato::EmDesligamento,
            'motivo_desligamento' => $motivo,
            'tipo_aviso' => $tipoAviso,
            'data_aviso' => $dados['data_aviso'] ?? null,
            'data_desligamento' => $desligamento,
            'dias_aviso' => $dias,
            'data_projetada_fim_aviso' => $this->calculadora->dataProjetadaFimAviso($tipoAviso, $desligamento, $dias),
            'pensao_alimenticia' => $dados['pensao_alimenticia'] ?? 'nao_ha',
            'observacoes_desligamento' => $dados['observacoes_desligamento'] ?? null,
        ]);

        $contrato->registrarHistorico('desligamento_iniciado', 'Desligamento iniciado: '.$motivo->getLabel().'.', $anterior, $user);
    }
}
