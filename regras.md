# regras.md — Regras de Negócio

Fonte de verdade sobre **o que** o sistema faz. Regras técnicas ficam em [AGENTS.md](AGENTS.md).

Cada regra tem um código (`RN-XXX`) para ser referenciada em código, testes e PRs. A centena indica a seção (ex.: `RN-3xx` = Autenticação).
Itens marcados com **[A DEFINIR]** ainda dependem de decisão do negócio — não implementar sem confirmação.

---

## 1. Contexto e escopo

SaaS para **escritórios de contabilidade** gerenciarem suas **empresas clientes**: cadastro, funcionários (departamento pessoal), obrigações fiscais, documentos, tarefas da equipe e honorários. Vários escritórios usam o mesmo sistema, cada um completamente isolado dos demais. As próprias empresas clientes também acessam o sistema, por um **portal da empresa**, para enviar documentos e dados de funcionários ao escritório. Inicialmente web; futuramente com aplicativos.

### Ordem de entrega

1. **Fundação** — landing page, login, escritórios, usuários (administrador e colaborador). Seções 3 a 6.
2. **Empresas clientes** — cadastro das empresas atendidas. Seção 7.
3. **Funcionários** — cadastro dos funcionários das empresas clientes, com admissão e desligamento e seus documentos. Seção 8.
4. **Área de trabalho mensal** — painel da empresa por competência, rotina mensal de itens a receber/fazer e fechamento do mês. Seção 9.
5. **Notas fiscais** — upload de XML pela empresa, com classificação automática na competência e consulta por mês. Seção 10.
6. **Dashboard do administrador** — total de empresas e empresas devendo documentação. Seção 18.
7. **Fases seguintes** — obrigações, documentos gerais, certificados, tarefas, honorários, notificações. Seções 11 a 16 (regras preliminares, sujeitas a revisão).

## 2. Glossário

| Termo | Significado |
|---|---|
| **Plataforma** | O próprio SaaS e a equipe que o mantém. |
| **Escritório** | O escritório contábil que assina o sistema (tenant). |
| **Usuário** | Pessoa que acessa o sistema em nome de um escritório. |
| **Administrador** | Usuário com acesso total ao seu escritório. |
| **Colaborador** | Usuário da equipe do escritório com acesso limitado pelas suas atribuições. |
| **Usuário da empresa** | Pessoa da empresa cliente que acessa o sistema pelo portal da empresa, restrita à(s) sua(s) empresa(s). |
| **Portal da empresa** | Área do sistema usada pelos usuários das empresas clientes. |
| **Atribuição** | Área de trabalho que o colaborador exerce no escritório (ex.: admissões, despesas), com as permissões correspondentes. Um colaborador pode ter várias. |
| **Perfil** | Conjunto de permissões de um usuário da empresa (RN-245). Na equipe do escritório foi substituído pelas atribuições. |
| **Permissão** | Autorização para uma ação específica (ex.: cadastrar empresa cliente). |
| **Empresa cliente** | Empresa atendida pelo escritório. |
| **Sócio** | Pessoa física ou jurídica que integra o quadro societário de uma empresa cliente. |
| **Estabelecimento** | Matriz ou filial (CNPJ próprio) de uma empresa cliente; é o local de trabalho dos funcionários. |
| **CBO** | Classificação Brasileira de Ocupações — código da ocupação do cargo. |
| **Funcionário** | Pessoa empregada por uma empresa cliente (regime CLT). |
| **Contrato de trabalho** | Período de vínculo do funcionário com a empresa, da admissão ao desligamento. Um funcionário pode ter vários ao longo do tempo (recontratação). |
| **Admissão** | Processo de contratação: coleta de dados e documentos até o início do contrato. |
| **Desligamento** | Processo de encerramento do contrato de trabalho (rescisão). |
| **ASO** | Atestado de Saúde Ocupacional (exame admissional, demissional etc.). |
| **TRCT** | Termo de Rescisão do Contrato de Trabalho. |
| **eSocial** | Sistema do governo para envio de informações trabalhistas (eventos S-2200 admissão, S-2299 desligamento). |
| **Competência** | Mês/ano de referência do trabalho de uma empresa (ex.: 09/2026). |
| **Área de trabalho** | Tela em que o usuário trabalha uma empresa em uma competência. |
| **Catálogo de documentos** | Lista, definida por cada escritório, dos documentos e tarefas mensais que ele exige das suas empresas. |
| **Rotina mensal** | Tipos do catálogo que se aplicam a uma empresa e geram os seus itens todo mês. |
| **Avaliação** | Julgamento do escritório sobre um documento enviado pela empresa: aceito (correto e válido) ou recusado (com motivo). |
| **Item da competência** | Cada entrega ou tarefa de uma empresa em uma competência, gerada pela rotina mensal ou incluída avulsa. |
| **NF-e / NFC-e** | Nota fiscal eletrônica (modelo 55) e nota fiscal de consumidor eletrônica (modelo 65). |
| **XML da nota** | Arquivo oficial da nota fiscal eletrônica, com a assinatura do emitente e o protocolo de autorização da SEFAZ. |
| **Chave de acesso** | Código de 44 dígitos que identifica unicamente uma nota fiscal eletrônica. |
| **DANFE** | Representação impressa (PDF) da NF-e. |
| **Obrigação** | Entrega fiscal, trabalhista ou contábil com prazo (DAS, DCTFWeb, SPED, eSocial...). |
| **Regime tributário** | MEI, Simples Nacional, Lucro Presumido, Lucro Real. |
| **Honorário** | Valor cobrado pelo escritório da empresa cliente. |

## 3. Landing page (página pública)

- **RN-001** — A página inicial (`/`) é pública e apresenta o produto, com acesso visível à área de login ("Entrar").
- **RN-002** — Usuário já autenticado que acessa a landing page vê a opção de ir direto ao painel, em vez de "Entrar".
- **RN-003** — **[A DEFINIR]** conteúdo da página (seções, planos/preços, contato) e se haverá botão de cadastro / teste grátis (depende de RN-102).
- **RN-004** — A página pública tem um formulário "Agende uma demonstração" (nome, escritório, e-mail, telefone, quantidade de empresas atendidas e mensagem). O pedido fica registrado e a equipe comercial é avisada por e-mail. Os planos aparecem com valor "sob consulta" até a definição de preços (D2).

## 4. Escritórios (tenants)

- **RN-100** — Cada escritório é um tenant isolado. Nenhum dado de um escritório pode ser visto ou alterado por usuário de outro escritório.
- **RN-101** — O escritório possui razão social, CNPJ (ou CPF, para contador autônomo) válido e único na plataforma, e CRC do responsável técnico.
- **RN-102** — **[A DEFINIR]** como um escritório é cadastrado: autocadastro pela landing page ou cadastro feito pela equipe da plataforma.
- **RN-103** — Situações do escritório: `ativo`, `suspenso`, `cancelado`. Usuários de escritório `suspenso` ou `cancelado` não conseguem entrar no sistema e recebem mensagem explicando o motivo genérico ("acesso do escritório suspenso, contate o administrador").
- **RN-104** — Todo escritório tem, desde a sua criação, exatamente um **administrador titular**.

## 5. Usuários e atribuições

### 5.1 Tipos de usuário

- **RN-200** — Nesta fase existem três tipos de usuário:
  - **Administrador** (equipe do escritório) — acesso total ao seu escritório: todos os módulos, gestão de usuários, atribuições e configurações.
  - **Colaborador** (equipe do escritório) — acesso definido pelas suas atribuições (seção 5.3).
  - **Usuário da empresa** (cliente do escritório) — acesso restrito aos dados da(s) empresa(s) cliente(s) a que está vinculado (seção 5.5).
- **RN-201** — Um usuário pertence a um único escritório.
- **RN-202** — Todo usuário tem e-mail único na plataforma (não pode repetir nem entre escritórios diferentes).
- **RN-203** — Dados mínimos do usuário: nome, e-mail, situação (`ativo`, `inativo`) e tipo (administrador, colaborador ou usuário da empresa). **[A DEFINIR]** se CPF e telefone são obrigatórios.

### 5.2 Administrador

- **RN-210** — O administrador titular (RN-104) não pode ser desativado nem rebaixado a colaborador. Para isso, a titularidade deve antes ser transferida a outro administrador.
- **RN-211** — Somente o administrador titular pode transferir a titularidade.
- **RN-212** — **[A DEFINIR]** se o escritório pode ter mais de um administrador além do titular.
- **RN-213** — Um administrador não pode desativar a si mesmo.

### 5.3 Colaborador e atribuições

- **RN-220** — Não há perfis fixos de colaborador: o administrador marca, para cada colaborador, as **atribuições** que ele exerce (ex.: Bruno faz admissões; Eduardo cuida do financeiro e das despesas; Daniela faz tudo). Todo colaborador tem **ao menos uma** atribuição, e suas permissões são a soma das permissões de cada atribuição marcada. O administrador não tem atribuições: já tem acesso total.
- **RN-221** — Atribuições desta fase (o catálogo é da plataforma; novas atribuições entram sem alterar as regras de autorização já existentes):

  | Atribuição | O que permite |
  |---|---|
  | Cadastro de empresas | cadastrar e alterar empresas clientes |
  | Usuários do portal | convidar e gerenciar usuários das empresas (RN-242) |
  | Admissões e desligamentos | fila de admissões, análise, conclusão e desligamentos (seção 8) |
  | Dados sensíveis de funcionários | ver os dados de RN-570 |
  | Avaliação de documentos | avaliar documentos, enviar documentos em nome da empresa (RN-646) e concluir tarefas da rotina mensal |
  | Catálogo de documentos | gerenciar o catálogo do escritório (RN-611) |
  | Reabrir competência | reabrir competências fechadas (RN-633) |
  | Notas fiscais | enviar XMLs em nome da empresa e mover notas de competência (RN-700, RN-722) |
  | Financeiro (cobrança de documentação) | cobranças de documentação (seção 16.1) |
  | Despesas | despesas das empresas e seus pagamentos (seção 19) |
  | Relatórios | relatório de admissões por colaborador (RN-556) |

  O formulário tem a opção "marcar todas", para o colaborador que faz de tudo.
