# AGENTS.md — Regras de Desenvolvimento

Este arquivo orienta qualquer agente (IA ou humano) que trabalhe neste repositório.
Regras de **negócio** ficam em [regras.md](regras.md). Em caso de conflito, `regras.md` define *o que* o sistema faz; este arquivo define *como* ele é construído.

---

## 1. Visão geral

- **Produto:** painel administrativo para contabilidade (escritório contábil e suas empresas clientes).
- **Fase 1:** aplicação web.
- **Fase 2 (futuro):** aplicativos móveis consumindo a mesma API.
- **Princípio central:** *API-first*. Toda regra de negócio deve ser acessível pela API; a interface web é apenas mais um cliente. Nenhuma regra de negócio pode existir só na camada web.

## 2. Stack

| Camada | Tecnologia |
|---|---|
| Linguagem | PHP 8.3+ |
| Framework | Laravel (última versão estável) |
| Banco de dados | PostgreSQL |
| Cache / filas / sessão | Driver `database` no início; Redis quando houver necessidade de escala |
| Autenticação | Sessão do Laravel na web (Filament); Laravel Sanctum com tokens para a API/apps |
| Painel administrativo | Filament (última versão estável) |
| Telas customizadas | Livewire + Blade + Tailwind CSS |
| Testes | Pest |
| Qualidade | Laravel Pint, Larastan (PHPStan nível 8+) |
| Containers | Docker + Docker Compose (container `app` e container `db`) |
| IA (leitura de documentos) | API da Anthropic (Claude) com o SDK `anthropic-ai/sdk`, atrás de `App\Contracts\LeitorDadosDeDocumento` (`docs/adr/0002-leitura-de-documentos-por-ia.md`) |

> Mudanças de stack exigem registro em `docs/adr/` (Architecture Decision Record).

### 2.1 Docker

A aplicação roda em **dois containers**, definidos em `docker-compose.yml` na raiz. No ambiente local há um terceiro, só para desenvolvimento:

| Serviço | Ambiente | Conteúdo | Observações |
|---|---|---|---|
| `app` | todos | PHP 8.3 (FPM) + Nginx, Composer, Node (build dos assets) e extensões necessárias (`pdo_pgsql`, `intl`, `zip`, `gd`, `bcmath`, `soap` — exigida pela biblioteca do DANFE) | Código montado como volume em desenvolvimento. Processos internos (PHP-FPM, Nginx, worker de fila e agendador) gerenciados por Supervisor. |
| `db` | todos | PostgreSQL (imagem oficial, versão fixada na tag) | Dados em volume nomeado (`pgdata`), para não se perderem ao recriar o container. |
| `mailhog` | **somente local** | MailHog (`mailhog/mailhog:v1.0.1`) | Captura todos os e-mails enviados pela aplicação (convites, recuperação de senha, notificações) para inspeção, sem entregá-los a ninguém. |

#### MailHog (somente local)

- O serviço `mailhog` tem `profiles: ["local"]` no `docker-compose.yml`, então **só sobe quando o perfil `local` está ativo**. No `.env` local, `COMPOSE_PROFILES=local` faz o `docker compose up -d` subir o MailHog automaticamente; em qualquer outro ambiente essa variável não existe e o serviço não sobe.
- Portas publicadas **apenas em `127.0.0.1`**, nunca em todas as interfaces:
  ```yaml
  mailhog:
    image: mailhog/mailhog:v1.0.1
    profiles: ["local"]
    ports:
      - "127.0.0.1:8025:8025"   # interface web: http://localhost:8025
      - "127.0.0.1:1025:1025"   # SMTP (só necessário para acesso fora da rede do Docker)
  ```
- Configuração de e-mail no `.env` local (o `app` acessa pelo nome do serviço, dentro da rede do Docker):
  ```dotenv
  MAIL_MAILER=smtp
  MAIL_HOST=mailhog
  MAIL_PORT=1025
  MAIL_USERNAME=null
  MAIL_PASSWORD=null
  MAIL_ENCRYPTION=null
  MAIL_FROM_ADDRESS="nao-responda@contab.test"
  ```
- O `.env.example` traz essa configuração com um comentário indicando que é exclusiva do ambiente local. Em produção, `MAIL_*` aponta para o provedor de e-mail real.
- Testes automatizados (Pest) **não** usam o MailHog: usam `Mail::fake()` / `Notification::fake()` e verificam o envio por asserções. O MailHog serve para conferir visualmente os e-mails durante o desenvolvimento.
- Alternativa compatível: o **Mailpit** (`axllent/mailpit`) é o sucessor mantido do MailHog, com as mesmas portas (1025/8025). A troca é só de imagem, se o MailHog apresentar problemas.

- Imagem do `app` construída a partir de `docker/app/Dockerfile`; configurações de Nginx, PHP e Supervisor em `docker/app/`.
- A aplicação acessa o banco pelo nome do serviço: `DB_HOST=db`, `DB_CONNECTION=pgsql`.
- Versões de imagem sempre fixadas (ex.: `postgres:17`), nunca `latest`.
- Credenciais vêm do `.env`; o `docker-compose.yml` não contém senhas.
- Todo comando PHP/Artisan/Composer/NPM roda **dentro** do container `app`:
  ```bash
  docker compose up -d
  docker compose exec app php artisan migrate
  docker compose exec app composer install
  docker compose exec app npm run build
  ```
