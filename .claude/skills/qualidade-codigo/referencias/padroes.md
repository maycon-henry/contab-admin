# Catálogo de padrões do Contab Admin

Use um padrão quando o **problema** dele aparecer. A coluna "Quando NÃO" é tão importante quanto
a de uso.

## Padrões em uso

### Command / Use Case — Actions (`app/Actions`)
- **Problema:** a mesma operação é chamada pelo Filament, pela API futura, por jobs e comandos.
- **Forma:** uma classe por caso de uso, dependências no construtor, método público
  `executar(...)` (ou `criar`/`atualizar` quando a Action salva um cadastro).
- **Regras:** autoriza primeiro, valida depois, grava em transação, registra histórico, devolve
  o resultado (ou alertas não impeditivos como `array<string>`).
- **Quando NÃO:** leitura simples de tela (lista, filtro) não precisa de Action.

### Domain Service / Policy object (`app/Domain/<Modulo>`)
- **Problema:** regra de negócio que precisa ser testada sem banco e reutilizada.
- **Exemplos:** `CalculadoraRotinaMensal`, `SelecionadorEmpresasParaCobranca`,
  `PreenchimentoAutomatico`, `VerificadorAdmissao`.
- **Regras:** sem estado mutável, sem usuário logado, entrada e saída explícitas.

### Value Object (`app/Domain`)
- **Problema:** um valor com formato e regras próprios (competência, chave de acesso, CPF).
- **Regras:** imutável, valida na construção (`parse`), sabe se formatar, comparação por valor.
- **Quando usar:** sempre que você se pegar passando o mesmo `string` e validando em vários
  lugares.

### State — situação no Enum
- **Problema:** fluxos com situação (admissão, item da competência, desligamento).
- **Forma:** Enum com `podeTransicionarPara()`; cada transição é uma Action (AGENTS.md 5.1).
- **Nunca:** alterar a coluna de situação por formulário.

### Query Object (`app/Domain/Consultas`)
- **Problema:** consulta agregada usada pelo widget e pelo endpoint da API.
- **Regras:** agregação em SQL, devolve Builder ou DTO, a regra (ex.: "atraso", RN-625) num
  lugar só.

### Adapter + Strategy — integrações (`app/Contracts` + `app/Integrations`)
- **Problema:** fornecedor externo trocável e simulável em teste.
- **Forma:** interface pequena no idioma do domínio; uma implementação por fornecedor/formato.
- **Regras:** a implementação traduz erros do fornecedor em exceções que o domínio entende;
  nunca registra dado pessoal em log (AGENTS.md 7).

### DTO (`app/Data`) — pendência (AGENTS.md 12)
- **Problema:** arrays soltos entre tela e Action.
- **Forma:** classe `readonly` com construtor tipado e `fromArray()`. Ao criar Action nova com
  muitos campos, prefira DTO.

### Null Object
- **Exemplo:** leitor de documentos sem chave → `disponivel() === false` e o fluxo segue manual.
- **Uso:** quando a ausência de um recurso é normal e não deve espalhar `if`.

### Observer / Eventos
- **Uso aqui:** infraestrutura e registros transversais (log de acessos, hash do arquivo).
- **Quando NÃO:** regra de negócio — ela fica explícita na Action, onde quem lê enxerga.

### Notification
- Envio de e-mail/push sempre por `Notification` do Laravel, nunca `Mail::send` na regra.

## Padrões que este projeto evita

| Padrão | Por quê |
|---|---|
| Repository genérico sobre Eloquent | duplica o Eloquent e esconde scopes de tenant; use Query Object |
| Service "faz-tudo" | viola SRP; quebre em Actions e domínio |
| Singleton manual | o container do Laravel já gerencia o ciclo de vida |
| Service Locator (`app()` no domínio) | esconde dependências e dificulta teste; injete no construtor |
| Herança entre Actions | acopla casos de uso; extraia o comum para domínio ou composição |
| Interface para tudo | cerimônia sem ganho; só em fronteiras externas ou com 2+ implementações reais |