- **RN-222** — Permissões são definidas por módulo e ação (ex.: empresas clientes → visualizar, cadastrar, editar, encerrar). O catálogo de permissões é definido pela plataforma.
- **RN-223** — Permissões exclusivas do administrador, que **nunca** fazem parte de uma atribuição: gerenciar usuários da equipe e suas atribuições, alterar dados e configurações do escritório. O dashboard do administrador (RN-1500) e o histórico de acessos (RN-331) também continuam só do administrador.
- **RN-224** — Todo colaborador, qualquer que seja a atribuição, **consulta** as empresas clientes e as notas fiscais.
- **RN-225** — As atribuições são as mesmas para todos os escritórios (definidas pela plataforma); cada escritório escolhe quais marcar para cada colaborador.
- **RN-226** — **[A DEFINIR]** se o colaborador terá acesso a todas as empresas clientes ou somente às da sua carteira.

### 5.4 Gestão de usuários

- **RN-230** — Somente administradores cadastram, editam, ativam e desativam usuários da **equipe do escritório** (administradores e colaboradores). Usuários das empresas clientes seguem RN-242.
- **RN-231** — Novos usuários são adicionados por **convite**: o administrador informa nome, e-mail, tipo e atribuições; o convidado recebe e-mail com link para definir a própria senha. O administrador nunca define nem vê a senha de outro usuário.
- **RN-232** — O link de convite expira em **7 dias** e pode ser reenviado pelo administrador; reenviar invalida o link anterior.
- **RN-233** — Usuário nunca é excluído fisicamente; é desativado. Desativar revoga imediatamente todas as sessões e tokens (web e app) e bloqueia novos logins.
- **RN-234** — Alterar as atribuições ou o tipo de um usuário passa a valer imediatamente, inclusive para sessões já abertas.

### 5.5 Usuários das empresas clientes

- **RN-240** — O usuário da empresa é vinculado a **uma ou mais empresas clientes** do mesmo escritório (ex.: um sócio com duas empresas). Ele só vê e altera dados das empresas a que está vinculado — nunca de outras empresas do escritório.
- **RN-241** — Quando vinculado a mais de uma empresa, o usuário escolhe em qual empresa está trabalhando, da mesma forma que a equipe escolhe a empresa na área de trabalho (seção 9).
- **RN-242** — Usuários da empresa são cadastrados por **convite** (mesmo fluxo de RN-231 e RN-232), por administradores ou colaboradores com permissão específica para isso. **[A DEFINIR]** se o próprio usuário da empresa poderá convidar outras pessoas da sua empresa.
- **RN-243** — Nesta fase existe um único perfil para usuários da empresa, com acesso a:
  - consultar os dados cadastrais da empresa;
  - consultar os documentos da empresa que o escritório marcou como visíveis (RN-904);
  - enviar os XMLs das notas fiscais e consultar suas notas (seção 10);
  - enviar documentos dos itens da competência (despesas, extratos, certidões — seção 9.7);
  - cadastrar funcionários ("Admitir novo", na tela de Funcionários) e enviar seus dados e documentos de admissão (seção 8.3); depois de admitido, apenas trocar documentos (RN-565);
  - consultar os funcionários da empresa e seus documentos;
  - cadastrar as despesas da empresa e registrar seus pagamentos (RN-1607).
- **RN-244** — O usuário da empresa **não** acessa: outras empresas, tarefas e anotações internas do escritório, configurações do escritório, usuários da equipe.
- **RN-245** — O sistema deve estar preparado para perfis diferentes de usuário da empresa no futuro (ex.: sócio, RH, financeiro), seguindo o mesmo modelo de permissões da seção 5.3.
- **RN-246** — Desvincular o usuário de uma empresa remove o acesso a ela imediatamente. Ao desvincular a última empresa, o usuário é desativado (RN-233).

## 6. Autenticação

### 6.1 Login

- **RN-300** — Login com e-mail e senha.
- **RN-301** — Em caso de falha, a mensagem é sempre genérica ("e-mail ou senha inválidos"), sem revelar se o e-mail existe.
- **RN-302** — Após **5 tentativas falhas** para o mesmo e-mail/IP, novas tentativas ficam bloqueadas por **15 minutos**.
- **RN-303** — Não podem entrar: usuário `inativo`, usuário com convite ainda não aceito, usuário de escritório `suspenso`/`cancelado` (RN-103), usuário da empresa sem nenhuma empresa vinculada em situação `ativa`.
- **RN-304** — A tela de login é a mesma para todos. Após o login, a equipe do escritório é direcionada ao painel do escritório e o usuário da empresa ao **portal da empresa**.
- **RN-305** — Opção "manter conectado" disponível no login web.
- **RN-306** — Sessão web expira após **120 minutos** de inatividade (exceto com "manter conectado").
- **RN-307** — **[A DEFINIR]** autenticação em dois fatores (2FA): opcional, obrigatória para administradores ou obrigatória para todos.

### 6.2 Senha

- **RN-310** — Senha com no mínimo **8 caracteres**, contendo letras e números.
- **RN-311** — Senha não pode constar em listas públicas de senhas vazadas.
- **RN-312** — Recuperação de senha por e-mail ("esqueci minha senha"). O link expira em **60 minutos** e só pode ser usado uma vez. A resposta da solicitação é sempre a mesma, exista ou não o e-mail.
- **RN-313** — Ao redefinir ou alterar a senha, todas as outras sessões e tokens do usuário são encerrados.
- **RN-314** — O usuário autenticado pode alterar a própria senha informando a senha atual.

### 6.3 Logout e dispositivos

- **RN-320** — Logout encerra a sessão atual.
- **RN-321** — No futuro app, cada dispositivo recebe um token próprio; o usuário poderá ver e encerrar seus dispositivos conectados.

### 6.4 Registro de acessos

- **RN-330** — Registrar cada login (sucesso e falha), logout, recuperação e alteração de senha: usuário/e-mail informado, data/hora, IP e dispositivo/navegador.
- **RN-331** — O administrador pode consultar o histórico de acessos dos usuários do seu escritório.

## 7. Empresas clientes

### 7.1 Dados cadastrais

- **RN-400** — CNPJ obrigatório, válido (dígitos verificadores) e único dentro do escritório.
- **RN-401** — Dados do cadastro (base: comprovante de inscrição do CNPJ na Receita Federal):
  - **Obrigatórios:** CNPJ, razão social, natureza jurídica, regime tributário, data de início do atendimento pelo escritório, situação no escritório.
  - **Complementares:** nome fantasia, data de abertura, porte (ME, EPP, demais), CNAE principal e secundários, capital social, situação cadastral na Receita, inscrição estadual (ou "isento"), inscrição municipal, endereço da sede, telefone, e-mail.
- **RN-402** — Situações: `ativa`, `suspensa`, `encerrada`. Empresa encerrada não gera novas obrigações nem admite funcionários, mas mantém histórico.
- **RN-403** — Regime tributário tem **histórico com vigência** (data início/fim); a alteração não sobrescreve o regime anterior.
- **RN-404** — Quadro societário: cada sócio tem nome, CPF (ou CNPJ, se pessoa jurídica) válido, qualificação (ex.: sócio-administrador), percentual de participação e data de entrada. A soma das participações deve ser 100%.
- **RN-405** — Empresa cliente não pode ser excluída fisicamente; apenas encerrada/arquivada.
- **RN-406** — **[A DEFINIR]** consulta automática de dados do CNPJ em fonte pública no cadastro.
- **RN-407** — Contato responsável na empresa: nome, CPF, telefone e e-mail.
- **RN-408** — Uma empresa não pode ser encerrada enquanto tiver contratos de trabalho `admitido`, em admissão (`rascunho`, `em_analise`, `devolvida`) ou em desligamento; todos devem estar desligados ou cancelados antes.

### 7.2 Dados de empregador

Necessários apenas para empresas que terão funcionários (base: eventos S-1000 e S-1005 do eSocial).

- **RN-410** — Dados de empregador: classificação tributária (tabela 8 do eSocial), indicativo de cooperativa, indicativo de construtora e indicativo de desoneração da folha.
- **RN-411** — Estabelecimentos: a matriz é criada automaticamente a partir do cadastro da empresa; filiais podem ser incluídas. Cada estabelecimento tem CNPJ, endereço, **CNAE preponderante**, **alíquota RAT** (1%, 2% ou 3%) e **FAP** (de 0,5 a 2,0; empresas do Simples Nacional usam 1,0).
- **RN-412** — Cada estabelecimento tem **grau de risco** (1 a 4, conforme NR-4), derivado do CNAE preponderante, podendo ser ajustado manualmente. É usado nas regras de exame demissional (RN-545).
- **RN-413** — Todo contrato de trabalho é vinculado a um estabelecimento (local de trabalho).
- **RN-414** — Não é possível iniciar uma admissão em empresa sem dados de empregador (RN-410) e sem ao menos um estabelecimento (RN-411).
- **RN-415** — **[A DEFINIR]** lotações tributárias (FPAS, códigos de terceiros — evento S-1020). Só serão necessárias se o sistema passar a calcular folha ou rescisão.

## 8. Funcionários (departamento pessoal)

### 8.1 Conceitos

- **RN-500** — Todo funcionário pertence a uma única empresa cliente. A mesma pessoa em duas empresas clientes diferentes tem dois cadastros independentes.
- **RN-501** — O funcionário tem um ou mais **contratos de trabalho**. Cada contrato começa na admissão e termina no desligamento. Uma recontratação gera um **novo contrato** no mesmo cadastro, preservando o histórico dos anteriores.
- **RN-502** — O CPF é único por empresa cliente. Um funcionário só pode ter **um contrato não encerrado** por vez na mesma empresa.
- **RN-503** — Situações do contrato:
  - `rascunho` — a empresa (ou o escritório) está preenchendo dados e enviando documentos; ainda não solicitou a admissão.
  - `em_analise` — admissão solicitada; está na fila do escritório, sendo analisada e registrada.
  - `devolvida` — o escritório devolveu a solicitação para a empresa corrigir.
  - `admitido` — admissão concluída pelo escritório; contrato vigente.
  - `em_desligamento` — desligamento iniciado, dados ou documentos pendentes.
  - `desligado` — desligamento concluído.
  - `admissao_cancelada` — admissão desistida antes de concluída (RN-584, RN-596).
- **RN-504** — Funcionários, contratos e seus documentos nunca são excluídos fisicamente.

### 8.2 Dados pessoais