- Endereços locais: aplicação em `http://localhost` (porta definida no `.env`), e-mails capturados em `http://localhost:8025`.
- Testes usam um banco PostgreSQL separado (`contab_testes`) no mesmo container `db`, criado por script de inicialização em `docker/db/init/`. Não usar SQLite nos testes, para não divergir do PostgreSQL.
- Novos serviços (Redis etc.) só entram com justificativa e registro em ADR. Serviços de apoio ao desenvolvimento seguem o modelo do MailHog: perfil `local` e portas apenas em `127.0.0.1`.
- `vendor/` e `node_modules/` ficam em volumes nomeados (mais rápido no Docker Desktop do Windows). Para inspecionar o código de pacotes, use `docker compose exec app ...`.
- **Usuário dos processos (somente desenvolvimento):** no Docker Desktop (Windows) os arquivos do volume montado pertencem a root; por isso PHP-FPM (`-R`), fila e agendador rodam como root no container de desenvolvimento (`docker/app/php-fpm-pool.conf`). A imagem de **produção** deve rodar como usuário sem privilégios.

#### Primeira execução

```bash
cp .env.example .env                      # já traz Postgres, MailHog e COMPOSE_PROFILES=local
docker compose up -d --build
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate:fresh --seed   # inclui o DesenvolvimentoSeeder (seção 5.0)
docker compose exec app npm install && docker compose exec app npm run build
docker compose exec app php artisan filament:assets
```

- Acesso: `http://localhost:8000` · e-mails: `http://localhost:8025`.
- Ao mudar código de jobs/comandos, reinicie a fila: `docker compose exec app supervisorctl restart queue scheduler`.

### 2.2 Homologação no Render

- Ambiente de testes no Render, definido em `render.yaml` e na imagem `docker/render/Dockerfile` (decisão e limitações em `docs/adr/0001-hospedagem-render.md`).
- **Não há deploy automático.** Deploy só pelo workflow manual **Actions → "Deploy (Render)" → Run workflow** na `main`, que roda Pint e Pest antes de chamar o Deploy Hook.
- Plano gratuito: arquivos enviados são perdidos a cada deploy e o banco expira em 30 dias — **nunca usar com dados reais**.

## 3. Arquitetura

### 3.1 Camadas

```
Filament (Resources/Pages) · Livewire · Controllers API
        │  só validam entrada, autorizam (Policy) e formatam saída
        ▼
Actions (app/Actions)           ← um caso de uso por classe, método executar() (criar()/atualizar() nas que salvam cadastro)
        │
        ▼
Domínio (Models, Enums, Value Objects, Services de domínio)
        │
        ▼
Infra (Eloquent, filas, storage, integrações externas)
```

- **Camada de interface é fina.** Filament Resources, componentes Livewire e controllers da API não contêm regra de negócio nem query complexa. Chamam uma Action e devolvem resposta.
- **Filament não é dono da regra.** Em Resources/Pages, sobrescrever a criação/edição (`handleRecordCreation`, `handleRecordUpdate`, `->action(...)`) para delegar à Action correspondente. Nada de lógica de negócio em hooks como `mutateFormDataBeforeCreate` ou `afterCreate`.
- **Actions** concentram os casos de uso (`CriarEmpresaCliente`, `GerarObrigacoesDaCompetencia`...). São reutilizadas pelo Filament, pela API, por jobs e comandos Artisan — é isso que garante que os apps futuros terão exatamente as mesmas regras da web.
- **DTOs** (`app/Data`) transportam dados entre a interface e as Actions. Actions não recebem `Request` nem dados de formulário do Filament crus.
- **Integrações externas** (Receita, SEFAZ, bancos, e-mail) ficam atrás de interfaces em `app/Contracts` com implementações em `app/Integrations`, para poderem ser trocadas e simuladas em teste.

### 3.2 Organização por módulo

Agrupar por domínio quando o módulo crescer:

```
app/
  Actions/<Modulo>/
  Data/<Modulo>/
  Enums/
  Filament/
    Escritorio/          ← painel da equipe do escritório (/admin)
      Resources/<Modulo>/
      Pages/
      Widgets/
    Empresa/             ← contexto de uma empresa selecionada (/admin/empresa/{empresa})
      Resources/<Modulo>/
      Pages/
    Compartilhado/       ← formulários, tabelas e ações usados em mais de um painel
    Portal/              ← portal da empresa cliente (/portal)
      Resources/<Modulo>/
      Pages/
      Widgets/
  Livewire/<Modulo>/
  Http/
    Controllers/Api/V1/<Modulo>/
    Requests/<Modulo>/
    Resources/V1/<Modulo>/
  Models/
  Policies/
```

### 3.3 Rotas

- Três painéis Filament, cada um com seu Panel Provider em `app/Providers/Filament/`:
  - **`escritorio`** (`/admin`) — equipe do escritório: trabalho que envolve todas as empresas (início, filas de avaliação e de admissões, cadastro de empresas, equipe, relatórios, configurações). A tela "Área de trabalho" (competência da empresa no painel do escritório) foi removida da interface; competências e itens continuam no domínio (portal, fila de avaliação, cobrança e dashboard);
  - **`empresa`** (`/admin/empresa/{empresa}`) — equipe do escritório **dentro de uma empresa selecionada**: o menu lateral é só dessa empresa (Página da Empresa — ações pendentes, `PendenciasDaEmpresa` —, Notas Fiscais — entrada e saída numa lista só, com abas Todas/Entrada/Saída —, Documentos — documentos do mês; o que o escritório envia já fica aceito —, Funcionários, Cadastro da Empresa, Despesas) e o seletor no topo troca de empresa. Telas com dados de uma única empresa ficam aqui, nunca no menu geral;
  - **`portal`** (`/portal/{empresa}`) — usuários das empresas clientes (`regras.md` seção 5.5). Não há menu "Admissões": admissão e cadastro do funcionário são acessados pela tela de Funcionários (Admitir novo, Visualizar cadastro, Atualizar cadastro).
