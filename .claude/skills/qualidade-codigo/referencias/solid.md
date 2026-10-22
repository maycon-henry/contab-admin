# SOLID aplicado ao Contab Admin

Cada princípio com o que ele significa aqui, um exemplo que já segue e o sinal de alerta.

## S — Responsabilidade única

*Uma classe tem um motivo para mudar.*

- **Segue:** `CalculadoraAvisoPrevio` só calcula aviso prévio (RN-542); `ConcluirAdmissao` só
  conclui; a tela da admissão só monta o formulário e chama a Action.
- **Alerta:** classe que muda por motivos de áreas diferentes (ex.: regra fiscal *e* layout de
  e-mail); método com "e" no nome (`validarESalvarENotificar`); Action com mais de um caso de uso
  escondido atrás de flags.
- **Como corrigir:** extraia a regra para `app/Domain`, a notificação para uma `Notification`, a
  formatação para a tela.

## O — Aberto/fechado

*Acrescentar comportamento sem editar o que já funciona.*

- **Segue:** novo documento fiscal (CT-e) é uma nova implementação de leitor, sem mexer no fluxo
  de importação (AGENTS.md 5.3); nova atribuição de colaborador é um caso no Enum `Atribuicao`,
  sem mudar nenhuma checagem — o código verifica **permissões**, não atribuições.
- **Alerta:** `match`/`switch` sobre tipo repetido em vários arquivos — cada tipo novo exige
  caçar todos. Concentre o `match` num único lugar (de preferência um método do próprio Enum).

## L — Substituição de Liskov

*Qualquer implementação de um contrato serve onde o contrato é esperado.*

- **Segue:** `LeitorDadosDeDocumento::ler()` documenta o que devolve (campo => valor ou null);
  sem chave da API, `disponivel()` é `false` e ninguém precisa saber qual implementação está ativa.
- **Alerta:** implementação que lança exceção num método que o contrato diz sempre funcionar,
  que exige chamada prévia não documentada, ou que devolve formato diferente. Documente no
  PHPDoc da interface as pré-condições, o retorno e as exceções.

## I — Segregação de interfaces

*Contratos pequenos, do tamanho de quem os usa.*

- **Segue:** `GeradorDanfe` e `LeitorDadosDeDocumento` têm um ou dois métodos.
- **Alerta:** interface "Cliente da Receita" com 15 métodos, em que cada consumidor usa dois.
  Divida pelo consumidor (ex.: `ConsultaCnpj`, `ConsultaSituacaoNota`).

## D — Inversão de dependência

*Regra de negócio depende de abstrações nas fronteiras; detalhes dependem delas.*

- **Segue:** `PreencherComDocumento` recebe `LeitorDadosDeDocumento` no construtor; o
  `AppServiceProvider` escolhe a implementação; os testes trocam por um falso com
  `app()->instance(...)`. `tests/Arch` garante que `App\Integrations` só é usado no provider.
- **Alerta:** `new ClienteHttpX()` dentro de Action ou domínio; `Http::post()` direto numa
  Action (deveria estar numa implementação de contrato).
- **Limite:** não crie interface para classe de domínio interna com uma implementação só —
  isso é inversão sem ganho. A fronteira que importa é o mundo externo (IA, SEFAZ, banco,
  e-mail, armazenamento).

## Princípios vizinhos que também valem

- **DRY com bom senso:** duplicar conhecimento (a mesma regra em dois lugares) é grave;
  duplicar código parecido que muda por motivos diferentes não é.
- **KISS:** a solução mais simples que cumpre a RN e os testes.
- **YAGNI:** nada de parâmetro, configuração ou camada "para o futuro".
- **Lei de Deméter:** evite `$a->b->c->d()` em regra de negócio; peça ao objeto o que precisa
  (`$contrato->documentoDoChecklist($tipo)`).
- **Tell, don't ask:** prefira `$situacao->podeTransicionarPara($nova)` a comparar a situação
  com listas espalhadas pelo código.
- **Composição sobre herança.**
- **Fail fast:** valide na entrada da Action, com mensagem clara, antes de gravar qualquer coisa.