- **RN-510** — Dados pessoais:
  - **Obrigatórios (exigidos pelo eSocial):** nome completo, CPF válido, data de nascimento, sexo, raça/cor, grau de instrução, país de nascimento, nacionalidade, endereço (CEP, logradouro, número, município, UF — ou endereço no exterior com país e cidade).
  - **Complementares:** nome social, estado civil, complemento e bairro, telefone, e-mail, documento de identidade (RG ou CNH: número, órgão emissor, UF, data de emissão), PIS/NIS.
  - O CPF é a identificação do trabalhador no eSocial e na Carteira de Trabalho Digital.
- **RN-511** — Idade mínima na data de admissão: **16 anos**, ou **14 anos** para aprendiz.
- **RN-512** — Dependentes: nome, data de nascimento, tipo de dependente (tabela 7 do eSocial: cônjuge, filho, enteado...), e indicadores de dependente para IRRF, para salário-família e de incapacidade física ou mental para o trabalho. O CPF do dependente é obrigatório quando ele for dependente para IRRF.
- **RN-513** — Dados bancários para pagamento: banco, agência, conta e tipo de conta, ou chave Pix.
- **RN-514** — Pessoa com deficiência: tipo(s) de deficiência (física, visual, auditiva, mental, intelectual), se é reabilitado/readaptado e se é contado na cota de PcD da empresa.
- **RN-515** — Trabalhador estrangeiro: condição de ingresso no país e prazo de residência.

### 8.3 Fluxo da admissão (empresa → escritório)

Visão geral:

```
 EMPRESA (portal)                          ESCRITÓRIO (painel)
 ─────────────────                         ───────────────────
 1. Cadastra funcionário ── rascunho
 2. Preenche dados e
    anexa documentos
 3. Solicita admissão ──────────────────▶  em_analise  (entra na fila de admissões)
                                           4. Colaborador assume e analisa
                                              ├─ 5a. Devolve com motivo ──┐
           devolvida  ◀───────────────────────────────────────────────────┘
 6. Corrige e solicita
    novamente ─────────────────────────▶  em_analise
                                              └─ 5b. Conclui a admissão ─▶ admitido
```

#### Etapa 1 — Rascunho (empresa)

- **RN-580** — A admissão é iniciada pela **empresa cliente** no portal: o usuário da empresa cadastra o funcionário informando, no mínimo, nome, CPF e data de admissão prevista. A admissão nasce como `rascunho`.
- **RN-581** — O escritório também pode iniciar uma admissão em nome da empresa (ex.: documentos recebidos por e-mail). Nesse caso ela também começa em `rascunho`, e o próprio colaborador solicita a admissão.
- **RN-582** — No rascunho, a empresa preenche (e pode salvar parcialmente e voltar depois):
  - **dados pessoais** (RN-510), **dependentes** (RN-512), **dados bancários** (RN-513), **deficiência** e **estrangeiro** (RN-514, RN-515);
  - **dados do contrato:** data de admissão prevista, cargo, salário, tipo de contrato (indeterminado, experiência — com os períodos —, prazo determinado), jornada e horário, estabelecimento (local de trabalho);
  - **documentos** do checklist (RN-526), cada um anexado ao seu item.
- **RN-583** — O sistema mostra à empresa, o tempo todo, a **lista de pendências**: campos obrigatórios não preenchidos e documentos obrigatórios não anexados.
- **RN-584** — A empresa pode excluir (cancelar) uma admissão em `rascunho`. Ela vai para `admissao_cancelada`, sem justificativa obrigatória.
- **RN-585** — Enquanto estiver em `rascunho`, a admissão **não aparece** na fila do escritório.

#### Etapa 2 — Solicitar admissão (empresa)

- **RN-586** — O botão **"Solicitar admissão"** só fica habilitado quando não houver pendências (RN-583).
- **RN-587** — Ao solicitar, o sistema valida novamente todas as regras (idade mínima RN-511, datas de experiência RN-522, ASO RN-527, salário RN-524) e, se estiver tudo certo, a admissão passa a `em_analise` e entra na **fila de admissões** do escritório. Data/hora e usuário da solicitação são registrados.
- **RN-588** — A partir da solicitação, a empresa **não pode mais alterar** dados nem documentos; ela apenas consulta a admissão e acompanha a situação. Para corrigir algo, deve pedir ao escritório (que pode devolver — RN-595).
- **RN-589** — Antecedência mínima: como o registro no eSocial deve ocorrer até o dia anterior ao início do trabalho (RN-529), o sistema alerta a empresa, ao solicitar, se a data de admissão prevista estiver próxima demais. **[A DEFINIR]** antecedência mínima (sugestão: 2 dias úteis antes da data de admissão), se ela bloqueia ou só alerta, e se o escritório pode configurá-la.

#### Etapa 3 — Fila de admissões (escritório)

- **RN-590** — A **fila de admissões** lista todas as admissões `em_analise` das empresas que o colaborador pode acessar (RN-226), mostrando: empresa, funcionário, cargo, data de admissão prevista, data da solicitação, responsável (se houver) e prazo do eSocial.
- **RN-591** — A fila é ordenada por **data de admissão prevista** (a mais próxima primeiro), com destaque para as que vencem o prazo do eSocial em até 1 dia útil ou já venceram. Filtros: empresa, responsável, "sem responsável", período.
- **RN-592** — O colaborador **assume** uma admissão da fila, tornando-se o **responsável**. A situação continua `em_analise`. Uma admissão com responsável não pode ser assumida por outro colaborador, salvo transferência feita por um administrador ou pelo próprio responsável. Ao assumir, fica registrado quem **iniciou o atendimento** e quando (RN-550).

#### Etapa 4 — Análise (escritório)

- **RN-593** — Na análise, o colaborador confere os dados e **cada documento** do checklist, marcando-o como `aceito` ou `recusado` (com motivo).
- **RN-594** — O colaborador pode corrigir pequenos erros nos dados informados pela empresa sem devolver a admissão. Toda correção fica registrada no histórico (valor anterior, novo valor, quem e quando) e visível para a empresa.
- **RN-595** — Se houver documento recusado ou dado que só a empresa pode corrigir, o colaborador **devolve** a admissão informando o motivo. Na própria tela de devolução ele marca os documentos com problema, cada um com o motivo (ilegível, diferente do solicitado, vencido ou desatualizado, incompleto, outro) e um detalhe opcional (ex.: "comprovante com mais de 90 dias"); esses documentos ficam recusados e a lista deles entra no motivo da devolução. Ela passa a `devolvida`; a empresa vê o motivo e os documentos recusados, volta a poder editar, e solicita novamente (RN-586). Na nova solicitação, a admissão volta à fila **mantendo o mesmo responsável**.
- **RN-596** — Uma admissão `em_analise` pode ser cancelada pelo escritório (ex.: candidato desistiu), com justificativa. A empresa pode pedir o cancelamento, mas quem executa é o escritório.

#### Etapa 5 — Concluir a admissão (escritório)

- **RN-597** — Para concluir, o colaborador completa os **dados técnicos** que cabem ao escritório: matrícula (RN-520), categoria do trabalhador, CBO, natureza da atividade, regime de jornada, sindicato e demais dados do eSocial; confirma a **data de admissão efetiva**; e, opcionalmente, informa o número do recibo do eSocial (S-2200).
- **RN-598** — A conclusão exige todos os dados obrigatórios preenchidos e todos os documentos obrigatórios `aceito` (RN-528). Ao concluir, o contrato passa a **`admitido`**, o funcionário passa a constar como ativo na empresa e a admissão aparece na competência do mês de admissão (RN-604). Fica registrado quem **admitiu** o funcionário e quando (RN-550).
- **RN-599** — **[A DEFINIR]** se haverá documentos gerados pelo escritório na conclusão (ex.: contrato de trabalho, ficha de registro) para a empresa baixar, colher assinatura e devolver pelo portal.

#### Regras gerais do fluxo

- **RN-575** — Histórico: cada mudança de situação (solicitada, assumida, devolvida, concluída, cancelada) é registrada com data/hora, usuário e motivo, e exibida como linha do tempo para a empresa e para o escritório.
- **RN-576** — Notificações: o escritório é avisado quando uma admissão é solicitada (ou solicitada novamente após devolução); a empresa é avisada quando a admissão é devolvida, cancelada e quando é concluída (`admitido`).
- **RN-577** — Visão da empresa: o portal mostra a lista das admissões da empresa com a situação de cada uma (`rascunho`, `em_analise`, `devolvida`, `admitido`, cancelada).
- **RN-578** — **[A DEFINIR]** se o desligamento também será solicitado pela empresa pelo portal (sugestão: mesmo modelo — a empresa solicita informando motivo, data e aviso prévio; o escritório analisa, processa e devolve o TRCT para assinatura e upload).

### 8.4 Admissão — dados e regras legais

- **RN-519** — A lei separa os dados da admissão em duas etapas, com prazos diferentes (Portaria MTP 671/2021, art. 14). O sistema segue a mesma divisão:
  - **Etapa 1 — até o dia anterior ao início do trabalho:** CPF, data de nascimento, data de admissão, matrícula, categoria do trabalhador, natureza da atividade (urbana/rural), CBO, salário contratual, tipo de contrato (com data de término, se prazo determinado).
  - **Etapa 2 — até o dia 15 do mês seguinte à admissão:** demais dados pessoais (RN-510), cargo, salário variável, jornada, local de trabalho, deficiência, dados de aprendiz, sindicato, dados de estrangeiro, tipo de admissão.
  - O sistema exibe o prazo de cada etapa e alerta quando houver dado pendente perto do vencimento.
