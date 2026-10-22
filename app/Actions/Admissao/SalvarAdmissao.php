<?php

namespace App\Actions\Admissao;

use App\Domain\DocumentoFiscal;
use App\Enums\DocumentoAdmissao;
use App\Enums\Permissao;
use App\Enums\SituacaoContrato;
use App\Enums\TipoUsuario;
use App\Exceptions\RegraDeNegocioException;
use App\Models\Contrato;
use App\Models\Documento;
use App\Models\Empresa;
use App\Models\Funcionario;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Cria ou atualiza uma admissão (funcionário + contrato + dependentes + documentos).
 * regras.md RN-580 a RN-585, RN-588 e RN-594.
 */
class SalvarAdmissao
{
    private const CAMPOS_FUNCIONARIO = [
        'nome', 'nome_social', 'cpf', 'data_nascimento', 'sexo', 'raca_cor', 'estado_civil', 'grau_instrucao',
        'pais_nascimento', 'nacionalidade', 'cep', 'logradouro', 'numero', 'complemento', 'bairro', 'municipio', 'uf',
        'telefone', 'email', 'rg', 'pis', 'banco', 'agencia', 'conta', 'chave_pix',
        'pessoa_com_deficiencia', 'tipo_deficiencia', 'estrangeiro', 'condicao_ingresso',
    ];

    private const CAMPOS_CONTRATO_EMPRESA = [
        'estabelecimento_id', 'data_admissao_prevista', 'cargo', 'salario', 'unidade_salario', 'tipo_contrato',
        'experiencia_dias_1', 'experiencia_dias_2', 'data_termino', 'jornada_semanal', 'horario',
    ];

    private const CAMPOS_CONTRATO_ESCRITORIO = [
        'matricula', 'data_admissao', 'categoria', 'cbo', 'natureza_atividade', 'regime_jornada', 'sindicato_cnpj', 'recibo_esocial',
    ];

    public function criar(Empresa $empresa, User $user, array $dados): Contrato
    {
        $this->autorizar($user, null, $empresa);

        $cpf = DocumentoFiscal::somenteDigitos(Arr::get($dados, 'funcionario.cpf'));
        RegraDeNegocioException::se(! DocumentoFiscal::cpfValido($cpf), 'CPF inválido.');

        return DB::transaction(function () use ($empresa, $user, $dados, $cpf) {
            $funcionario = Funcionario::withoutGlobalScopes()->firstOrNew(['empresa_id' => $empresa->id, 'cpf' => $cpf]);

            // RN-502 — um contrato não encerrado por vez na mesma empresa
            if ($funcionario->exists) {
                $emAndamento = $funcionario->contratos()->withoutGlobalScopes()->get()
                    ->contains(fn (Contrato $c) => $c->situacao->emAndamento());
                RegraDeNegocioException::se($emAndamento, 'Este CPF já tem um contrato em andamento nesta empresa (RN-502).');
            }

            $funcionario->fill(Arr::only($dados['funcionario'] ?? [], self::CAMPOS_FUNCIONARIO));
            $funcionario->cpf = $cpf;
            $funcionario->escritorio_id = $empresa->escritorio_id;
            $funcionario->save();

            $contrato = new Contrato(Arr::only($dados, self::CAMPOS_CONTRATO_EMPRESA));
            $contrato->escritorio_id = $empresa->escritorio_id;
            $contrato->empresa_id = $empresa->id;
            $contrato->funcionario_id = $funcionario->id;
            $contrato->situacao = SituacaoContrato::Rascunho;
            $contrato->save();

            $this->sincronizarDependentes($funcionario, $dados['funcionario']['dependentes'] ?? []);
            $this->sincronizarDocumentos($contrato, $user, $dados['documentos'] ?? [], $dados['documentos_nomes'] ?? []);
            $this->registrarQuemSalvou($contrato, $user);

            $contrato->registrarHistorico('criada', 'Admissão iniciada (rascunho).', null, $user);

            return $contrato;
        });
    }

