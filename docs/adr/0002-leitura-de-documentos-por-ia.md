# ADR 0002 — Leitura de documentos por IA na admissão

- **Situação:** aceita
- **Data:** 2026-10-21

## Contexto

No pedido de admissão (`regras.md` seção 8.3) a empresa digita os dados pessoais do funcionário que já estão
nos documentos que ela anexa (RG/CNH, comprovante de residência...). Queremos que, ao anexar o documento, os
campos vazios sejam pré-preenchidos (RN-579), sem nunca sobrescrever o que o usuário digitou.

## Decisão

- Leitura feita pela **API da Anthropic (Claude)**, com o SDK oficial `anthropic-ai/sdk` (nova dependência).
  O modelo recebe a imagem ou o PDF e devolve um JSON restrito aos campos pedidos (*structured outputs*),
  com `null` para o que não consta. Modelo configurável em `ANTHROPIC_MODELO` (padrão `claude-opus-5-5`),
  esforço `low` (extração simples) e *fallback* do lado do servidor se o modelo recusar por política.
- A integração fica atrás de `App\Contracts\LeitorDadosDeDocumento` (AGENTS.md 3.1); a regra — o que cada
  documento pode preencher, validação dos valores e "só campos vazios" — fica no domínio
  (`PreenchimentoAutomatico`), testável sem a API.
- Sem `ANTHROPIC_API_KEY` a funcionalidade fica desligada e o formulário segue só manual.
- Só são enviados os documentos que podem preencher algum campo (identificação, comprovante de residência,
  reservista, certidão de casamento, escolaridade). ASO e documentos de dependentes não são enviados.
- A leitura é síncrona (o usuário espera alguns segundos após o upload); falha vira aviso e não bloqueia nada.

## Consequências

- **LGPD:** documentos pessoais do funcionário passam a ser enviados a um suboperador (Anthropic, fora do
  Brasil). Antes de produção: incluir a Anthropic na lista de suboperadores, revisar contrato/DPA e as
  configurações de retenção de dados da conta da API, e informar os escritórios clientes.
- Custo por documento lido (cobrado por token); acompanhar o uso pelo console da Anthropic.
- Os logs registram só o tipo do documento e a classe do erro, nunca os dados lidos (AGENTS.md 7).
- Endpoint equivalente na API v1 fica pendente junto com o restante da API (AGENTS.md 12); a Action
  `PreencherComDocumento` já está pronta para ser exposta.
