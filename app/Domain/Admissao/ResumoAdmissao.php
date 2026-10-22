<?php

namespace App\Domain\Admissao;

use App\Domain\DocumentoFiscal;
use App\Domain\Opcoes;
use App\Enums\DocumentoAdmissao;
use App\Enums\TipoContrato;
use Carbon\CarbonImmutable;

/**
 * Resumo da admissão antes do envio ao escritório — regras.md RN-586.
 *
 * Monta, a partir dos dados como estão no formulário (ainda não salvos), o que será enviado
 * e a lista de pendências, com os mesmos obrigatórios do VerificadorAdmissao. Na solicitação,
 * SolicitarAdmissao confere tudo de novo com os dados gravados.
 */
class ResumoAdmissao
{
    /**
     * @param  array<string, mixed>  $dados  estado do formulário (funcionario.*, dados do contrato, documentos.*)
     * @param  array<int|string, string>  $estabelecimentos  id => nome do local de trabalho
     * @return array{secoes: array<string, list<array{rotulo: string, valor: ?string, falta: bool}>>, documentos: list<array{rotulo: string, anexado: bool, obrigatorio: bool}>, pendencias: list<string>}
     */
    public function montar(array $dados, array $estabelecimentos = []): array
    {
        $f = (array) ($dados['funcionario'] ?? []);
        $tipoContrato = $this->tipoContrato($dados['tipo_contrato'] ?? null);

        $pessoais = [
            'nome' => $f['nome'] ?? null,
            'cpf' => filled($f['cpf'] ?? null) ? DocumentoFiscal::formatarCpf(DocumentoFiscal::somenteDigitos($f['cpf'])) : null,
            'data_nascimento' => $this->data($f['data_nascimento'] ?? null),
            'sexo' => Opcoes::SEXO[$f['sexo'] ?? ''] ?? null,
            'raca_cor' => Opcoes::RACA_COR[$f['raca_cor'] ?? ''] ?? null,
            'estado_civil' => Opcoes::ESTADO_CIVIL[$f['estado_civil'] ?? ''] ?? null,
            'grau_instrucao' => Opcoes::GRAU_INSTRUCAO[$f['grau_instrucao'] ?? ''] ?? null,
            'pais_nascimento' => $f['pais_nascimento'] ?? null,
            'nacionalidade' => $f['nacionalidade'] ?? null,
            'rg' => $f['rg'] ?? null,
            'pis' => $f['pis'] ?? null,
            'telefone' => $f['telefone'] ?? null,
            'email' => $f['email'] ?? null,
        ];
        $endereco = [
            'cep' => $f['cep'] ?? null, 'logradouro' => $f['logradouro'] ?? null, 'numero' => $f['numero'] ?? null,
            'complemento' => $f['complemento'] ?? null, 'bairro' => $f['bairro'] ?? null,
            'municipio' => $f['municipio'] ?? null, 'uf' => $f['uf'] ?? null,
        ];
        $contrato = [
            'data_admissao_prevista' => $this->data($dados['data_admissao_prevista'] ?? null),
            'cargo' => $dados['cargo'] ?? null,
            'estabelecimento_id' => $estabelecimentos[$dados['estabelecimento_id'] ?? ''] ?? null,
            'salario' => filled($dados['salario'] ?? null) ? 'R$ '.number_format((float) $dados['salario'], 2, ',', '.') : null,
            'tipo_contrato' => $tipoContrato?->getLabel(),
            'jornada_semanal' => filled($dados['jornada_semanal'] ?? null) ? $dados['jornada_semanal'].' h' : null,
            'horario' => $dados['horario'] ?? null,
        ];

        $rotulos = VerificadorAdmissao::OBRIGATORIOS_PESSOAIS + VerificadorAdmissao::OBRIGATORIOS_CONTRATO + [
            'raca_cor' => 'Raça/cor', 'estado_civil' => 'Estado civil', 'rg' => 'RG / CNH', 'pis' => 'PIS/NIS',
            'telefone' => 'Telefone', 'email' => 'E-mail', 'complemento' => 'Complemento', 'bairro' => 'Bairro', 'horario' => 'Horário',
        ];
        $linhas = fn (array $valores, array $obrigatorios) => collect($valores)->map(fn ($valor, $campo) => [
            'rotulo' => $rotulos[$campo],
            'valor' => filled($valor) ? (string) $valor : null,
            'falta' => array_key_exists($campo, $obrigatorios) && blank($valor),
        ])->values()->all();

        $secoes = [
            'Dados pessoais' => $linhas($pessoais, VerificadorAdmissao::OBRIGATORIOS_PESSOAIS),
            'Endereço' => $linhas($endereco, VerificadorAdmissao::OBRIGATORIOS_PESSOAIS),
            'Contrato' => $linhas($contrato, VerificadorAdmissao::OBRIGATORIOS_CONTRATO),
        ];

        $pendencias = collect(['Dados pessoais' => $pessoais + $endereco, 'Contrato' => $contrato])
            ->flatMap(fn ($valores, $secao) => collect($valores)
                ->filter(fn ($valor, $campo) => blank($valor) && array_key_exists($campo, VerificadorAdmissao::OBRIGATORIOS_PESSOAIS + VerificadorAdmissao::OBRIGATORIOS_CONTRATO))
                ->map(fn ($valor, $campo) => "{$secao}: {$rotulos[$campo]}")->values())
            ->all();

        if ($tipoContrato === TipoContrato::Experiencia && blank($dados['experiencia_dias_1'] ?? null)) {
            $pendencias[] = 'Contrato: duração do 1º período de experiência';
        }
        if ($tipoContrato === TipoContrato::Determinado && blank($dados['data_termino'] ?? null)) {
            $pendencias[] = 'Contrato: data de término';
        }

        $documentos = collect(DocumentoAdmissao::cases())->map(fn (DocumentoAdmissao $tipo) => [
            'rotulo' => $tipo->getLabel(),
            'anexado' => filled($dados['documentos'][$tipo->value] ?? null),
            'obrigatorio' => $tipo->obrigatorio(),
        ])->all();
        foreach ($documentos as $documento) {
            if ($documento['obrigatorio'] && ! $documento['anexado']) {
                $pendencias[] = 'Documento: '.$documento['rotulo'];
            }
        }

        return ['secoes' => $secoes, 'documentos' => $documentos, 'pendencias' => $pendencias];
    }

    private function tipoContrato(mixed $valor): ?TipoContrato
    {
        return $valor instanceof TipoContrato ? $valor : TipoContrato::tryFrom((string) $valor);
    }

    private function data(mixed $valor): ?string
    {
        return filled($valor) ? CarbonImmutable::parse((string) $valor)->format('d/m/Y') : null;
    }
}