- Links entre painéis sempre informam o painel: `Resource::getUrl(..., panel: 'escritorio')` (ou `'empresa'`, com `tenant:`). Sem isso, o Filament monta a rota no painel atual e a página quebra.
- O acesso a cada painel é controlado por `canAccessPanel()` no model `User`, conforme o tipo do usuário. Um usuário da empresa nunca acessa `/admin`, e vice-versa.
- Os dois painéis chamam **as mesmas Actions**; a diferença entre eles é só de interface e de quais campos/ações cada um expõe. Ex.: a empresa usa `SolicitarAdmissao` pelo portal; o colaborador usa `ConcluirAdmissao` pelo painel do escritório.
- `routes/web.php` — telas Livewire/Blade fora do painel, se houver.
- `routes/api.php` — API REST versionada em `/api/v1/...` (Sanctum).
- Cada funcionalidade nova deve ter seu endpoint equivalente na API v1 (ou um item explícito no backlog justificando a ausência).

### 3.4 Multi-tenancy no Filament

- **Painel do escritório:** como cada usuário pertence a um único escritório (`regras.md` RN-201), **não** se usa a tenancy do Filament (que exigiria o tenant na URL e um seletor). O isolamento é feito pelo trait `App\Models\Concerns\PertenceAoEscritorio`: *global scope* por `escritorio_id` do usuário autenticado e preenchimento automático na criação.
- **Portal da empresa:** usa a tenancy nativa do Filament com `Empresa` como tenant (`/portal/{empresa}/...`), o que dá o seletor de empresa para quem tem mais de uma (RN-241). Resources do portal declaram `$tenantRelationshipName` com o nome da relação em `Empresa` (`itensCompetencia`, `notasFiscais`, `contratos`...).
- Sem usuário autenticado (jobs, comandos, seeders) os scopes não filtram: o código chamador usa IDs explícitos e `withoutGlobalScopes()` quando precisa.
- O tenant é resolvido também na API (pelo usuário autenticado) e aplicado pelo mesmo *global scope*, para que o isolamento não dependa do Filament.
- **Segundo nível de isolamento — empresa:** usuários da empresa só acessam as empresas a que estão vinculados (tabela pivô `empresa_usuario`). Um *global scope* por empresa é aplicado automaticamente para esse tipo de usuário em todos os models que pertencem a uma empresa (funcionários, contratos, competências, itens, documentos), tanto no portal quanto na API. As Policies verificam o vínculo novamente — nunca confiar apenas no ID da empresa vindo da URL.
- No portal, a empresa selecionada (RN-241) também faz parte da URL, como na área de trabalho (seção 3.5).
- Documentos consultados pelo usuário da empresa respeitam a visibilidade (`regras.md` RN-904) também no scope/Policy, não apenas na tela.

### 3.5 Área de trabalho (empresa + competência)

- A empresa e a competência selecionadas (`regras.md` seção 9) fazem parte da **URL**, não da sessão: ex. `/admin/empresas/{empresa}/competencias/2026-09`. Assim, links podem ser compartilhados, abas diferentes podem trabalhar meses diferentes e a API segue o mesmo formato (`/api/v1/empresas/{empresa}/competencias/2026-09/itens`).
- A sessão (ou preferência do usuário) pode apenas lembrar a última competência usada, nunca ser a fonte da verdade.
- Competência é um Value Object (`App\Domain\Competencia`) com ano e mês: parse/formatação (`2026-09` na URL e na API, `09/2026` na tela), `anterior()`, `seguinte()`, `primeiroDia()`, `ultimoDia()`, `ehFutura()`. Nunca manipular competência como string solta.
- No banco, competência é uma coluna `date` com o primeiro dia do mês (`2026-09-01`), com índice único em `(empresa_id, competencia)` na tabela de competências.
- **Catálogo e rotina** (`regras.md` seções 9.2 e 9.3):
  - `tipos_documento` — catálogo **por escritório** (`escritorio_id`), com as configurações de RN-610. Nunca há tipos "globais" da plataforma em uso direto: o catálogo sugerido (RN-613) é **copiado** para o escritório, que passa a ser dono dos registros.
  - `tipo_documento_aplicacoes` — regras de aplicação (todas / por regime / empresas selecionadas).
  - `empresa_rotina_ajustes` — dispensas e alterações de prazo/obrigatoriedade por empresa (RN-616).
  - A rotina vigente de uma empresa é calculada por uma única classe de domínio (`CalculadoraRotinaMensal`), usada pela geração mensal, pela tela da rotina (RN-618) e pela API — nunca recalculada em telas.
  - Cada item da competência guarda uma **cópia das configurações** do tipo no momento da criação (prazo, obrigatoriedade, exige validade), para que alterações no catálogo não mudem competências já criadas (RN-612).
- **Avaliação** (`regras.md` seção 9.5): tabela `avaliacoes_item` (uma linha por avaliação, nunca atualizada); os motivos de recusa são um Enum. O item aponta para a avaliação vigente; o histórico completo fica preservado (RN-627, RN-655).
- A geração mensal (RN-620) é um comando Artisan agendado no Scheduler, **idempotente** (pode rodar mais de uma vez sem duplicar), e a mesma Action atende à criação sob demanda (RN-621).
- Competência fechada (RN-632) é bloqueada nas Policies — qualquer escrita em itens/documentos da competência verifica a situação, tanto no Filament quanto na API.

### 3.6 Rotinas agendadas (cron)

- Existe **uma única entrada de agendamento** no container `app`: o Supervisor mantém `php artisan schedule:work` rodando (equivale ao cron `* * * * * php artisan schedule:run`). Nenhuma rotina é cadastrada diretamente no crontab do sistema operacional; todas ficam no Scheduler do Laravel (`routes/console.php`), versionadas no código.
- Toda rotina agendada é um **comando Artisan** que só seleciona o que precisa ser feito e **despacha jobs na fila** (um por empresa ou por registro). O trabalho pesado nunca roda dentro do comando.
- Agendamentos sempre com fuso explícito e proteção contra sobreposição:
  ```php
  Schedule::command('cobranca:documentacao-diaria')
      ->dailyAt('08:00')
      ->timezone('America/Sao_Paulo')
      ->withoutOverlapping()
      ->onOneServer();
  ```
