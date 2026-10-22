<?php

namespace App\Integrations\Anthropic;

use Anthropic\Client;
use App\Contracts\LeitorDadosDeDocumento;
use RuntimeException;

/**
 * Leitura de documentos pessoais com o Claude (API da Anthropic) — regras.md RN-579.
 * A resposta é restrita a um JSON com exatamente os campos pedidos (structured outputs).
 */
class LeitorDadosDeDocumentoClaude implements LeitorDadosDeDocumento
{
    /** Limite da API para imagens (5 MB depois de codificar em base64). */
    private const TAMANHO_MAXIMO_IMAGEM = 3_750_000;

    private const INSTRUCOES = <<<'TXT'
        Você lê documentos brasileiros enviados para a admissão de um funcionário e extrai dados pessoais
        para pré-preencher o cadastro. Regras:
        - Extraia somente o que estiver escrito e legível no documento. Nunca deduza nem invente.
        - Se um campo não constar, estiver ilegível ou houver dúvida, devolva null.
        - Datas no formato AAAA-MM-DD. CPF, CEP e PIS somente com dígitos.
        - Para campos com valores permitidos, use exatamente um dos valores listados ou null.
        - Escreva nomes e endereços como estão no documento, sem abreviar.
        TXT;

    public function __construct(
        private readonly ?string $chave,
        private readonly string $modelo,
    ) {}

    public function disponivel(): bool
    {
        return filled($this->chave);
    }

    public function ler(string $conteudo, string $mimeType, string $tipoDocumento, array $campos): array
    {
        if (! $this->disponivel() || $campos === []) {
            return [];
        }

        $arquivo = match ($mimeType) {
            'application/pdf' => ['type' => 'document', 'source' => ['type' => 'base64', 'mediaType' => 'application/pdf', 'data' => base64_encode($conteudo)]],
            'image/jpeg', 'image/png' => strlen($conteudo) <= self::TAMANHO_MAXIMO_IMAGEM
                ? ['type' => 'image', 'source' => ['type' => 'base64', 'mediaType' => $mimeType, 'data' => base64_encode($conteudo)]]
                : throw new RuntimeException('Imagem grande demais para leitura automática.'),
            default => throw new RuntimeException("Formato não suportado na leitura automática: {$mimeType}."),
        };

        $pedido = "Documento enviado: {$tipoDocumento}.\nCampos a extrair:\n"
            .collect($campos)->map(fn (string $instrucao, string $campo) => "- {$campo}: {$instrucao}")->implode("\n");

        $resposta = (new Client(apiKey: $this->chave))->beta->messages->create(
            model: $this->modelo,
            maxTokens: 16000,
            system: self::INSTRUCOES,
            messages: [['role' => 'user', 'content' => [$arquivo, ['type' => 'text', 'text' => $pedido]]]],
            outputConfig: [
                'effort' => 'low',
                'format' => ['type' => 'json_schema', 'schema' => [
                    'type' => 'object',
                    'properties' => collect($campos)->map(fn () => ['type' => ['string', 'null']])->all(),
                    'required' => array_keys($campos),
                    'additionalProperties' => false,
                ]],
            ],
            // se o modelo recusar por política, a própria API tenta o modelo substituto padrão
            fallbacks: 'default',
            betas: ['server-side-fallback-2026-07-01'],
            requestOptions: ['timeout' => 90.0],
        );

        if ($resposta->stopReason === 'refusal') {
            throw new RuntimeException('A leitura automática recusou o documento.');
        }

        foreach ($resposta->content as $bloco) {
            if ($bloco->type === 'text') {
                $dados = json_decode($bloco->text, true, flags: JSON_THROW_ON_ERROR);

                return collect($campos)->keys()
                    ->mapWithKeys(fn (string $campo) => [$campo => is_scalar($dados[$campo] ?? null) ? (string) $dados[$campo] : null])
                    ->all();
            }
        }

        throw new RuntimeException('A leitura automática não devolveu dados.');
    }
}
