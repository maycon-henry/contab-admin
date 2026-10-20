<?php

namespace App\Domain\Admissao;

use App\Domain\DocumentoFiscal;
use App\Enums\DocumentoAdmissao;
use App\Enums\TipoContrato;
use App\Models\Contrato;

/**
 * Pendências e validações legais da admissão — regras.md RN-583, RN-586, RN-587, RN-598 e seção 8.4.
 * Devolve listas de textos prontos para a tela e para a API.
 */
class VerificadorAdmissao
{
    /** Salário mínimo nacional usado no alerta de RN-524. */
    public const SALARIO_MINIMO = 1518.00;

    /** Pendências do que cabe à empresa preencher/anexar (RN-582, RN-583). */
    public function pendenciasDaEmpresa(Contrato $contrato): array
    {
        $contrato->load(['funcionario', 'documentos']); // sempre do banco: documentos podem ter acabado de ser salvos
        $f = $contrato->funcionario;
        $pendencias = [];

        $obrigatoriosPessoais = [
            'nome' => 'Nome completo', 'cpf' => 'CPF', 'data_nascimento' => 'Data de nascimento', 'sexo' => 'Sexo',
            'raca_cor' => 'Raça/cor', 'grau_instrucao' => 'Grau de instrução', 'pais_nascimento' => 'País de nascimento',
            'nacionalidade' => 'Nacionalidade', 'cep' => 'CEP', 'logradouro' => 'Logradouro', 'numero' => 'Número',
            'municipio' => 'Município', 'uf' => 'UF',
        ];
        foreach ($obrigatoriosPessoais as $campo => $rotulo) {
            if (blank($f?->{$campo})) {
                $pendencias[] = "Dados pessoais: {$rotulo}";
            }
        }

        $obrigatoriosContrato = [
            'data_admissao_prevista' => 'Data de admissão prevista', 'cargo' => 'Cargo', 'salario' => 'Salário',
            'tipo_contrato' => 'Tipo de contrato', 'jornada_semanal' => 'Jornada semanal', 'estabelecimento_id' => 'Local de trabalho',
        ];
        foreach ($obrigatoriosContrato as $campo => $rotulo) {
            if (blank($contrato->{$campo})) {
                $pendencias[] = "Contrato: {$rotulo}";
            }
        }

        if ($contrato->tipo_contrato === TipoContrato::Experiencia && blank($contrato->experiencia_dias_1)) {
            $pendencias[] = 'Contrato: duração do 1º período de experiência';
        }
        if ($contrato->tipo_contrato === TipoContrato::Determinado && blank($contrato->data_termino)) {
            $pendencias[] = 'Contrato: data de término';
        }

        foreach (DocumentoAdmissao::sempreObrigatorios() as $documento) {
            if (! $contrato->documentoDoChecklist($documento)) {
                $pendencias[] = 'Documento: '.$documento->getLabel();
            }
        }

        return $pendencias;
    }

    /** Regras legais verificadas na solicitação (RN-587). Retorna erros que impedem a solicitação. */
    public function errosLegais(Contrato $contrato): array
    {
        $contrato->loadMissing('funcionario');
        $f = $contrato->funcionario;
        $erros = [];

        if ($f?->cpf && ! DocumentoFiscal::cpfValido($f->cpf)) {
            $erros[] = 'CPF do funcionário inválido.';
        }

        if ($f?->data_nascimento && $contrato->data_admissao_prevista) {
            $idade = $f->data_nascimento->diffInYears($contrato->data_admissao_prevista);
            if ($idade < 16) {
                $erros[] = 'Idade mínima na admissão é 16 anos (14 para aprendiz) — RN-511.';
            }
        }

        if ($contrato->tipo_contrato === TipoContrato::Experiencia) {
            $total = (int) $contrato->experiencia_dias_1 + (int) $contrato->experiencia_dias_2;
            if ($total > 90) {
                $erros[] = 'O contrato de experiência não pode passar de 90 dias no total — RN-522.';
            }
        }

        if ($contrato->tipo_contrato === TipoContrato::Determinado && $contrato->data_termino && $contrato->data_admissao_prevista
            && $contrato->data_admissao_prevista->diffInDays($contrato->data_termino) > 730) {
            $erros[] = 'O contrato por prazo determinado não pode passar de 2 anos — RN-523.';
        }

        return $erros;
    }

    /** Alertas que não impedem a solicitação (RN-524, RN-589). */
    public function alertas(Contrato $contrato): array
    {
        $alertas = [];

        if ($contrato->salario && $contrato->jornada_semanal) {
            $minimoProporcional = self::SALARIO_MINIMO * min(1, (float) $contrato->jornada_semanal / 44);
            if ((float) $contrato->salario < $minimoProporcional) {
                $alertas[] = 'Salário abaixo do salário mínimo proporcional à jornada (RN-524).';
            }
        }

        // RN-589 — antecedência mínima sugerida: 2 dias úteis (decisão D27)
        if ($contrato->data_admissao_prevista && today()->addWeekdays(2)->gt($contrato->data_admissao_prevista)) {
            $alertas[] = 'A data de admissão está muito próxima: o registro no eSocial deve ser feito até o dia anterior ao início do trabalho (RN-589).';
        }

        return $alertas;
    }

    /** O que falta para o escritório concluir (RN-598). */
    public function pendenciasDoEscritorio(Contrato $contrato): array
    {
        $pendencias = [];

        foreach (['matricula' => 'Matrícula', 'categoria' => 'Categoria do trabalhador', 'cbo' => 'CBO',
            'natureza_atividade' => 'Natureza da atividade', 'regime_jornada' => 'Regime de jornada', 'data_admissao' => 'Data de admissão efetiva'] as $campo => $rotulo) {
            if (blank($contrato->{$campo})) {
                $pendencias[] = $rotulo;
            }
        }

        foreach (DocumentoAdmissao::sempreObrigatorios() as $documento) {
            $doc = $contrato->documentoDoChecklist($documento);
            if (! $doc || $doc->avaliacao !== 'aceito') {
                $pendencias[] = 'Documento não aceito: '.$documento->getLabel();
            }
        }

        return $pendencias;
    }
}