- Toda rotina é **idempotente**: rodar duas vezes no mesmo dia não duplica efeito. Na cobrança diária (`regras.md` seção 16.1), isso é garantido por índice único em `cobrancas_documentacao (empresa_id, data_referencia, tipo)` — o job tenta registrar a cobrança antes de enviar e desiste se já existir (RN-1320).
- Todo comando aceita `--data=AAAA-MM-DD` (simula outro dia, para reprocessar ou testar), `--empresa=` (restringe a uma empresa) e `--simular` (lista o que seria feito, sem enviar nem gravar).
- Regras de elegibilidade (data de corte RN-1311, competência de referência RN-1310, itens cobráveis RN-1313, suspensões RN-1317) ficam em classes de domínio testáveis isoladamente, ex. `SelecionadorEmpresasParaCobranca`, e não no comando.
- O envio usa **Notifications** do Laravel (canal `mail` agora; canal de push quando houver app), nunca `Mail::send` direto na regra de negócio. Localmente, os e-mails caem no MailHog (seção 2.1).
- Testes usam `Carbon::setTestNow()` para cobrir: antes da data de corte, no dia do corte, data de corte própria da empresa, empresa suspensa, empresa sem pendências, empresa sem usuários, dupla execução no mesmo dia e virada de mês.
- Rotinas agendadas atuais:

  | Comando | Quando | Regra |
  |---|---|---|
  | `competencias:gerar-mensal` | dia 1, 00:05 | `regras.md` RN-620 |
  | `cobranca:documentacao-diaria` | diariamente, 08:00 | `regras.md` seção 16.1 |

## 4. Padrões da API (preparação para apps)

- REST, JSON, versionada (`/api/v1`). Quebra de contrato ⇒ nova versão.
- Respostas sempre via **API Resources** (`JsonResource`); nunca retornar Model cru.
- Formato de erro padronizado:
  ```json
  { "message": "Descrição legível", "errors": { "campo": ["mensagem"] }, "code": "CODIGO_ERRO" }
  ```
- Paginação padrão do Laravel (`data`, `links`, `meta`) em toda listagem.
- Datas em ISO 8601 com timezone; valores monetários como **string decimal** (`"1234.56"`) ou inteiro em centavos — nunca `float`.
- Identificadores públicos: **UUID/ULID** nas rotas da API (não expor IDs incrementais).
- Rate limiting configurado por rota/grupo.
- Documentação da API gerada automaticamente (ex.: Scribe ou Scramble) e mantida atualizada.
- Tokens Sanctum com *abilities* e nome do dispositivo, para permitir revogação por aparelho.

## 5. Banco de dados

- Toda alteração via **migration**; nunca editar migration já mergeada na `main`.
- Nomes de tabelas e colunas em **português, snake_case** (ex.: `empresas_clientes`, `data_vencimento`), seguindo o vocabulário de `regras.md`. Código (classes, métodos) também em português para o domínio; termos técnicos do framework permanecem em inglês.
- Chaves estrangeiras com constraint e índice.
- Valores monetários: `decimal(15,2)` (ou `bigint` em centavos — definir uma vez e manter).
- `softDeletes` em entidades de negócio (empresas, documentos, lançamentos). Dados fiscais/contábeis **não são apagados fisicamente**.
- Multi-tenant: toda tabela de negócio carrega `escritorio_id` e é filtrada por *global scope* (ver `regras.md`). Testes devem cobrir vazamento entre tenants.
- Seeders para dados de referência (CFOP, CNAE, CBO, municípios IBGE, feriados) separados de factories de teste.
- Tabelas do eSocial usadas no domínio (tabela 1 — categorias, 7 — tipos de dependente, 8 — classificação tributária, 19 — motivos de desligamento) ficam em tabelas de referência com o **código oficial** e vigência, nunca como texto livre. Campos de domínio que espelham o eSocial guardam o código oficial, para viabilizar integração futura sem conversão.
- Dados com vigência (regime tributário, salário/cargo/jornada, honorários) ficam em tabelas de histórico com `vigencia_inicio`/`vigencia_fim`; nunca sobrescrever o valor anterior.

### 5.0 Dados iniciais de desenvolvimento

O `DesenvolvimentoSeeder` cria um ambiente pronto para testar o sistema manualmente:

| Registro | Dados |
|---|---|
| Escritório | "Escritório Demo", CNPJ `11.222.333/0001-81`, situação `ativo` |
| Administrador (titular) | `admin@contab.test` — senha `123` |
| Colaboradores | `colaborador@contab.test` — senha `123`, com as atribuições operacionais (empresas, usuários do portal, admissões, documentos, notas); `daniela@contab.test` com **todas** as atribuições; `eduardo@contab.test` (convite pendente) com financeiro e despesas |
| Empresa cliente | "Empresa Demo Ltda", CNPJ `22.333.444/0001-81`, Simples Nacional, situação `ativa`, com dados de empregador e estabelecimento matriz preenchidos (para permitir admissões — `regras.md` RN-414) |
| Usuário da empresa | `empresa@contab.test` — senha `123`, vinculado à Empresa Demo |

Regras:

- **Somente nos ambientes `local` e `testing`.** O seeder verifica `app()->environment(['local', 'testing'])` e, em qualquer outro ambiente, **interrompe com erro** em vez de seguir silenciosamente. Ele nunca é chamado pelo `DatabaseSeeder` de produção.
- A senha `123` descumpre a política de senha (`regras.md` RN-310/RN-311) de propósito, por conveniência de desenvolvimento. Por isso o seeder grava o hash diretamente (`Hash::make('123')`), sem passar pela validação, e a política continua valendo para qualquer senha criada pelo sistema.
- Usuários do seeder são criados com convite já aceito e e-mail verificado.
- Os dados (CNPJs, nomes) são fictícios; CNPJs com dígitos verificadores válidos para passar nas validações.
- Execução: `docker compose exec app php artisan db:seed --class=DesenvolvimentoSeeder` (ou `migrate:fresh --seed` em ambiente local, que o chama).

