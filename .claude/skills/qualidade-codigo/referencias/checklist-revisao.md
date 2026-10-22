# Checklist de revisão

Use na revisão de qualquer diff. Classifique cada achado:

- **Bloqueante** — bug, falha de segurança/isolamento de tenant, regra de negócio errada ou
  inventada, perda de dado, quebra de contrato da API. Não entra assim.
- **Importante** — viola a arquitetura ou SOLID de forma que vai custar caro depois, falta
  teste exigido.
- **Sugestão** — legibilidade, nome, pequena simplificação.

Para cada achado: arquivo:linha, o problema, **o cenário concreto** em que dá errado e a correção
sugerida. Sem cenário concreto, não é bloqueante.

## 1. Regra de negócio
- [ ] Toda regra aplicada existe no `regras.md` e está citada (`// regras.md RN-xxx`)?
- [ ] Nada foi inventado; o que é [A DEFINIR] não foi implementado sem confirmação?
- [ ] Regra nova foi registrada no `regras.md` antes do código?

## 2. Segurança e isolamento
- [ ] Toda consulta respeita o escritório e, para usuário da empresa, a empresa (scopes ou filtro explícito com `withoutGlobalScopes()`)?
- [ ] A Action autoriza por **permissão** (nunca por nome de atribuição) e confere o vínculo (não confia no ID da URL)?
- [ ] Documento respeita `visivel_empresa` (RN-904); dado sensível respeita RN-570?
- [ ] Nada de CPF, CNPJ completo, senha ou token em log?
- [ ] XML/ZIP/upload validados conforme AGENTS.md 5.3?

## 3. Arquitetura (AGENTS.md 3)
- [ ] Tela fina: sem regra, transação ou query de negócio no Filament/Livewire/Controller?
- [ ] Regra pura em `app/Domain`, sem Filament/Request/usuário logado?
- [ ] Integração externa atrás de `app/Contracts`, injetada?
- [ ] Situação alterada só por Action de transição?
- [ ] Existe caminho para a API v1 (a regra não ficou presa na tela)?

## 4. SOLID e design ([solid.md](solid.md), [padroes.md](padroes.md))
- [ ] Cada classe/método com uma responsabilidade?
- [ ] Sem `match` sobre o mesmo tipo repetido em vários arquivos?
- [ ] Sem abstração especulativa (interface/classe base sem segundo uso real)?
- [ ] Sem flags booleanas controlando o comportamento de um método?
- [ ] Dependências injetadas, não buscadas com `app()` no domínio?

## 5. Clean code
- [ ] Nomes no vocabulário do `regras.md`; nada de `$data2`, `$aux`, `$tmp`?
- [ ] Guard clauses, pouca indentação, métodos curtos?
- [ ] Sem string mágica para situação/tipo (Enum)? Dinheiro sem `float`?
- [ ] Comentários explicam o porquê e seguem a densidade do arquivo?
- [ ] Sem código morto, `dd()`, `dump()`, TODO sem dono?

## 6. Banco
- [ ] Mudança por migration nova (nunca editando migration da `main`)?
- [ ] FK com constraint e índice; colunas em português, snake_case?
- [ ] Dados fiscais/funcionários nunca apagados fisicamente?
- [ ] Consultas sem N+1 (`with()`), agregações no SQL?

## 7. Testes ([testes.md](testes.md))
- [ ] Regra de domínio nova tem teste **unitário** com os limites?
- [ ] Action nova tem feature cobrindo sucesso, validação e a matriz de autorização?
- [ ] Testes citam a RN; nenhum teste desligado ou enfraquecido para passar?
- [ ] `pint`, `pest` (inclui `tests/Arch`) passando?

## 8. Interface (AGENTS.md 9)
- [ ] Textos em português do Brasil, com acentuação correta?
- [ ] Ação de linha nova com `->icon()` e descrição em `DescricaoAcoes`?
- [ ] Links entre painéis informam `panel:`?