- **RN-520** — Dados do contrato:
  - **Matrícula:** única na empresa. **[A DEFINIR]** se gerada automaticamente (sequencial por empresa) ou informada.
  - **Categoria do trabalhador** (tabela 1 do eSocial). **[A DEFINIR]** categorias aceitas nesta fase (sugestão: 101 — empregado geral e 103 — aprendiz).
  - **Tipo de admissão:** admissão comum; transferências entre empresas do grupo ou por sucessão ficam para depois.
  - **Regime:** trabalhista CLT e previdenciário RGPS (fixos nesta fase).
  - **Natureza da atividade:** urbana ou rural.
  - **Cargo** (nome) e **CBO**.
  - **Remuneração:** salário-base, unidade de pagamento (hora, dia, semana, quinzena, mês, tarefa) e descrição do salário variável, quando houver.
  - **Duração:** prazo indeterminado ou prazo determinado. No prazo determinado: data de término e se há cláusula assecuratória de rescisão antecipada (art. 481 da CLT).
  - **Jornada:** regime de jornada (submetido a controle de horário; atividade externa, cargo de gestão ou teletrabalho — art. 62 da CLT), horas semanais, tempo parcial (sim/não), trabalho noturno (sim/não) e descrição da jornada/horário.
  - **Local de trabalho:** estabelecimento (RN-413).
  - **Sindicato da categoria:** CNPJ e mês da data-base.
  - **Aprendiz:** CNPJ da entidade qualificadora e modo de contratação (direta ou indireta).
  - **Complementares:** departamento/setor.
- **RN-521** — Para o usuário, os tipos de contrato são: **prazo indeterminado**, **contrato de experiência** e **prazo determinado**. O contrato de experiência é, legalmente, um prazo determinado; a distinção existe para aplicar RN-522. **[A DEFINIR]** se haverá intermitente ou temporário nesta fase.
- **RN-522** — Contrato de experiência: no máximo **90 dias** no total, podendo ser dividido em até **2 períodos** (uma única prorrogação). O sistema calcula as datas de término e alerta antes de cada vencimento. A prorrogação deve ser registrada até o **primeiro dia útil** seguinte (Portaria MTP 671/2021, art. 14, VI). **[A DEFINIR]** antecedência do alerta (sugestão: 10 dias).
- **RN-523** — Contrato por prazo determinado: data de término obrigatória, duração máxima de **2 anos**.
- **RN-524** — O sistema alerta quando o salário informado for menor que o salário mínimo nacional vigente, proporcional à jornada. **[A DEFINIR]** se deve bloquear em vez de alertar.
- **RN-525** — Alterações de salário, cargo e jornada após a admissão são registradas como **histórico com data de vigência**; nunca sobrescrevem o valor anterior.
- **RN-526** — Checklist de documentos admissionais:
  - **Sempre obrigatórios:** documento de identificação com foto, comprovante de residência, ASO admissional, contrato de trabalho assinado.
  - **Obrigatórios conforme o caso:** certidão de casamento (se casado), certidão de nascimento e CPF dos dependentes (se houver), cartão de vacinação de filhos até 6 anos e comprovante de frequência escolar de filhos de 7 a 14 anos (se houver salário-família), certificado de reservista (homens de 18 a 45 anos).
  - **Opcionais:** comprovante de escolaridade, título de eleitor, foto, outros.
  - **[A DEFINIR]** se o escritório poderá personalizar o checklist (incluir/remover itens).
- **RN-527** — A data do ASO admissional deve ser igual ou anterior à data de admissão.
- **RN-528** — A admissão só pode ser concluída (contrato `admitido`) quando todos os dados obrigatórios estiverem preenchidos e todos os documentos obrigatórios estiverem anexados e aceitos (RN-598). O sistema exibe as pendências.
- **RN-529** — O eSocial exige o envio da admissão (S-2200) até o **dia anterior ao início do trabalho**. O sistema alerta admissões ainda não registradas (`rascunho`, `em_analise`, `devolvida`) cuja data de admissão está próxima ou já passou. Opcionalmente, registra o número do recibo de envio do eSocial.
- **RN-530** — Recontratação: se o contrato anterior terminou por dispensa sem justa causa há **menos de 90 dias**, o sistema alerta o usuário (presunção de fraude — Portaria MTb 384/1992). O alerta não impede a admissão.

### 8.5 Desligamento

- **RN-540** — Dados do desligamento (base: Portaria MTP 671/2021, art. 14, VII, e evento S-2299):
  - **Obrigatórios:** motivo (RN-541), data de desligamento (último dia do contrato), tipo de aviso prévio (trabalhado, indenizado, dispensado, não se aplica), pensão alimentícia (não há / percentual / valor / percentual e valor — com o percentual e/ou valor).
  - **Condicionais:** data do aviso prévio (quando houver aviso); data projetada do fim do aviso prévio (obrigatória quando indenizado); número do processo trabalhista (desligamento por decisão judicial); adesão a PDV (quando aplicável).
  - **Complementares:** dias de aviso prévio (RN-542), valor líquido da rescisão, observações.
- **RN-541** — Os motivos seguem a **tabela 19 do eSocial**. Nesta fase o sistema oferece apenas os motivos aplicáveis a empregados CLT:

  | Código | Motivo |
  |---|---|
  | 01 | Justa causa, por iniciativa do empregador |
  | 02 | Sem justa causa, por iniciativa do empregador |
  | 03 | Rescisão antecipada do contrato a termo, pelo empregador |
  | 04 | Rescisão antecipada do contrato a termo, pelo empregado |
  | 05 | Culpa recíproca |
  | 06 | Término do contrato a termo (inclui fim da experiência) |
  | 07 | Iniciativa do empregado (pedido de demissão) |
  | 10 | Falecimento do empregado |
  | 14 | Encerramento da empresa, de estabelecimento ou de atividade |
  | 15 | Aprendiz — desempenho insuficiente ou inadaptação |
  | 17 | Rescisão indireta |
  | 27 | Força maior |
  | 33 | Acordo entre as partes (art. 484-A da CLT) |

  A lista completa e vigente é mantida como tabela de referência e deve ser conferida com o leiaute oficial antes da implementação.
- **RN-542** — Na dispensa sem justa causa (02), o sistema calcula os dias de aviso prévio proporcional: **30 dias + 3 dias por ano completo de serviço, até 90 dias** (Lei 12.506/2011). Nos demais motivos com aviso prévio, 30 dias. O valor pode ser ajustado manualmente, com justificativa. Quando o aviso é indenizado, a data projetada do fim do aviso é calculada somando os dias de aviso à data de desligamento.
- **RN-543** — A data de desligamento não pode ser anterior à data de admissão.
- **RN-544** — O sistema calcula e exibe os prazos legais, alertando antes do vencimento:
  - pagamento das verbas rescisórias: **10 dias corridos** após o término do contrato (art. 477, §6º da CLT);
  - envio do desligamento ao eSocial (S-2299): até o **10º dia** seguinte ao desligamento.
- **RN-545** — ASO demissional obrigatório, exceto quando o último exame ocupacional foi realizado há menos de **135 dias** (estabelecimentos de grau de risco 1 e 2 — RN-412) ou **90 dias** (grau de risco 3 e 4), conforme NR-7. Nesse caso, o usuário marca a dispensa e anexa o último ASO.
- **RN-546** — Checklist de documentos demissionais:
  - **Sempre obrigatórios:** TRCT assinado, comprovante de pagamento das verbas rescisórias, ASO demissional (ou dispensa conforme RN-545), comprovante de recolhimento do FGTS rescisório.
  - **Conforme o motivo:** pedido de demissão assinado (pedido de demissão); aviso prévio assinado (quando houver aviso); termo de acordo (art. 484-A); guia de seguro-desemprego (dispensa sem justa causa e rescisão indireta); documentos que fundamentam a justa causa (justa causa); certidão de óbito (falecimento).
- **RN-547** — O desligamento só pode ser concluído (contrato `desligado`) quando todos os dados e documentos obrigatórios estiverem completos.
- **RN-548** — Enquanto o contrato estiver `em_desligamento`, o desligamento pode ser cancelado (ex.: reconsideração do aviso prévio), com justificativa; o contrato volta a `admitido`. **[A DEFINIR]** se e por quem um desligamento já concluído pode ser revertido.
- **RN-549** — **[A DEFINIR]** cálculo das verbas rescisórias pelo sistema. Nesta fase o sistema apenas registra o valor e anexa o TRCT gerado em outro sistema.

### 8.6 Documentos do funcionário

- **RN-560** — Cada documento tem tipo (item do checklist), arquivo, data de envio e usuário que enviou. Fica vinculado ao funcionário (documentos pessoais) ou a um contrato (documentos de admissão e de desligamento).
- **RN-561** — Formatos aceitos conforme RN-901.
- **RN-562** — Substituir um documento mantém a versão anterior no histórico.
- **RN-563** — **[A DEFINIR]** tabela de prazos de guarda dos documentos trabalhistas. Até que seja definida, nenhum documento de funcionário pode ser descartado.
- **RN-565** — Depois da admissão concluída, a **empresa não altera os dados do funcionário** (pessoais, contrato, dependentes, bancários): quem atualiza é o escritório (atribuição "Admissões e desligamentos"). A empresa pode apenas **trocar ou incluir documentos** do funcionário, pela opção "Atualizar cadastro" da tela de Funcionários do portal. Durante a admissão (`rascunho` ou `devolvida`) a empresa continua preenchendo o formulário completo (RN-582).
- **RN-566** — Na troca, um documento nunca é removido: o novo arquivo vira a versão vigente e a anterior fica no histórico (RN-562).
- **RN-567** — Todo documento trocado ou incluído pela empresa em funcionário admitido fica **em análise** até alguém do Operacional **aceitar ou recusar** (com motivo) na ficha do funcionário. Enquanto isso, ele aparece nas ações pendentes da Página da Empresa (RN-1521), e a empresa vê a situação de cada documento ("em análise", "aceito" ou "recusado" com o motivo). Envio e avaliação ficam na linha do tempo (RN-575).
- **RN-564** — Além do arquivo, o ASO tem seus dados registrados (base: NR-7): tipo de exame (admissional, periódico, retorno ao trabalho, mudança de risco ocupacional, demissional), data do exame, resultado (apto/inapto), médico responsável (nome, CRM e UF), riscos ocupacionais e procedimentos realizados. Esses dados alimentam RN-527 e RN-545.

### 8.7 Acesso e LGPD

- **RN-570** — São **dados sensíveis** do funcionário, visíveis apenas para usuários com permissão específica: salário e histórico salarial, dados bancários, raça/cor, ASOs e qualquer dado de saúde, documentos de justa causa.
- **RN-571** — A visualização e o download de dados ou documentos sensíveis são registrados em auditoria.
- **RN-572** — O usuário da empresa vê todos os dados dos funcionários da **própria** empresa, inclusive os sensíveis (a empresa é a empregadora e fornece esses dados). A restrição de RN-570 vale para a equipe do escritório. Quando houver perfis diferentes de usuário da empresa (RN-245), a mesma permissão de dados sensíveis será aplicada a eles.

