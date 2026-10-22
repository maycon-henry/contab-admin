---
name: qualidade-codigo
description: Padrão de qualidade do Contab Admin — SOLID, design patterns, clean code e testes aplicados à arquitetura deste projeto (Laravel + Filament, Actions, Domain, Contracts). Use SEMPRE antes de escrever ou alterar código PHP do projeto (Action, classe de domínio, Model, Enum, tela Filament, integração, teste), ao planejar uma funcionalidade, ao revisar um diff, ou quando pedirem "padrão", "qualidade", "SOLID", "design pattern", "clean code", "refatorar" ou "revisar".
---

# Qualidade de código — Contab Admin

Esta skill transforma "boas práticas de mercado" em regras **deste** projeto. Ela não substitui
o [AGENTS.md](../../../AGENTS.md) (como construir) nem o [regras.md](../../../regras.md) (o que o
sistema faz); se houver conflito, eles vencem e você avisa.

**Princípio que governa todos os outros: pragmatismo.** Padrão de projeto é ferramenta para um
problema concreto, não enfeite. Antes de criar interface, classe abstrata ou camada nova,
responda: *qual mudança provável isso facilita, e quem sofre se eu não fizer?* Se a resposta for
"nenhuma ainda", não crie (YAGNI). Três linhas repetidas são melhores que uma abstração errada.

## Como usar

1. **Antes de codar**, decida onde cada coisa mora (tabela abaixo) e qual padrão do catálogo
   se aplica — detalhes em [referencias/padroes.md](referencias/padroes.md).
2. **Ao escrever**, siga as regras de ouro abaixo e os exemplos SOLID deste código em
   [referencias/solid.md](referencias/solid.md).
3. **Teste** conforme a pirâmide em [referencias/testes.md](referencias/testes.md).
4. **Antes de entregar**, passe o [referencias/checklist-revisao.md](referencias/checklist-revisao.md)
   e rode Pint + Pest (inclui `tests/Arch`, que confere parte destas regras automaticamente).

## Onde cada coisa mora

| Preciso de… | Vai em | Padrão | Exemplo real |
|---|---|---|---|
| Um caso de uso (criar, aprovar, enviar…) | `app/Actions/<Modulo>/` | Command / Use Case | `Admissao/ConcluirAdmissao` |
| Uma regra de negócio pura (cálculo, validação, decisão) | `app/Domain/<Modulo>/` | Domain Service / Policy object | `Desligamento/CalculadoraAvisoPrevio` |
| Um valor com regra própria (mês, chave, CPF) | `app/Domain/` | Value Object (imutável) | `Competencia`, `NotasFiscais/ChaveAcesso` |
| Uma consulta agregada para tela + API | `app/Domain/Consultas/` | Query Object | `ConsultaEmpresasDevendoDocumentacao` |
| Situações e transições | `app/Enums/` | State (no Enum) | `SituacaoContrato::podeTransicionarPara()` |
| Lista fixa com comportamento | `app/Enums/` | Enum rico | `DocumentoAdmissao`, `GrupoDocumentoFuncionario` |
| Serviço externo (IA, SEFAZ, PDF, banco) | interface em `app/Contracts/`, implementação em `app/Integrations/<Fornecedor>/` | Adapter + Dependency Inversion | `LeitorDadosDeDocumento` → `LeitorDadosDeDocumentoClaude` |
| Várias formas de fazer a mesma coisa | interface + uma classe por forma | Strategy | leitores por modelo de documento fiscal (AGENTS.md 5.3) |
| Tela | `app/Filament/<Painel>/` (compartilhada em `Compartilhado/`) | Humble Object (tela fina) | `TabelaDocumentosFuncionario` |
| Trabalho pesado / assíncrono | `app/Jobs/` | Command na fila | `ProcessarArquivoXmlJob` |

## Regras de ouro

1. **Tela fina.** Filament/Livewire/Controller só validam, autorizam e chamam a Action. Nada de
   regra, `DB::transaction` ou query de negócio na tela (`tests/Arch` confere).
2. **Uma Action, um caso de uso.** Recebe o `User` e dados já filtrados; autoriza por permissão
   (`$user->can(Permissao::X->value)`), nunca por nome de atribuição; falha com
   `RegraDeNegocioException::se(...)` e mensagem para o usuário em português.
3. **Domínio sem framework de tela.** `app/Domain` não conhece Filament, Request nem usuário
   logado — recebe tudo por parâmetro. Assim é testável em teste unitário, sem banco.
4. **Dependa de contratos nas fronteiras.** Serviço externo sempre atrás de `app/Contracts`,
   ligado no `AppServiceProvider`, injetado no construtor. Dentro do domínio, use classes
   concretas: interface com uma única implementação e sem fronteira externa é cerimônia.
5. **Sem string mágica.** Situação, tipo, motivo e permissão são Enums. Dinheiro é `decimal`
   (string), nunca `float`. Datas de regra são `CarbonImmutable`.
6. **Nomes em português do domínio** (`regras.md` §2), verbos no infinitivo para Actions
   (`SolicitarAdmissao`), substantivos para domínio (`CalculadoraRotinaMensal`). Termos do
   framework ficam em inglês (`handle`, `casts`, `booted`).
7. **Funções curtas e com guard clauses.** Retorne cedo; evite `else` depois de `return`;
   mais de ~3 níveis de indentação ou ~40 linhas pede extração de método.
8. **Imutabilidade e efeitos explícitos.** Métodos de domínio devolvem valor novo; quem grava
   é a Action, dentro de `DB::transaction` quando grava mais de uma tabela.
9. **Toda regra cita a RN.** Comentário curto `// regras.md RN-xxx` onde a regra é aplicada, e
   o teste que a cobre menciona a mesma RN. Regra que não está no `regras.md` não é inventada:
   pergunte ou registre como pendência.
10. **Comentário explica o porquê**, nunca o quê. Siga a densidade do arquivo vizinho.

## O que evitar (antipadrões comuns em Laravel)

- **Repository por cima do Eloquent** só "por padrão": Eloquent já é o repositório. Use Query
  Object quando a consulta for complexa ou reutilizada.
- **"Service" genérico** (`EmpresaService` com 30 métodos): quebre em Actions e classes de
  domínio com um propósito.
- **`app()` / facades dentro do domínio** para buscar dependências (Service Locator): injete.
- **Trait como depósito de código**: trait só para comportamento transversal de verdade
  (`PertenceAoEscritorio`).
- **Herança para reaproveitar código**: prefira composição; herança só de classes do framework.
- **Lógica em hooks do Filament** (`mutateFormDataBeforeCreate`, `afterCreate`) ou em
  observers que escondem regra de negócio. Eventos do model só para infraestrutura (ex.: hash
  do arquivo em `Documento::creating`).
- **Booleanos de controle** (`executar($x, true, false)`): crie dois métodos ou um Enum.

## Quando discordar desta skill

Se seguir uma regra deixar o código pior num caso concreto, não siga às cegas: explique o
motivo no PR/resposta e, se for recorrente, proponha a mudança nesta skill.