### 5.1 Fluxos com situação (admissão, desligamento e similares)

- Situações são Enums (ex.: `App\Enums\SituacaoContrato`) com as transições permitidas definidas no próprio Enum (ex.: `podeTransicionarPara()`).
- Cada transição é uma Action própria. Na admissão (`regras.md` seção 8.3): `IniciarAdmissao`, `SolicitarAdmissao`, `AssumirAdmissao`, `TransferirResponsavelAdmissao`, `AvaliarDocumentoAdmissao`, `DevolverAdmissao`, `ConcluirAdmissao`, `CancelarAdmissao`. No desligamento: `IniciarDesligamento`, `ConcluirDesligamento`, `CancelarDesligamento`. Nos itens da competência (`regras.md` seções 9.4 a 9.7): `EnviarDocumentosDoItem`, `InformarSemMovimento`, `AceitarItem`, `RecusarItem`, `ConcluirTarefa`, `DispensarItem`. Nunca alterar a coluna de situação diretamente por formulário.
- Cada Action de transição valida **quem** pode executá-la (ex.: a empresa ou o escritório solicita; só o escritório assume, devolve, cancela após a solicitação e conclui) via Policy, independentemente do painel de onde foi chamada.
- As validações de conclusão (dados obrigatórios, checklist de documentos — `regras.md` RN-528, RN-547) ficam na Action de conclusão e devolvem a lista de pendências, para a tela e a API exibirem o mesmo resultado.
- Prazos legais (RN-522, RN-529, RN-544) são calculados por classes de domínio testáveis isoladamente, nunca na tela.

### 5.2 Documentos e checklist

- Um único model `Documento`, polimórfico (`documentavel_type`/`documentavel_id`), serve empresas, funcionários e contratos.
- Os tipos de documento e as regras de obrigatoriedade do checklist (sempre / conforme o caso / opcional) ficam em tabela de referência, com a condição avaliada por código de domínio — não espalhar `if`s de checklist pelas telas.
- Substituir um documento cria nova versão; a anterior é mantida (RN-562).
- Documentos do funcionário (`regras.md` RN-568): `documentos.funcionario_id` junta os documentos de todos os contratos, e `hash` (SHA-256), `mime` e `tamanho` registram o arquivo. Tudo é preenchido no evento `creating` do model `Documento`, então nenhuma Action precisa se lembrar disso. O arquivo não muda de lugar depois de gravado (o caminho é estável, como o XML das notas): a organização em pastas fica no banco e no dossiê.
- Grupo e nome de cada tipo de documento do funcionário: `App\Enums\GrupoDocumentoFuncionario` (tela e dossiê usam a mesma classificação). Tela: `TabelaDocumentosFuncionario` (painéis `empresa` e `portal`); dossiê `.zip`: Action `GerarDossieFuncionario` (RN-573).

### 5.2.1 Responsáveis pela admissão e relatórios

- Os responsáveis de cada etapa (`regras.md` RN-550) são **colunas na própria tabela de contratos**, não apenas linhas de histórico: `solicitado_por_id`/`solicitado_em`, `atendimento_iniciado_por_id`/`atendimento_iniciado_em`, `admitido_por_id`/`admitido_em`. Isso deixa o relatório simples e rápido (contagem agrupada direto na tabela).
- As colunas são preenchidas **somente** pelas Actions da transição correspondente (`SolicitarAdmissao`, `AssumirAdmissao` — apenas na primeira vez —, `ConcluirAdmissao`) e nunca expostas em formulário. Depois de preenchidas, a Action se recusa a sobrescrevê-las (RN-551); teste cobrindo isso é obrigatório.
- Chaves estrangeiras para `users` sem `cascade` (usuários nunca são excluídos — RN-233).
- O histórico de transições (RN-575) continua sendo a fonte do detalhe (transferências, devoluções); a contagem de devoluções do relatório (RN-553) vem dele.
- Índices: `contratos (escritorio_id, admitido_por_id, admitido_em)` e `contratos (escritorio_id, atendimento_iniciado_por_id, atendimento_iniciado_em)`.
- `contratos.formulario_salvo_por_id`/`formulario_salvo_em` (quem salvou a última versão do formulário) é a exceção: é atualizado a cada salvamento, por `SalvarAdmissao::registrarQuemSalvou()`. `documentos.avaliado_por_id`/`avaliado_em` são preenchidos só por `AvaliarDocumentoAdmissao`.

### 5.2.2 Preenchimento automático da admissão (`regras.md` RN-579)

- Fluxo: `FileUpload` do checklist → `afterStateUpdated` → Action `PreencherComDocumento` → contrato `LeitorDadosDeDocumento` (implementação `App\Integrations\Anthropic\LeitorDadosDeDocumentoClaude`, structured outputs) → domínio `PreenchimentoAutomatico` (o que cada documento pode preencher, validação e "só campos vazios"). A Action não grava nada; devolve os campos para a tela (e, no futuro, para a API).
- Configuração: `ANTHROPIC_API_KEY` e `ANTHROPIC_MODELO` (`config/services.php`). Sem chave, `disponivel()` é `false`: não há janela de aviso nem leitura.
- Testes nunca chamam a API: troque o contrato por um leitor falso (`app()->instance(LeitorDadosDeDocumento::class, ...)`).
- O resumo da última aba vem de `App\Domain\Admissao\ResumoAdmissao`, que usa os mesmos obrigatórios de `VerificadorAdmissao` (`OBRIGATORIOS_PESSOAIS`, `OBRIGATORIOS_CONTRATO`).
- Relatórios seguem o mesmo padrão dos dashboards: classe de consulta de domínio (ex.: `RelatorioAdmissoesPorColaborador`) com agregação em SQL, usada pela página do Filament e pelo endpoint da API; acesso por permissão (`relatorios.admissoes`).