### 8.8 Registro do atendimento e relatório de admissões

- **RN-550** — Toda admissão guarda, de forma permanente, os **responsáveis por cada etapa**:
  - **solicitada por** — usuário que solicitou a admissão (da empresa ou do escritório — RN-581) e data/hora;
  - **atendimento iniciado por** — colaborador que assumiu a admissão pela primeira vez (RN-592) e data/hora;
  - **admitido por** — colaborador que concluiu a admissão (RN-598) e data/hora.
- **RN-551** — Esses registros **não podem ser alterados** depois de gravados, nem por administradores. Se a admissão for transferida para outro colaborador (RN-592), quem iniciou o atendimento continua o mesmo; a transferência fica no histórico (RN-575), e quem concluir fica registrado como quem admitiu.
- **RN-552** — Os registros continuam válidos mesmo que o colaborador seja desativado depois (RN-233): o relatório continua mostrando o nome dele.
- **RN-553** — **Relatório de admissões por colaborador**, com uma linha por colaborador:
  - **atendimentos iniciados** — admissões que ele assumiu primeiro;
  - **admissões concluídas** — admissões em que ele consta como quem admitiu (**principal indicador**);
  - **devoluções feitas** — quantas vezes devolveu admissões à empresa (RN-595);
  - **tempo médio de atendimento** — da solicitação até a conclusão, e de quando assumiu até a conclusão.
- **RN-554** — Filtros do relatório: período (pela data de conclusão da admissão), empresa e colaborador. O período padrão é o mês atual.
- **RN-555** — Cada linha pode ser aberta para listar as admissões daquele colaborador no período: funcionário, empresa, cargo, data de admissão, data da solicitação, quem iniciou o atendimento, data da conclusão.
- **RN-556** — O relatório é acessível ao administrador e a usuários com permissão específica. **[A DEFINIR]** se o colaborador pode ver os próprios números, e se o relatório pode ser exportado (sugestão: CSV/Excel).

## 9. Área de trabalho mensal (competências)

O trabalho do escritório com cada empresa é organizado **mês a mês**. Cada mês de cada empresa é uma **competência**, com sua lista de itens a receber e a fazer (notas fiscais, despesas, extratos, certidões etc.). A **área de trabalho** é a tela em que o usuário trabalha uma empresa em uma competência.

### 9.1 Área de trabalho

- **RN-600** — Ao selecionar uma empresa, o sistema abre a área de trabalho dessa empresa na **competência padrão**. **[A DEFINIR]** se a competência padrão é o mês atual ou o mês anterior (na rotina contábil, em outubro normalmente se processa setembro).
- **RN-601** — Para trabalhar em outro mês/ano, o usuário **troca a competência** pelo seletor da área de trabalho. Tudo o que ele registrar (itens, documentos, conclusões) fica vinculado à competência selecionada.
- **RN-602** — A empresa e a competência selecionadas ficam **sempre visíveis** no topo da área de trabalho. Quando a competência selecionada não for a padrão, o sistema mostra um destaque visual (ex.: "Você está trabalhando em 03/2025"), para evitar lançamentos no mês errado.
- **RN-603** — Competências selecionáveis: do mês de início do atendimento da empresa (RN-401) até o mês atual. Competências futuras não podem ser abertas.
- **RN-604** — Cadastros permanentes (dados da empresa, sócios, estabelecimentos, funcionários) **não** pertencem a uma competência e são acessíveis em qualquer uma. Movimentos com data (admissões, desligamentos, documentos, itens mensais, obrigações) pertencem à competência em que ocorrem.
- **RN-605** — O painel da competência mostra:
  - progresso (itens aceitos ou concluídos / total);
  - itens pendentes, atrasados, recusados e aguardando avaliação, com prazo e responsável;
  - alertas de certidões positivas e de documentos vencidos ou a vencer (RN-653, RN-626);
  - documentos recebidos no mês;
  - movimentos de funcionários do mês (admissões e desligamentos, com seus prazos — seção 8);
  - situação da competência (RN-630).
- **RN-606** — O usuário também pode ver uma visão geral de todas as empresas em uma competência (ex.: quais empresas ainda não entregaram as notas de 09/2026). **[A DEFINIR]** se essa visão entra na primeira versão.

### 9.2 Catálogo de documentos do escritório

Cada escritório decide **quais documentos exige** das suas empresas. Um escritório pode exigir certidão negativa municipal todo mês, enquanto outro não exige; por isso o catálogo pertence ao escritório, e não à plataforma.

- **RN-610** — Cada escritório tem o seu **catálogo de documentos e tarefas mensais**. Cada tipo do catálogo tem:
  - **nome** (ex.: "CND Federal — Receita/PGFN", "CRF — FGTS", "CNDT — Trabalhista", "Extrato bancário", "Comprovantes de despesas");
  - **instruções para a empresa** — o que é o documento e onde obtê-lo (texto livre, pode conter links), exibidas no portal junto ao item;
  - **tipo**: `documento` (enviado pela empresa) ou `tarefa` (executada pelo escritório);
  - **formatos aceitos** (PDF, imagem, XML, planilha) e se aceita **mais de um arquivo** por mês;
  - **prazo padrão**: dia do mês seguinte à competência (ex.: dia 10 — o documento de 09/2026 vence em 10/10/2026);
  - **obrigatório** para o fechamento da competência (sim/não);
  - **exige data de validade** (sim/não) — para certidões e documentos com vencimento;
  - **é certidão** (sim/não) — habilita o registro do resultado da certidão na avaliação (RN-653);
  - **aproveitar documento ainda válido** (sim/não) — ver RN-656;
  - **aplicação**: a quais empresas o tipo se aplica — **todas**, **por regime tributário** (ex.: só Lucro Presumido e Real) ou **empresas selecionadas**;
  - **situação**: ativo/inativo.
- **RN-611** — Somente o administrador ou colaborador com permissão específica gerencia o catálogo.
- **RN-612** — Tipos do catálogo não são excluídos; são **inativados**. Inativar ou alterar um tipo afeta apenas as competências criadas **depois** da alteração; competências já criadas mantêm seus itens.
- **RN-613** — Ao criar um escritório, o sistema oferece um **catálogo inicial sugerido**, que o escritório pode aceitar, editar ou descartar por completo. **[A DEFINIR]** itens do catálogo sugerido (sugestão: notas fiscais de saída, notas fiscais de entrada, extratos bancários, comprovantes de despesas, CND Federal, CRF/FGTS, CNDT, CND estadual, CND municipal).
- **RN-614** — **[A DEFINIR]** se haverá tipos com periodicidade trimestral/anual. Nesta fase todos os tipos são mensais.

### 9.3 Rotina mensal da empresa

- **RN-615** — A **rotina mensal** de uma empresa é o conjunto dos tipos ativos do catálogo que se aplicam a ela (RN-610, "aplicação").
- **RN-616** — Na empresa, o escritório pode **ajustar** a rotina: dispensar um tipo para aquela empresa (com justificativa — ex.: "empresa sem inscrição estadual") e alterar o prazo ou a obrigatoriedade apenas para ela.
- **RN-617** — Um documento que só uma empresa precisa entregar é criado no catálogo com aplicação "empresas selecionadas". Assim todo item tem um tipo do catálogo, o que permite relatórios por tipo de documento em todas as empresas.
- **RN-618** — A tela da empresa mostra a rotina vigente: cada tipo, de onde vem (todas / regime / selecionada), prazo e obrigatoriedade, com os ajustes destacados.

### 9.4 Itens da competência

- **RN-620** — No **primeiro dia de cada mês**, o sistema cria a competência do mês para cada empresa `ativa`, com um **item** para cada tipo da sua rotina mensal. A criação não pode gerar duplicidade se executada mais de uma vez.
- **RN-621** — Competências passadas que ainda não existem (ex.: meses anteriores ao uso do sistema) são criadas quando acessadas pela primeira vez, com a rotina vigente.
- **RN-622** — Na competência aberta, o escritório pode incluir um item avulso (de um tipo do catálogo) ou remover um item gerado pela rotina (remover exige justificativa).
- **RN-623** — Situações de um item do tipo **documento**:
  - `pendente` — aguardando envio da empresa;
  - `em_avaliacao` — a empresa enviou o(s) arquivo(s) ou informou "sem movimento"; aguarda avaliação do escritório;
  - `aceito` — o escritório avaliou e considerou o documento correto e válido;
  - `recusado` — o escritório recusou, com motivo; a empresa deve reenviar;
  - `nao_se_aplica` — não há o que entregar no mês (ex.: "sem movimento" confirmado pelo escritório).
- **RN-624** — Situações de um item do tipo **tarefa**: `pendente`, `concluido`, `nao_se_aplica` (com justificativa).
- **RN-625** — **Atrasado** não é uma situação: é calculado quando o prazo passa e o item ainda está `pendente` ou `recusado`.
- **RN-626** — No envio, a empresa anexa o(s) arquivo(s) (seção 12) e, se o tipo exigir validade, informa a **data de validade** do documento. O sistema alerta documentos aceitos que estão vencidos ou a vencer. **[A DEFINIR]** antecedência do alerta.
- **RN-627** — Todo reenvio mantém os arquivos anteriores e suas avaliações no histórico do item; nada é sobrescrito.
- **RN-628** — Registrar quem e quando mudou a situação de cada item.

### 9.5 Avaliação do documento

- **RN-650** — Cada item `em_avaliacao` tem o botão **"Avaliar"**, disponível apenas para o escritório (administrador ou colaborador com permissão de avaliar). A empresa não avalia os próprios documentos.
- **RN-651** — A avaliação tem dois resultados:
  - **Aceitar** — o documento é o solicitado, está legível, pertence à empresa e é válido. O item passa a `aceito`.
  - **Recusar** — informa o motivo, escolhido de uma lista (documento ilegível, documento diferente do solicitado, documento de outra empresa, documento vencido, documento incompleto, outro) com complemento em texto. O item passa a `recusado` e a empresa é notificada com o motivo.