    public function atualizar(Contrato $contrato, User $user, array $dados): Contrato
    {
        $this->autorizar($user, $contrato, $contrato->empresa);

        return DB::transaction(function () use ($contrato, $user, $dados) {
            $funcionario = $contrato->funcionario;
            $this->registrarQuemSalvou($contrato, $user);
            $antes = $funcionario->only(self::CAMPOS_FUNCIONARIO) + $contrato->only(self::CAMPOS_CONTRATO_EMPRESA);

            $dadosFuncionario = Arr::only($dados['funcionario'] ?? [], self::CAMPOS_FUNCIONARIO);
            unset($dadosFuncionario['cpf']); // CPF não muda depois de criado
            $funcionario->update($dadosFuncionario);
            $this->sincronizarDependentes($funcionario, $dados['funcionario']['dependentes'] ?? []);

            // Funcionário já admitido: só o cadastro pessoal muda. Cargo, salário e jornada
            // mudam por histórico com vigência (RN-525), nunca sobrescrevendo o contrato.
            if ($contrato->situacao === SituacaoContrato::Admitido) {
                $alterados = collect($funcionario->only(self::CAMPOS_FUNCIONARIO))
                    ->filter(fn ($valor, $campo) => (string) ($antes[$campo] ?? '') !== (string) $valor)->keys();
                if ($alterados->isNotEmpty()) {
                    $contrato->registrarHistorico('cadastro_atualizado', 'Cadastro do funcionário atualizado: '.$alterados->implode(', ').'.', $contrato->situacao, $user);
                }

                return $contrato;
            }

            $campos = self::CAMPOS_CONTRATO_EMPRESA;
            if ($user->ehDaEquipe()) {
                $campos = array_merge($campos, self::CAMPOS_CONTRATO_ESCRITORIO);
            }
            $contrato->update(Arr::only($dados, $campos));

            $this->sincronizarDocumentos($contrato, $user, $dados['documentos'] ?? [], $dados['documentos_nomes'] ?? []);

            // RN-594 — correções do escritório durante a análise ficam registradas
            if ($user->ehDaEquipe() && $contrato->situacao === SituacaoContrato::EmAnalise) {
                $depois = $funcionario->fresh()->only(self::CAMPOS_FUNCIONARIO) + $contrato->fresh()->only(self::CAMPOS_CONTRATO_EMPRESA);
                $alterados = collect($depois)->filter(fn ($valor, $campo) => (string) ($antes[$campo] ?? '') !== (string) $valor)->keys();
                if ($alterados->isNotEmpty()) {
                    $contrato->registrarHistorico('corrigida', 'Dados corrigidos pelo escritório: '.$alterados->implode(', ').'.', $contrato->situacao, $user);
                }
            }

            return $contrato;
        });
    }

    private function autorizar(User $user, ?Contrato $contrato, Empresa $empresa): void
    {
        if ($user->tipo === TipoUsuario::UsuarioEmpresa) {
            RegraDeNegocioException::se(! in_array($empresa->id, $user->empresasIds(), true), 'Empresa não vinculada ao usuário.');
            RegraDeNegocioException::se($contrato && ! $contrato->situacao->editavelPelaEmpresa(),
                'Depois de solicitada, a admissão não pode mais ser alterada pela empresa (RN-588).');

            return;
        }

        RegraDeNegocioException::se(! $user->can(Permissao::AdmissoesAnalisar->value), 'Você não tem permissão para editar admissões.');
        RegraDeNegocioException::se($contrato && ! in_array($contrato->situacao, [SituacaoContrato::Rascunho, SituacaoContrato::Devolvida, SituacaoContrato::EmAnalise, SituacaoContrato::Admitido], true),
            'Esta admissão não pode mais ser editada.');
    }

    /** RN-550 — quem salvou a última versão do formulário e quando. */
    public function registrarQuemSalvou(Contrato $contrato, User $user): void
    {
        $contrato->forceFill(['formulario_salvo_por_id' => $user->id, 'formulario_salvo_em' => now()])->save();
    }

    private function sincronizarDependentes(Funcionario $funcionario, array $dependentes): void
    {
        $funcionario->dependentes()->delete();

        foreach ($dependentes as $dependente) {
            if (blank($dependente['nome'] ?? null)) {
                continue;
            }
            $funcionario->dependentes()->create(Arr::only($dependente, [
                'nome', 'cpf', 'data_nascimento', 'tipo', 'dependente_irrf', 'dependente_salario_familia',
            ]));
        }
    }

    /**
     * Grava as versões novas dos documentos do checklist (RN-562). Documento novo nasce sem
     * avaliação, isto é, aguardando o escritório.
     *
     * @param  array<string, string|null>  $documentos  chave do checklist => caminho do arquivo
     * @return array<DocumentoAdmissao> tipos que mudaram
     */
    public function sincronizarDocumentos(Contrato $contrato, User $user, array $documentos, array $nomes): array
    {
        $alterados = [];
        $contrato->load('documentos');

        foreach (DocumentoAdmissao::cases() as $tipo) {
            $caminho = $documentos[$tipo->value] ?? null;
            $caminho = is_array($caminho) ? (array_values($caminho)[0] ?? null) : $caminho;
            $atual = $contrato->documentoDoChecklist($tipo);

            if (($atual && $atual->arquivo === $caminho) || (! $atual && ! $caminho)) {
                continue;
            }

            // substituição ou remoção mantém a versão anterior (RN-562)
            $atual?->update(['substituido' => true]);
            $alterados[] = $tipo;

            if ($caminho) {
                $nome = $nomes[$tipo->value] ?? null;
                Documento::withoutGlobalScopes()->create([
                    'escritorio_id' => $contrato->escritorio_id,
                    'empresa_id' => $contrato->empresa_id,
                    'documentavel_type' => $contrato->getMorphClass(),
                    'documentavel_id' => $contrato->id,
                    'tipo' => $tipo->value,
                    'arquivo' => $caminho,
                    'nome_original' => is_array($nome) ? (array_values($nome)[0] ?? basename($caminho)) : ($nome ?: basename($caminho)),
                    'origem' => $user->tipo === TipoUsuario::UsuarioEmpresa ? 'empresa' : 'escritorio',
                    'visivel_empresa' => true,
                    'enviado_por_id' => $user->id,
                ]);
            }
        }

        $contrato->unsetRelation('documentos');

        return $alterados;
    }
}