### 5.3 Notas fiscais (importação de XML)

- **Modelagem:** tabela `notas_fiscais` com os dados extraídos (`regras.md` RN-710), `competencia` (date, primeiro dia do mês), `classificacao` (Enum: `saida`, `entrada_terceiros`, `entrada_propria`), `situacao` (Enum: `autorizada`, `cancelada`) e referência ao arquivo XML original. Índice único em `(empresa_id, chave_acesso)` e índice em `(empresa_id, competencia, classificacao)` para a consulta mensal.
- **Lotes:** cada upload gera um registro `lotes_importacao_xml` (quem, quando, totais) e um registro por arquivo com o resultado (`importada`, `duplicada`, `rejeitada` + motivo). É isso que alimenta o relatório do envio (RN-703).
- **Processamento assíncrono:** o upload só armazena os arquivos e cria o lote; a leitura roda em **jobs na fila** (driver `database`, worker no container `app`), um job por arquivo. O job é idempotente pela chave de acesso — reprocessar não duplica.
- **Leitores por modelo:** interface `LeitorDocumentoFiscal` com uma implementação por tipo (`LeitorNFe`, cobrindo modelos 55 e 65, e `LeitorEventoCancelamentoNFe`); CT-e e NFS-e entram como novas implementações, sem alterar o fluxo. O leitor devolve um DTO; regras de pertencimento, classificação e competência (RN-713, RN-716, RN-720) ficam em classes de domínio separadas, testáveis sem XML.
- **Data da competência:** derivar do `dhEmi` **no offset informado no próprio XML** (RN-721). Nunca converter para UTC antes de extrair ano/mês.
- **Segurança do XML:** parse com `DOMDocument`/`SimpleXML` sem resolução de entidades externas (não usar `LIBXML_NOENT`; usar `LIBXML_NONET`), rejeitando DTD. Validar extensão e conteúdo (não confiar no MIME informado).
- **Segurança do ZIP:** limitar quantidade de arquivos, tamanho descompactado total e por arquivo (proteção contra *zip bomb*), ignorar caminhos com `..` e arquivos que não sejam `.xml`.
- **Armazenamento:** XML original gravado sem alteração em storage privado, no caminho `escritorios/{escritorio}/empresas/{empresa}/notas/{AAAA-MM}/{chave}.xml`, com hash SHA-256 registrado para garantir integridade (RN-750).
- **Download em lote** (RN-743): o `.zip` é gerado por job e disponibilizado por URL temporária, para não travar a requisição em consultas grandes.
- **DANFE** (RN-743): contrato `App\Contracts\GeradorDanfe`, implementado por `App\Integrations\Nfephp\GeradorDanfeNfephp` (pacote `nfephp-org/sped-da`, DANFE para modelo 55 e DANFCE para 65). A Action `GerarDanfe` verifica o acesso e lê o XML guardado; a rota `notas.danfe` (`/notas-fiscais/{nota}/danfe`) devolve o PDF *inline*, e o route model binding já aplica os scopes de escritório e empresa. Em testes, troque o contrato por um mock quando não precisar do PDF real.
- **Testes:** XMLs de exemplo (anonimizados) em `tests/Fixtures/xml/` cobrindo saída, entrada de terceiros, entrada própria, NFC-e, cancelamento, duplicidade, nota de outra empresa, chave inválida, nota sem protocolo e virada de mês no fuso.

## 6. Autenticação e autorização

### 6.1 Autenticação

- Landing page pública em `routes/web.php` (Blade + Tailwind), fora do painel Filament.
- Login, recuperação de senha e aceite de convite usam as páginas de autenticação do Filament, customizadas quando necessário. As regras de bloqueio (usuário inativo, escritório suspenso, convite pendente — `regras.md` RN-303) ficam numa Action compartilhada, chamada tanto pelo login web quanto pelo login da API.
- Throttling de login conforme RN-302; mensagens genéricas conforme RN-301 e RN-312.
- Senhas validadas com `Password::min(8)->letters()->numbers()->uncompromised()` (RN-310, RN-311), definido uma única vez em `Password::defaults()`.
- Sessões web armazenadas no banco (driver `database`), para permitir encerrar sessões de um usuário (RN-233, RN-313).
- Eventos de autenticação (`Login`, `Failed`, `Logout`, `PasswordReset`) registrados por listeners em uma tabela de log de acessos (RN-330).

### 6.2 Autorização (atribuições e permissões)

- Pacote **spatie/laravel-permission** com o recurso *teams* habilitado, usando `escritorio_id` como team — atribuições e permissões ficam isoladas por escritório.
- O tipo do usuário (`administrador` / `colaborador` / `usuario_empresa`) é um Enum na tabela de usuários; cada **atribuição** do colaborador (`regras.md` RN-220) é um *role* do Spatie. Futuros perfis de usuário da empresa (RN-245) seguem o mesmo mecanismo.
- Permissões do portal ficam no mesmo catálogo (`App\Enums\Permissao`), marcadas como aplicáveis a usuários da empresa. Permissões de equipe nunca podem ser atribuídas a um perfil de usuário da empresa, e vice-versa.
- Permissões nomeadas `modulo.acao` (ex.: `empresas.visualizar`, `empresas.cadastrar`) e catalogadas em um Enum (`App\Enums\Permissao`). O catálogo é sincronizado por seeder; nunca criar permissão "solta" no código.
- **O código verifica permissões, nunca o nome da atribuição.** Proibido `if ($user->hasRole('despesas'))`. Use `$user->can('despesas.gerenciar')` / Policies. É isso que permite criar novas atribuições (RN-221) sem alterar código.
- As atribuições ficam no Enum `App\Enums\Atribuicao` (valor = nome do role, rótulo, descrição e permissões; `PERMISSOES_BASICAS` entram em todas). A Action `DefinirAtribuicoes` cria/atualiza os roles em cada escritório e define as atribuições do colaborador (`syncRoles`).
- Administrador tem acesso total via `Gate::before` (retorna `true` para administrador do escritório corrente). Permissões exclusivas do administrador (RN-223) ficam marcadas no Enum `Permissao` e nunca entram numa atribuição — `DefinirAtribuicoes` as filtra.
- Autorização **sempre** por Policy/Gate, tanto no Filament (Resources usam as Policies automaticamente) quanto na API — nunca checagem manual no controller ou na tela.
- Testes de autorização cobrem, para cada recurso: administrador, colaborador com permissão, colaborador sem permissão, usuário de outro escritório e — quando o recurso pertence a uma empresa — usuário da própria empresa e usuário de **outra empresa do mesmo escritório**.

