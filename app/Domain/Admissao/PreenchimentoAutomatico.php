<?php

namespace App\Domain\Admissao;

use App\Domain\DocumentoFiscal;
use App\Domain\Opcoes;
use App\Enums\DocumentoAdmissao;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Pré-preenchimento do cadastro do funcionário a partir dos documentos — regras.md RN-579.
 *
 * Define o que cada documento do checklist pode preencher (ex.: do comprovante de residência
 * só sai o endereço, pois ele pode estar em nome de outra pessoa), valida o que foi lido e
 * devolve apenas os campos que ainda estão vazios — nunca altera o que o usuário preencheu.
 */
class PreenchimentoAutomatico
{
    private const INSTRUCOES = [
        'nome' => 'nome completo da pessoa titular do documento',
        'cpf' => 'CPF do titular, 11 dígitos',
        'data_nascimento' => 'data de nascimento do titular',
        'sexo' => 'sexo do titular: "M" ou "F"',
        'rg' => 'número do RG, da CIN ou do registro da CNH',
        'pis' => 'número do PIS/PASEP/NIS, se constar',
        'nacionalidade' => 'nacionalidade (ex.: "Brasileira")',
        'pais_nascimento' => 'país de nascimento (ex.: "Brasil")',
        'estado_civil' => 'estado civil atual registrado: "casado" ou "divorciado" (havendo averbação de divórcio)',
        'grau_instrucao' => 'escolaridade concluída, um destes: fundamental_completo, medio_completo, superior_completo, pos_graduacao, mestrado, doutorado',
        'cep' => 'CEP do endereço, 8 dígitos',
        'logradouro' => 'logradouro (rua, avenida...) sem o número',
        'numero' => 'número do imóvel',
        'complemento' => 'complemento (apto, bloco...)',
        'bairro' => 'bairro',
        'municipio' => 'município',
        'uf' => 'sigla da UF com 2 letras',
    ];

    private const ENDERECO = ['cep', 'logradouro', 'numero', 'complemento', 'bairro', 'municipio', 'uf'];

    /** Rótulos para avisar o usuário do que foi preenchido. */
    public const ROTULOS = [
        'nome' => 'Nome completo', 'cpf' => 'CPF', 'data_nascimento' => 'Data de nascimento', 'sexo' => 'Sexo',
        'rg' => 'RG / CNH', 'pis' => 'PIS/NIS', 'nacionalidade' => 'Nacionalidade', 'pais_nascimento' => 'País de nascimento',
        'estado_civil' => 'Estado civil', 'grau_instrucao' => 'Grau de instrução', 'cep' => 'CEP', 'logradouro' => 'Logradouro',
        'numero' => 'Número', 'complemento' => 'Complemento', 'bairro' => 'Bairro', 'municipio' => 'Município', 'uf' => 'UF',
    ];

    /**
     * Campos que podem ser lidos de cada documento, com a instrução para a leitura.
     *
     * @return array<string, string>
     */
    public function camposDoDocumento(DocumentoAdmissao $tipo): array
    {
        $campos = match ($tipo) {
            DocumentoAdmissao::DocumentoComFoto => ['nome', 'cpf', 'data_nascimento', 'sexo', 'rg', 'nacionalidade', 'pais_nascimento'],
            DocumentoAdmissao::ComprovanteResidencia => self::ENDERECO,
            DocumentoAdmissao::CertificadoReservista => ['nome', 'cpf', 'data_nascimento'],
            DocumentoAdmissao::CertidaoCasamento => ['estado_civil'],
            DocumentoAdmissao::ComprovanteEscolaridade => ['grau_instrucao'],
            // ASO, contrato e documentos de dependentes não trazem dados do cadastro do funcionário
            default => [],
        };

        return array_intersect_key(self::INSTRUCOES, array_flip($campos));
    }

    /**
     * Valida e padroniza o que foi lido. Valores inválidos são descartados.
     *
     * @param  array<string, string|null>  $lidos
     * @return array<string, string>
     */
    public function normalizar(array $lidos): array
    {
        $dados = [];

        foreach ($lidos as $campo => $valor) {
            $valor = trim((string) $valor);
            if ($valor === '' || ! array_key_exists($campo, self::INSTRUCOES)) {
                continue;
            }

            $valor = match ($campo) {
                'cpf' => DocumentoFiscal::cpfValido($digitos = DocumentoFiscal::somenteDigitos($valor)) ? $digitos : null,
                'cep' => strlen($digitos = DocumentoFiscal::somenteDigitos($valor)) === 8 ? $digitos : null,
                'pis' => strlen($digitos = DocumentoFiscal::somenteDigitos($valor)) === 11 ? $digitos : null,
                'data_nascimento' => $this->data($valor),
                'sexo' => in_array($sexo = mb_strtoupper(mb_substr($valor, 0, 1)), array_keys(Opcoes::SEXO), true) ? $sexo : null,
                'estado_civil' => array_key_exists($valor, Opcoes::ESTADO_CIVIL) ? $valor : null,
                'grau_instrucao' => array_key_exists($valor, Opcoes::GRAU_INSTRUCAO) ? $valor : null,
                'uf' => array_key_exists($uf = mb_strtoupper($valor), Opcoes::ufs()) ? $uf : null,
                'nome', 'logradouro', 'bairro', 'municipio' => $this->capitalizar($valor),
                default => $valor,
            };

            if ($valor !== null) {
                $dados[$campo] = $valor;
            }
        }

        return $dados;
    }

    /**
     * Só os campos ainda vazios recebem o valor lido; o que já foi preenchido não muda.
     *
     * @param  array<string, mixed>  $atuais  dados atuais do funcionário (estado do formulário)
     * @param  array<string, string>  $lidos  dados já normalizados
     * @return array<string, string>
     */
    public function camposAPreencher(array $atuais, array $lidos): array
    {
        return array_filter($lidos, fn ($valor, $campo) => blank($atuais[$campo] ?? null), ARRAY_FILTER_USE_BOTH);
    }

    private function data(string $valor): ?string
    {
        try {
            $data = str_contains($valor, '/')
                ? CarbonImmutable::createFromFormat('!d/m/Y', $valor)
                : CarbonImmutable::createFromFormat('!Y-m-d', $valor);
        } catch (Throwable) {
            return null;
        }

        return $data && $data->year >= 1900 && $data->isPast() ? $data->toDateString() : null;
    }

    /** Documentos costumam vir em caixa alta: "MARIA DA SILVA" vira "Maria da Silva". */
    private function capitalizar(string $valor): string
    {
        if (mb_strtoupper($valor) !== $valor) {
            return $valor;
        }

        return collect(explode(' ', mb_convert_case(mb_strtolower($valor), MB_CASE_TITLE)))
            ->map(fn (string $palavra, int $i) => $i > 0 && in_array(mb_strtolower($palavra), ['da', 'de', 'do', 'das', 'dos', 'e'], true) ? mb_strtolower($palavra) : $palavra)
            ->implode(' ');
    }
}