- **RN-652** — Se o tipo exige validade, ao aceitar o avaliador **confirma ou corrige a data de validade**. Não é possível aceitar documento já vencido na data da avaliação.
- **RN-653** — Se o tipo é certidão, ao aceitar o avaliador registra o **resultado da certidão**: `negativa`, `positiva com efeitos de negativa` ou `positiva`. Uma certidão **positiva** é aceita (o documento é válido), mas gera um **alerta para o escritório**, pois indica pendência da empresa no órgão emissor.
- **RN-654** — Quando a empresa informa "sem movimento", o avaliador **confirma** (item vai para `nao_se_aplica`) ou **recusa** (item vai para `recusado`, com motivo).
- **RN-655** — Cada avaliação fica registrada: avaliador, data/hora, resultado, motivo, validade e resultado da certidão. A empresa vê o resultado e o motivo; o nome do avaliador é exibido apenas para o escritório.
- **RN-656** — **Aproveitamento de documento válido:** quando o tipo tem essa opção ativa (RN-610) e o último documento aceito daquele tipo ainda é válido até o último dia da nova competência, o item já nasce `aceito`, vinculado a esse documento e identificado como "aceito por validade". Sem a opção, a empresa deve enviar o documento todo mês, mesmo que ainda válido.
- **RN-657** — **Fila de avaliação:** o escritório tem uma visão com todos os itens `em_avaliacao` das empresas que pode acessar, ordenada pela data de envio, com filtros por empresa, tipo de documento e competência. O avaliador pode aceitar ou recusar diretamente da fila.

### 9.6 Fechamento da competência

- **RN-630** — Situações da competência: `aberta`, `fechada`.
- **RN-631** — A competência só pode ser fechada quando todos os itens obrigatórios estiverem `aceito`, `concluido` ou `nao_se_aplica`.
- **RN-632** — Competência fechada é **somente leitura**: não aceita novos itens, documentos, avaliações ou mudanças de situação.
- **RN-633** — Reabrir uma competência fechada exige permissão específica e justificativa, e fica registrado em auditoria.
- **RN-634** — Competências não precisam ser fechadas em ordem; várias podem estar abertas ao mesmo tempo.

### 9.7 Participação da empresa cliente

- **RN-640** — O usuário da empresa vê a área de trabalho da sua empresa no portal, com a mesma lógica de competência (RN-600 a RN-603), mas apenas os itens do tipo `documento`. Tarefas internas do escritório não aparecem.
- **RN-641** — Para cada item, a empresa vê: nome do documento, **instruções** (RN-610), prazo, situação e, se recusado, o motivo. Ela envia o(s) arquivo(s) e o item passa a `em_avaliacao`.
- **RN-642** — A empresa pode informar **"sem movimento"** em um item, com observação; o item passa a `em_avaliacao` e segue RN-654.
- **RN-643** — Item `recusado` volta a aceitar envio; o reenvio leva o item novamente a `em_avaliacao`. Item `em_avaliacao` ou `aceito` não aceita novo envio pela empresa.
- **RN-644** — Em competência fechada (RN-632), a empresa não consegue enviar documentos.
- **RN-645** — A empresa é lembrada dos itens próximos do prazo, atrasados e recusados. Os lembretes são feitos pela cobrança diária de documentação (seção 16.1).
- **RN-646** — **Envio pelo escritório:** o escritório também pode enviar o documento de um item `pendente` ou `recusado` em nome da empresa (ex.: documento recebido por e-mail), pelo menu **"Documentos"** da empresa (lista dos documentos do mês, por competência, com os que a empresa enviou para avaliar ali mesmo) ou pelo botão de envio das ações pendentes da Página da Empresa. Quem envia precisa da permissão de avaliar documentos (RN-650). Como o próprio escritório conferiu o documento, o item **já passa a `aceito`**, sem ir para a fila de avaliação; as exigências do aceite continuam valendo (validade não vencida — RN-652; resultado da certidão — RN-653), e o aceite fica registrado no histórico de avaliações em nome de quem enviou (RN-655).

## 10. Notas fiscais (importação de XML)

A empresa envia os **arquivos XML** das suas notas fiscais. O sistema lê cada XML, identifica a nota, descobre se é de entrada ou de saída e a coloca **automaticamente na competência correta**, para que depois seja possível consultar "todas as notas de 09/2026" sem nenhuma classificação manual.

### 10.1 Envio

- **RN-700** — O portal da empresa tem uma área **"Notas fiscais"** para upload. O escritório também pode fazer upload em nome da empresa, pelo painel.
- **RN-701** — O upload aceita **vários arquivos de uma vez**: arquivos `.xml` individuais e arquivos `.zip` contendo XMLs. **[A DEFINIR]** limites de quantidade de arquivos e de tamanho por envio.
- **RN-702** — O usuário **não informa o mês** no upload: a competência de cada nota é definida pelo próprio XML (RN-720). Um mesmo envio pode conter notas de meses diferentes, e cada uma vai para a sua competência.
- **RN-703** — O processamento é feito em segundo plano. Ao final, o usuário vê um **relatório do envio**: quantas notas foram importadas (com a competência de cada uma), quantas eram duplicadas e quantas foram rejeitadas, com o motivo de cada rejeição.
- **RN-704** — Tipos de documento aceitos nesta fase: **NF-e (modelo 55)** e **NFC-e (modelo 65)**, no XML de distribuição (nota + protocolo de autorização), e os **XMLs de evento de cancelamento** dessas notas. **[A DEFINIR]** inclusão de CT-e (modelo 57) e NFS-e (padrão nacional).

### 10.2 Leitura e validação

- **RN-710** — De cada XML, o sistema extrai e guarda:
  - **chave de acesso** (44 dígitos), modelo, série, número;
  - **data e hora de emissão** e, quando houver, data de saída/entrada;
  - **tipo de operação** informado na nota (entrada ou saída);
  - **emitente**: CNPJ/CPF, razão social, inscrição estadual, UF;
  - **destinatário**: CNPJ/CPF, nome, UF;
  - natureza da operação, CFOPs presentes nos itens;
  - **valor total da nota** e principais totais (produtos, descontos, frete, ICMS, IPI);
  - **protocolo de autorização** (número, data/hora e código de situação).
- **RN-711** — **[A DEFINIR]** importação dos itens da nota (produto, NCM, CFOP, quantidade, valores e impostos por item). Nesta fase, guardar apenas o cabeçalho e os totais.
- **RN-712** — Um XML é **rejeitado** quando:
  - não é um XML válido, ou não é de um tipo aceito (RN-704);
  - a chave de acesso é inválida (dígito verificador);
  - a nota não tem protocolo de autorização, ou o protocolo indica que ela não foi autorizada (ex.: denegada, rejeitada);
  - **a empresa não é emitente nem destinatária** da nota (RN-713);
  - a competência da nota está fechada (RN-724).
- **RN-713** — A nota pertence à empresa quando o **CNPJ do emitente ou do destinatário** é o CNPJ de um dos **estabelecimentos** da empresa (matriz ou filial — RN-411). Se a nota envolver duas empresas clientes do mesmo escritório, ela é importada para cada uma, conforme o lado de cada empresa.
- **RN-714** — **Duplicidade:** a chave de acesso é única por empresa. Enviar novamente uma nota já importada não cria outra; ela aparece como "duplicada" no relatório do envio.
- **RN-715** — **[A DEFINIR]** se o sistema valida a assinatura digital do XML e/ou consulta a situação da nota na SEFAZ.

### 10.3 Classificação

- **RN-716** — Cada nota é classificada em relação à empresa:
  - **Saída** — a empresa é a emitente e a nota é de saída (vendas, remessas, devoluções a fornecedor...).
  - **Entrada (terceiros)** — a empresa é a destinatária de uma nota emitida por outro (compras, serviços com mercadoria, devoluções de clientes...).
  - **Entrada (emissão própria)** — a empresa é a emitente e a nota é de entrada (ex.: importação, devolução recebida, entrada de produtor rural).
- **RN-717** — Ao ser importada, a nota é vinculada ao item correspondente da rotina mensal da competência (ex.: "Notas fiscais de saída", "Notas fiscais de entrada" — RN-615), que passa para `em_avaliacao` (RN-641) e segue a avaliação normal (seção 9.5). Se a rotina não tiver o item, a nota é importada normalmente e fica visível na competência.

### 10.4 Competência da nota

- **RN-720** — A competência é definida automaticamente:
  - **Saída** e **entrada (emissão própria)**: mês/ano da **data de emissão**.
  - **Entrada (terceiros)**: mês/ano da **data de emissão**. **[A DEFINIR]** se deve usar a data de entrada efetiva da mercadoria na empresa (usada na escrituração fiscal), que nem sempre consta no XML.
- **RN-721** — A data usada é a que consta no XML, no fuso horário informado na própria nota (ex.: uma nota emitida em 30/09/2026 às 23h30 no horário de Brasília é de 09/2026, mesmo que em UTC já seja 01/10).
- **RN-722** — O escritório pode **mover** uma nota para outra competência, com justificativa (ex.: nota recebida em outubro com emissão em setembro e escriturada em outubro). A competência original e a mudança ficam no histórico. A empresa não pode mover notas.
- **RN-723** — Notas com emissão anterior ao início do atendimento (RN-401) são importadas com alerta, e a competência é criada sob demanda (RN-621). **[A DEFINIR]** se devem ser bloqueadas.
- **RN-724** — Se a competência da nota estiver **fechada** (RN-632), a nota não é importada: aparece no relatório como rejeitada ("competência 08/2026 fechada — fale com o escritório") e o escritório é notificado, podendo reabrir a competência (RN-633) e importar.

### 10.5 Cancelamento

- **RN-730** — Ao receber o XML do evento de cancelamento de uma nota já importada, a nota passa à situação **cancelada**, com data/hora e protocolo do cancelamento. Ela continua na sua competência (não é excluída), mas é sinalizada e não entra nos totais.
- **RN-731** — Se o cancelamento chegar antes da nota, ele é guardado e aplicado quando a nota for importada.
- **RN-732** — Situações da nota: `autorizada`, `cancelada`.

### 10.6 Consulta

