<?php

namespace App\Actions\Admissao;

use App\Enums\DocumentoAdmissao;
use App\Enums\Permissao;
use App\Enums\SituacaoContrato;
use App\Enums\TipoUsuario;
use App\Exceptions\RegraDeNegocioException;
use App\Models\Contrato;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Funcionário já admitido: a empresa (ou o escritório) troca ou inclui documentos — regras.md
 * RN-565 a RN-567. Os dados do funcionário não mudam aqui; cada documento novo fica
 * "em análise" até alguém do Operacional aceitar ou recusar.
 */
class AtualizarDocumentosDoFuncionario
{
    public function __construct(private readonly SalvarAdmissao $salvarAdmissao) {}

    /** @return array<DocumentoAdmissao> documentos que mudaram */
    public function executar(Contrato $contrato, User $user, array $documentos, array $nomes = []): array
    {
        if ($user->tipo === TipoUsuario::UsuarioEmpresa) {
            RegraDeNegocioException::se(! in_array($contrato->empresa_id, $user->empresasIds(), true), 'Empresa não vinculada ao usuário.');
        } else {
            RegraDeNegocioException::se($contrato->escritorio_id !== $user->escritorio_id || ! $user->can(Permissao::AdmissoesAnalisar->value),
                'Você não tem permissão para atualizar documentos deste funcionário.');
        }

        RegraDeNegocioException::se($contrato->situacao !== SituacaoContrato::Admitido,
            'Só é possível atualizar documentos de funcionário admitido. Durante a admissão, use o formulário da admissão.');

        // RN-566 — funcionário admitido: documento só é trocado, nunca removido
        $contrato->load('documentos');
        foreach (DocumentoAdmissao::cases() as $tipo) {
            $atual = $contrato->documentoDoChecklist($tipo);
            if ($atual && blank($documentos[$tipo->value] ?? null)) {
                $documentos[$tipo->value] = $atual->arquivo;
            }
        }

        return DB::transaction(function () use ($contrato, $user, $documentos, $nomes) {
            $alterados = $this->salvarAdmissao->sincronizarDocumentos($contrato, $user, $documentos, $nomes);

            if ($alterados !== []) {
                $this->salvarAdmissao->registrarQuemSalvou($contrato, $user);
                $contrato->registrarHistorico('documentos_atualizados',
                    'Documentos atualizados, aguardando avaliação do escritório: '.collect($alterados)->map(fn (DocumentoAdmissao $t) => $t->getLabel())->implode(', ').'.',
                    $contrato->situacao, $user);
            }

            return $alterados;
        });
    }
}