## 7. Segurança e LGPD

- Validação **sempre** por FormRequest.
- Campos sensíveis (certificados digitais, senhas de portais, dados bancários) criptografados com `encrypted` cast.
- Dados sensíveis de funcionários (`regras.md` RN-570) protegidos por permissão própria (ex.: `funcionarios.ver_dados_sensiveis`): ocultos nas telas do Filament e omitidos dos API Resources quando o usuário não tiver a permissão. Visualização e download registrados em auditoria (RN-571).
- Arquivos (documentos, certificados) em storage privado (S3 compatível), acessados por URL temporária assinada, gerada somente após checar a Policy.
- Logs não devem conter CPF, CNPJ completo, senhas ou tokens.
- Auditoria de alterações em entidades sensíveis (quem, quando, antes/depois).
- Nenhum segredo no repositório; usar `.env` e `.env.example` atualizado.

## 8. Qualidade e testes

- Toda Action nova tem teste. Todo endpoint novo tem teste de feature (sucesso, validação, autorização, isolamento de tenant).
- Regras de negócio de `regras.md` devem ter teste que as referencie (ex.: `// regras.md RN-302`).
- Antes de concluir uma tarefa, rodar:
  ```bash
  docker compose exec app ./vendor/bin/pint
  docker compose exec app ./vendor/bin/phpstan analyse
  docker compose exec app ./vendor/bin/pest
  ```
- Não desabilitar testes, regras do PHPStan ou Pint para "fazer passar". Corrigir a causa.

### 8.1 Padrão de qualidade e agentes

- O padrão de código (SOLID, design patterns, clean code e testes aplicados a esta arquitetura) está na skill **`.claude/skills/qualidade-codigo/`**: `SKILL.md` (onde cada coisa mora e regras de ouro) e `referencias/` (SOLID, catálogo de padrões, pirâmide de testes e checklist de revisão). Vale para qualquer agente ou pessoa.
- Testes em três suítes: **`tests/Unit`** (domínio, sem banco — toda classe nova de `app/Domain` tem teste unitário próprio em `tests/Unit/<Modulo>/<Classe>Test.php`), **`tests/Arch`** (regras de arquitetura com `arch()` do Pest, conferidas a cada execução) e **`tests/Feature`** (Actions, autorização e telas).
- Agentes do Claude Code em `.claude/agents/`:

  | Agente | Quando usar |
  |---|---|
  | `arquiteto` | antes de implementar algo que toca mais de uma camada: devolve o plano (arquivos, padrões, RNs, testes). Não edita. |
  | `testador` | depois de implementar: escreve e roda os testes unitários, de feature e de arquitetura. |
  | `revisor` | antes de commit/PR: revisa o diff contra este arquivo, o `regras.md` e a skill, e roda Pint e Pest. Não edita. |

- Fluxo sugerido para funcionalidade nova: `arquiteto` → implementação → `testador` → `revisor`. Para mudanças pequenas, basta seguir a skill e rodar Pint e Pest.

## 9. Front-end web

- Priorizar componentes nativos do Filament (tabelas, formulários, filtros, actions, widgets) antes de criar telas customizadas.
- Telas que fogem do padrão do Filament: página customizada do Filament ou componente Livewire, com Blade + Tailwind.
- Componentes de formulário/tabela reutilizados entre Resources (ex.: campo CNPJ, campo moeda) ficam em classes próprias, não copiados.
- Máscaras e validações de UI (CPF, CNPJ, moeda, datas) são conveniência; a validação real é sempre no backend (Action/DTO), valendo igualmente para a API.
- Evitar JavaScript customizado; quando necessário, usar Alpine.js (já incluso no Livewire).
- **Ações das linhas das tabelas aparecem só como ícone**, com o nome e uma descrição curta no tooltip. Isso é aplicado globalmente em `AppServiceProvider` (`Table::configureUsing` → `modifyUngroupedRecordActionsUsing`) por `App\Filament\Support\DescricaoAcoes`. Toda ação de linha nova precisa de `->icon(...)` e de uma descrição em `DescricaoAcoes::DESCRICOES`, com o nome da ação como chave. Ação sem ícone continua com texto.
- Janelas informativas com dados em colunas (ex.: "Quais documentos", itens de uma cobrança) usam `App\Filament\Support\TabelaInformativa::html()`: tabela com cabeçalho, espaçamento e linhas separadas, legível no tema claro e no escuro. Não montar `<table>`/`<ul>` à mão.

### Dashboards

