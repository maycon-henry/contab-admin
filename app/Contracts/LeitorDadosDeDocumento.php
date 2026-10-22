<?php

namespace App\Contracts;

/**
 * Lê dados pessoais de um documento (imagem ou PDF) para pré-preencher o cadastro do
 * funcionário — regras.md RN-579. A implementação devolve os valores como encontrou no
 * documento; normalizar e decidir o que preencher é do domínio (PreenchimentoAutomatico).
 */
interface LeitorDadosDeDocumento
{
    /** Se a leitura está configurada (ex.: chave da API informada). */
    public function disponivel(): bool;

    /**
     * @param  string  $conteudo  bytes do arquivo
     * @param  string  $mimeType  application/pdf, image/jpeg ou image/png
     * @param  string  $tipoDocumento  nome do documento do checklist (ex.: "Comprovante de residência")
     * @param  array<string, string>  $campos  campo => instrução do que procurar
     * @return array<string, string|null> campo => valor encontrado (null quando não consta)
     */
    public function ler(string $conteudo, string $mimeType, string $tipoDocumento, array $campos): array;
}