- **RN-740** — A consulta de notas parte da **competência**: na área de trabalho (seção 9), a aba "Notas fiscais" mostra as notas da empresa na competência selecionada, separadas em saídas e entradas.
- **RN-741** — Filtros: competência (ou período de emissão), classificação (saída/entrada), situação (autorizada/cancelada), emitente/destinatário (CNPJ ou nome), número, chave de acesso, CFOP, faixa de valor, modelo (NF-e/NFC-e).
- **RN-742** — Totais da consulta: quantidade de notas e soma do valor total, separados por saídas e entradas, desconsiderando notas canceladas.
- **RN-743** — Ações sobre o resultado: baixar o XML de uma nota e **baixar todos os XMLs do resultado em um `.zip`** (ex.: todas as notas de saída de 09/2026). Ao lado do XML, o botão **"Ver nota"** abre em nova aba o **DANFE** (NF-e) ou a **DANFCE** (NFC-e) em PDF, montado a partir do XML guardado; nota cancelada sai marcada como cancelada (RN-730). Vale para o escritório e para a empresa, cada um com acesso só às notas que já pode consultar (RN-744).
- **RN-744** — A empresa consulta apenas as próprias notas, no portal, com os mesmos filtros. O escritório consulta por empresa e, opcionalmente, todas as empresas em uma competência.

### 10.7 Guarda

- **RN-750** — O arquivo XML original é guardado **exatamente como foi recebido**, sem alteração, e não pode ser substituído nem excluído (é o documento fiscal com validade jurídica). Prazo de guarda conforme RN-903.
- **RN-751** — Ficam registrados, para cada nota: quem enviou, quando, por qual envio (lote) e o nome original do arquivo.

## 11. Obrigações e calendário fiscal

- **RN-800** — Obrigações são geradas por competência, conforme regime tributário e configurações da empresa, e aparecem na área de trabalho da competência (seção 9).
- **RN-801** — Vencimento que cair em fim de semana ou feriado segue a regra da própria obrigação (antecipa ou posterga) — cada tipo de obrigação define seu comportamento.
- **RN-802** — Status de uma obrigação: `pendente`, `em_andamento`, `entregue`, `dispensada`, `atrasada`. `atrasada` é calculado quando passa do vencimento sem entrega.
- **RN-803** — Marcar como `entregue` exige data de entrega e, quando aplicável, comprovante anexado.
- **RN-804** — `dispensada` exige justificativa.
- **RN-805** — **[A DEFINIR]** lista inicial de tipos de obrigação e regras de geração por regime.

## 12. Documentos

- **RN-900** — Documentos pertencem a uma empresa cliente e, opcionalmente, a uma competência, a uma obrigação ou a um funcionário (seção 8.6).
- **RN-901** — Tipos aceitos inicialmente: PDF, XML, imagens (JPG/PNG), planilhas. **[A DEFINIR]** tamanho máximo por arquivo.
- **RN-902** — Documentos não são apagados fisicamente; exclusão é lógica e registrada em auditoria.
- **RN-903** — Prazo de guarda segue a legislação aplicável (mínimo de 5 anos para documentos fiscais).
- **RN-904** — Todo documento registra **quem enviou** (empresa ou escritório) e se é **visível para a empresa**. Documentos enviados pela empresa e documentos de funcionários são sempre visíveis para ela. Documentos gerados pelo escritório (ex.: guias, relatórios) são visíveis quando o escritório os publica para a empresa; documentos internos do escritório nunca são visíveis.

## 13. Certificados digitais e credenciais

- **RN-1000** — O escritório pode armazenar certificado digital A1 das empresas clientes; o sistema alerta **30 dias** antes do vencimento.
- **RN-1001** — Senhas de certificados e de portais governamentais são armazenadas criptografadas e nunca exibidas em listagens ou logs.

## 14. Tarefas

- **RN-1100** — Tarefas podem estar vinculadas a uma empresa cliente e/ou obrigação, têm responsável, prazo e status.
- **RN-1101** — Tarefas recorrentes mensais são tipos `tarefa` do catálogo do escritório (RN-610) e entram na rotina mensal. Esta seção trata das tarefas avulsas.

## 15. Honorários (financeiro do escritório)

- **RN-1200** — Cada empresa cliente tem um contrato com valor de honorário mensal e dia de vencimento, informados na aba "Cobrança" do cadastro da empresa. O valor deve ser maior que zero e o dia de vencimento vai de 1 a 28 (para valer em todos os meses). No cadastro, a vigência começa na data de início do atendimento.
- **RN-1201** — Reajuste de honorário mantém histórico com vigência: o novo valor vale a partir do dia da alteração e o anterior fica registrado até a véspera, com quem registrou. Alterações no mesmo dia corrigem a vigência do dia, sem abrir outra. O histórico aparece abaixo dos campos.
- **RN-1202** — **[A DEFINIR]** geração de cobrança (boleto/Pix) e integração bancária.
- **RN-1203** — Valores monetários em reais, com duas casas decimais.

## 16. Notificações

- **RN-1300** — O sistema notifica (e-mail e, futuramente, push no app): obrigações próximas do vencimento, prazos de admissão e desligamento (RN-522, RN-529, RN-544), certificados vencendo e honorários em aberto.
- **RN-1301** — **[A DEFINIR]** antecedência padrão dos avisos e se o escritório pode configurá-la.

### 16.1 Cobrança diária de documentação

Rotina automática que, todos os dias, envia a cada empresa a lista do que ela ainda deve entregar ao escritório.

- **RN-1310** — **Competência cobrada:** a cobrança se refere à **competência de referência**, que é o **mês anterior** ao da data de envio (em 15/10/2026, cobram-se os documentos de 09/2026), porque os documentos de um mês são entregues no mês seguinte (RN-610, prazo). Itens em atraso de competências anteriores ainda abertas também entram, em um bloco separado.
- **RN-1311** — **Data de corte:** dia do mês a partir do qual a cobrança começa a ser enviada. Antes desse dia, a empresa não recebe cobrança. A partir dele, recebe uma vez por dia enquanto houver pendências.
  - O escritório define a data de corte **padrão** (ex.: dia 5).
  - Cada empresa pode ter uma data de corte **própria**, que substitui a padrão (ex.: empresa que só fecha o caixa no dia 8).
  - Valores aceitos: 1 a 28, para valer em todos os meses.
  - **[A DEFINIR]** data de corte padrão sugerida ao criar o escritório (sugestão: dia 5).
- **RN-1312** — **Horário e dias:** a rotina roda uma vez por dia, às **08:00 (horário de Brasília)**. **[A DEFINIR]** se envia apenas em dias úteis ou todos os dias, e se o escritório pode escolher o horário.
- **RN-1313** — **O que é cobrado:** itens do tipo `documento` nas situações `pendente` ou `recusado`. Itens `em_avaliacao`, `aceito` e `nao_se_aplica` não entram (a empresa já fez a sua parte ou não há o que entregar).
- **RN-1314** — **Quem é cobrado:** empresas `ativas`, com cobrança automática habilitada (RN-1317), que tenham pelo menos um item cobrável. **Não se envia e-mail sem pendências.**
- **RN-1315** — **Conteúdo da cobrança**, um único e-mail por empresa por dia:
  - nome da empresa e competência de referência;
  - para cada item: nome do documento, prazo, situação — "pendente", "pendente — atrasado há N dias" ou "recusado: motivo";
  - bloco separado com atrasados de competências anteriores (RN-1310);
  - link direto para a área de envio no portal (RN-641) e as instruções de cada documento (RN-610).
- **RN-1316** — **Destinatários:** todos os usuários da empresa `ativos` e vinculados a ela (seção 5.5). **[A DEFINIR]** se o escritório pode escolher destinatários específicos por empresa (ex.: só o responsável financeiro) e incluir o e-mail do contato responsável (RN-407) mesmo que ele não seja usuário.
- **RN-1317** — **Controle pelo escritório:**
  - liga/desliga a cobrança automática para o escritório inteiro;
  - **suspende a cobrança de uma empresa** por um período (ex.: empresa em negociação), com motivo e data de retorno; ao fim do período, a cobrança volta automaticamente.
- **RN-1318** — Empresa ativa **sem nenhum destinatário** (nenhum usuário da empresa ativo) não recebe cobrança, e isso aparece como alerta para o escritório ("cobrança não enviada: empresa sem usuário").
- **RN-1319** — **Histórico da cobrança:** cada envio fica registrado (data/hora, destinatários, itens incluídos, resultado do envio) e é visível para o escritório na tela da empresa. Os itens mostram quantas vezes foram cobrados.
- **RN-1320** — A rotina **nunca envia duas cobranças à mesma empresa no mesmo dia**, mesmo se executada mais de uma vez.
- **RN-1321** — O escritório pode **enviar uma cobrança manualmente** para uma empresa, a qualquer momento, com o mesmo conteúdo. O envio manual fica registrado no histórico e não impede a cobrança automática do dia.

## 17. Auditoria e LGPD

- **RN-1400** — Toda criação, alteração e desativação/exclusão lógica de escritório, usuário, perfil, empresa, sócio, funcionário, contrato, documento, obrigação e honorário é registrada (usuário, data/hora, IP, valores anteriores e novos).
- **RN-1401** — Dados pessoais são tratados conforme a LGPD; o escritório é o controlador e a plataforma, operadora.
- **RN-1402** — Titular pode solicitar exportação de seus dados pessoais. Exclusão só é possível quando não houver obrigação legal de guarda.

---

## 18. Tela inicial (dashboards)

### 18.1 Tela inicial por tipo de usuário

- **RN-1500** — A tela inicial após o login depende do tipo de usuário:
  - **Administrador** — dashboard do escritório (seção 18.2).
  - **Colaborador** — por enquanto, **sem indicadores**: apenas a tela de boas-vindas com o menu de navegação.
  - **Usuário da empresa** — portal da empresa (RN-304). **[A DEFINIR]** conteúdo da tela inicial do portal.

### 18.2 Dashboard do administrador

- **RN-1510** — **Indicador "Empresas"**: total de empresas clientes do escritório, com a divisão por situação (`ativa`, `suspensa`, `encerrada`). Clicar abre a lista de empresas filtrada pela situação escolhida.
- **RN-1511** — **Indicador "Empresas devendo documentação"**: quantidade de empresas `ativas` com pelo menos um documento **em atraso** (RN-625: prazo vencido e item `pendente` ou `recusado`) em qualquer competência aberta.
- **RN-1512** — **Lista "Empresas devendo documentação"**, com uma linha por empresa:
  - nome da empresa;
  - quantidade de documentos em atraso (e quantos deles são obrigatórios);
  - quantidade de documentos recusados aguardando reenvio;
  - competência mais antiga com atraso;
  - dias de atraso do documento mais antigo.