- Dashboards são Widgets do Filament (`StatsOverviewWidget` para os indicadores, `TableWidget` para listas) em `app/Filament/Escritorio/Widgets/`.
- Visibilidade de cada widget por permissão (`canView()` → `$user->can('dashboard.administrador')`), nunca por tipo de usuário. O colaborador hoje não tem a permissão (`regras.md` RN-1500); dar a ele um dashboard no futuro é só atribuir permissão e criar widgets.
- Os números vêm de classes de consulta de domínio (ex.: `ConsultaEmpresasDevendoDocumentacao`), reutilizadas pelo widget e pelo endpoint da API (`GET /api/v1/dashboard/...`), com a regra de "atraso" (RN-625) definida num único lugar.
- Consultas agregadas em SQL (contagens e agrupamentos no banco), nunca carregando todos os itens em memória. Índice em `itens_competencia (escritorio_id, situacao, prazo)` para sustentar a consulta de atrasos.
- Interface em **português do Brasil**; textos via arquivos de tradução (`lang/pt_BR`).
- Formatação: moeda `R$ 1.234,56`, data `dd/mm/aaaa`, timezone `America/Sao_Paulo`.

## 10. Git e fluxo de trabalho

- Branch principal: `main` (sempre estável).
- Branches: `feature/<descricao>`, `fix/<descricao>`, `chore/<descricao>`.
- Commits no padrão **Conventional Commits**, em português: `feat(empresas): cadastro de empresa cliente`.
- PRs pequenos, com descrição do que muda e como testar. CI verde é obrigatório para merge.

## 11. Armadilhas conhecidas (Filament 5)

- **Nomes de parâmetros das closures importam.** O Filament injeta por nome: em `->query()`, `->modifyQueryUsing()` e filtros, use `fn (Builder $query, array $data) => $query...`. Com outro nome (ex.: `$q`) o parâmetro chega como uma consulta vazia e quebra a tela.
- Não dar a um scope o mesmo nome de uma relação do model (ex.: relação `documentos()` e `scopeDocumentos()`); a chamada estática resolve para a relação.
- Relation managers e widgets carregam de forma assíncrona (*lazy*): o teste de página (GET) não os renderiza. Teste-os com `Livewire::test(RelationManager::class, ['ownerRecord' => ..., 'pageClass' => ...])`.
- Em Actions, `extraAttributes(['x-on:click' => ...])` é descartado na renderização; use `alpineClickHandler()` (que desliga o `wire:click` — chame `$wire.metodo()` no próprio handler). Ex.: o "Aplicar filtros" global em `AppServiceProvider`.
- Em testes, o Filament guarda o menu montado do painel no processo: num mesmo teste, ao fazer `get()` em páginas de **painéis diferentes**, verifique o menu na primeira requisição.
- Em testes, depois de um `get()` numa página de painel com tenant (`empresa`, `portal`), o filtro de tenant do Filament continua ativo nos models durante o resto do teste. Faça as consultas de preparação **antes** das requisições.
- Telas reaproveitadas entre painéis (ex.: ficha da admissão no painel `empresa`) montam URLs com `static::getResource()::getUrl(...)`, nunca com o nome fixo de um Resource de outro painel.
- **Não use `Tabs::livewireProperty()` em formulários com `FileUpload`:** com ela só a aba ativa é renderizada, e ao trocar de aba o FilePond é recriado e o anexo some da tela. O `FormularioAdmissao` usa abas no navegador (`persistTabInQueryString('aba')`); o Resumo se atualiza sozinho com `x-intersect="$wire.$refresh()"`, que envia junto o que foi digitado. Ações dentro de uma aba são chamadas em testes com `TestAction::make(...)->schemaComponent('resumo.envio', schema: 'form')`.
- `View::make()->viewData(...)` avalia **uma closure que devolve o array inteiro** (`fn (Get $get) => ['chave' => ...]`), não closures por chave.
- No PostgreSQL, uma violação de índice único dentro de transação invalida a transação inteira: quem depende de capturar `UniqueConstraintViolationException` deve envolver o insert em `DB::transaction()` (savepoint).

## 12. Pendências técnicas da versão inicial

Itens que este documento exige e que ainda **não** foram implementados — devem ser tratados antes de produção:

- **API REST v1** (seção 4): nenhum endpoint criado ainda. As regras estão nas Actions e nas classes de consulta, prontas para serem expostas.
- **Larastan/PHPStan** (seção 2): não instalado; hoje só o Pint roda.
- **DTOs** (seção 3.1): as Actions recebem arrays já filtrados (`Arr::only`), não DTOs.
- **Auditoria completa** (`regras.md` RN-1300, RN-571): existem históricos específicos (admissão, avaliações, cobranças, acessos, mudança de competência de nota), mas não um registro genérico de "antes/depois" de todas as entidades.
- **Bloqueio de 15 min após 5 tentativas** (RN-302): o login usa o limite padrão do Filament (5 tentativas por minuto).
- **Política de dados sensíveis**: aplicada a salário e dados bancários do funcionário; raça/cor e ASO ainda não têm restrição própria.
- **Transações e `DB::` em telas** (`tests/Arch`, exceções em `->ignoring()`): `ConfiguracoesEscritorio`, `EditUsuario` e `EditUsuarioEmpresa` (encerramento de sessões — RN-233) devem passar para Actions.
- **Testes unitários do domínio**: `PreenchimentoAutomatico`, `VerificadorAdmissao`, `CalculadoraRotinaMensal`, `SelecionadorEmpresasParaCobranca` e `GrupoDocumentoFuncionario` são cobertos só por testes de feature.

## 13. O que um agente NÃO deve fazer

- Colocar regra de negócio em controller, Filament Resource, componente Livewire, view ou migration.
- Criar funcionalidade só para web sem pensar no contrato da API.
- Usar `float` para dinheiro.
- Apagar fisicamente registros fiscais/contábeis.
- Fazer query sem escopo de tenant.
- Inventar regra de negócio: se algo não estiver em `regras.md`, **perguntar** ou registrar como pendência, nunca assumir.
- Adicionar dependência nova sem justificar no PR.
- Criar usuários com senhas fracas (como as do `DesenvolvimentoSeeder`) fora dos ambientes `local` e `testing`.