- **RN-1513** — A lista é ordenada pelo **maior atraso** (dias do documento mais antigo). Cada linha pode ser expandida para mostrar quais documentos estão em atraso (tipo, competência, prazo, situação), e clicar na empresa abre a sua área de trabalho na competência mais antiga com atraso (seção 9).
- **RN-1514** — Filtros da lista: competência (todas as abertas ou uma específica), tipo de documento do catálogo e "somente obrigatórios".
- **RN-1515** — Os números são calculados no momento do acesso: um documento enviado pela empresa (item `em_avaliacao`) deixa de contar como "devendo" imediatamente, mesmo antes da avaliação. Documentos aguardando avaliação não são dívida da empresa, e sim trabalho do escritório.
- **RN-1516** — O dashboard considera todas as empresas do escritório, já que o administrador tem acesso total.

### 18.3 Página da Empresa (painel da empresa selecionada)

- **RN-1520** — Ao selecionar uma empresa (botão "Página da Empresa" na lista de empresas), o escritório vê primeiro o painel da empresa, com indicadores e a lista de **ações pendentes**, agrupada por assunto. Cada linha mostra o que é, a data (prazo ou vencimento), a situação e **quem precisa agir** (empresa ou escritório). Atrasos aparecem em destaque.
- **RN-1521** — **Documentos:** itens de competências abertas que a empresa deve (`pendente` ou `recusado` — mesma regra da cobrança, RN-1313), e, separados, os que dependem do escritório: documentos `em_avaliacao` (com link para a fila de avaliação já filtrada pela empresa) e tarefas mensais `pendente`.
- **RN-1522** — **Funcionários:** admissões em `rascunho` ou `devolvida` (empresa age), em `em_analise` (escritório age) e desligamentos em andamento, com link para o cadastro do funcionário.
- **RN-1523** — **Contas a pagar:** despesas vencidas e não pagas (com o valor total) e despesas não pagas que vencem nos próximos **7 dias**.
- **RN-1524** — Os números são calculados no momento do acesso, como no dashboard do administrador (RN-1515).
- **RN-1525** — As ações pendentes ficam em **uma única tabela** (assunto, pendência, prazo/vencimento, situação, quem age), das atrasadas para as demais, e cada linha termina com o botão que resolve a pendência: **Cobrar empresa** (documento devido — envio manual da cobrança, RN-1321), **Avaliar** (abre a fila de avaliação filtrada pela empresa), **Concluir tarefa**, **Abrir cadastro** (funcionário) e **Registrar pagamento** (conta a pagar — RN-1605, sem sair da página).

## 19. Despesas da empresa

- **RN-1600** — Na tela da empresa, o escritório cadastra as **despesas** da empresa cliente, com **descrição**, **valor** e **data de vencimento** (todos obrigatórios). Fica registrado quem cadastrou.
- **RN-1601** — O valor é em reais, com duas casas decimais, e deve ser maior que zero.
- **RN-1602** — Na equipe do escritório, cadastrar, alterar e excluir despesas exige a atribuição "Despesas". A lista mostra o total das despesas exibidas e filtra por período de vencimento.
- **RN-1603** — Excluir uma despesa é exclusão lógica: ela some da lista, mas o registro é preservado.
- **RN-1604** — **[A DEFINIR]** recorrência mensal, estorno de pagamento registrado por engano e remoção de documento anexado por engano.
- **RN-1605** — **Pagamento:** o escritório registra o pagamento informando **valor pago** e **data do pagamento** (o formulário já vem com o valor da despesa e a data de hoje). O valor pago deve ser maior que zero e pode ser diferente do valor da despesa (juros, desconto). A data não pode ser futura. O pagamento é registrado uma única vez e guarda quem registrou e quando. A situação da despesa é calculada: **paga** (com pagamento), **vencida** (sem pagamento e vencimento passado) ou **em aberto**.
- **RN-1606** — **Documentos da despesa:** no cadastro ou na alteração, pode-se anexar vários documentos à despesa, cada um de um tipo: **Boleto**, **Recibo**, **Nota Fiscal** ou **Cheque**. Cada arquivo precisa respeitar os formatos e o tamanho máximo configurados para o seu tipo (RN-1610), conferidos também no servidor. Os documentos ficam disponíveis na lista ("Documentos"), com quem enviou e quando. Os boletos anexados antes desta regra viraram documentos do tipo Boleto.
- **RN-1607** — **Despesas pelo portal:** o usuário da empresa tem o menu "Despesas" no portal e cadastra, altera, exclui e registra o pagamento das despesas das empresas a que está vinculado (RN-240), com as mesmas regras do escritório. Escritório e empresa veem e mantêm a mesma lista; fica registrado quem cadastrou cada despesa.
- **RN-1608** — **Despesa já paga:** no cadastro (pelo escritório ou pela empresa), a despesa pode ser marcada como "já paga", informando valor pago e data do pagamento (o formulário sugere o valor da despesa e a data de hoje). O pagamento segue as mesmas regras de RN-1605 e é registrado em nome de quem cadastrou.
- **RN-1609** — **Categoria:** toda despesa pertence a uma categoria (obrigatória). As categorias são do escritório e só o administrador as cadastra, em Configurações → Categorias de despesa. Não há duas categorias com o mesmo nome. Categoria não é excluída, é inativada: some das novas despesas, mas as despesas antigas continuam com ela. A lista de despesas mostra e filtra pela categoria.
- **RN-1610** — **Configuração dos documentos:** em Configurações → Escritório e cobrança, o administrador define, para cada tipo de documento de despesa, os formatos aceitos (PDF, imagem, XML, planilha) e o tamanho máximo (de 1 a 50 MB). Enquanto não configurar, vale: PDF ou imagem, até 10 MB (Nota Fiscal aceita também XML).

---

## Futuro (fora do escopo atual)

- Dashboard do colaborador (ex.: suas admissões em análise, fila de avaliação, empresas da sua carteira) — ver RN-1500.
- Novas atribuições de colaborador (ex.: obrigações fiscais, honorários) — ver RN-221.
- Perfis diferentes para usuários da empresa (sócio, RH, financeiro...) — ver RN-245.
- Afastamentos, férias e folha de pagamento.
- Integração direta com o eSocial.
- Aplicativos móveis.

## Pendências de definição

| Código | Assunto |
|---|---|
| RN-003 | Conteúdo da landing page |
| RN-102 | Como um escritório é cadastrado |
| RN-203 | CPF/telefone obrigatórios no usuário |
| RN-212 | Mais de um administrador por escritório |
| RN-226 | Colaborador vê todas as empresas ou só sua carteira |
| RN-242 | Usuário da empresa pode convidar outros da sua empresa |
| RN-307 | Autenticação em dois fatores |
| RN-406 | Consulta automática de CNPJ |
| RN-415 | Lotações tributárias (só se houver cálculo de folha) |
| RN-520 | Matrícula automática ou informada; categorias aceitas |
| RN-521 | Outros tipos de contrato (intermitente, temporário) |
| RN-522 | Antecedência do alerta de fim de experiência |
| RN-524 | Salário abaixo do mínimo: alertar ou bloquear |
| RN-526 | Checklist de admissão personalizável |
| RN-548 | Reversão de desligamento concluído |
| RN-549 | Cálculo das verbas rescisórias |
| RN-556 | Colaborador vê os próprios números; exportação do relatório |
| RN-563 | Prazos de guarda de documentos trabalhistas |
| RN-578 | Desligamento solicitado pela empresa pelo portal |
| RN-589 | Antecedência mínima para envio da admissão |
| RN-599 | Documentos gerados pelo escritório na conclusão da admissão |
| RN-600 | Competência padrão: mês atual ou mês anterior |
| RN-606 | Visão geral de todas as empresas na primeira versão |
| RN-613 | Itens do catálogo inicial sugerido |
| RN-614 | Tipos com periodicidade trimestral/anual |
| RN-626 | Antecedência do alerta de certidões |
| RN-701 | Limites de quantidade e tamanho por envio de XML |
| RN-704 | Inclusão de CT-e e NFS-e |
| RN-711 | Importação dos itens da nota |
| RN-715 | Validação de assinatura e consulta à SEFAZ |
| RN-720 | Competência da nota de entrada: emissão ou entrada efetiva |
| RN-723 | Notas anteriores ao início do atendimento: alertar ou bloquear |
| RN-805 | Tipos de obrigação e regras de geração |
| RN-901 | Tamanho máximo de arquivo |
| RN-1202 | Cobrança e integração bancária |
| RN-1301 | Antecedência dos avisos |
| RN-1311 | Data de corte padrão sugerida |
| RN-1312 | Cobrança só em dias úteis; horário configurável |
| RN-1316 | Destinatários específicos por empresa |
| RN-1500 | Tela inicial do portal da empresa |
| RN-1604 | Despesas: recorrência, estorno de pagamento, remoção de documento anexado por engano |

## Fontes de referência

Consultadas em 10/2026. Antes de implementar cada módulo, conferir a versão vigente dos leiautes.

- [Leiautes do eSocial — documentação técnica oficial (gov.br)](https://www.gov.br/esocial/pt-br/documentacao-tecnica)
- [S-2200 — Cadastramento inicial do vínculo e admissão (leiaute campo a campo)](https://documentacao.senior.com.br/gestao-de-pessoas-hcm/esocial/leiautes/nao-periodicos/s-2200.htm)
- [S-2299 — Desligamento (leiaute campo a campo)](https://documentacao.senior.com.br/gestao-de-pessoas-hcm/esocial/leiautes/nao-periodicos/s-2299.htm)
- [S-1000 — Informações do empregador](https://documentacao.senior.com.br/seniorxplatform/manual-do-usuario/hcm/folha-x/esocial/tabelas-eventos-iniciais/s-1000)
- [S-1005 — Tabela de estabelecimentos](https://documentacao.senior.com.br/gestao-de-pessoas-hcm/esocial/leiautes/tabelas/s-1005.htm)
- [Tabela 19 — Motivos de desligamento](https://suporte.quarta.com.br/LayOuts/eSocial/Tabelas/Tabela_19.htm)
- [Portaria MTP 671/2021 — registro de empregados e prazos (art. 14 e 15)](https://www.normaslegais.com.br/legislacao/portaria-mtp-671-2021.htm)
