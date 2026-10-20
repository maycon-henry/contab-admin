# ADR 0001 — Ambiente de homologação no Render

- **Situação:** aceita
- **Data:** 2026-10-19

## Contexto

O sistema roda localmente em Docker (`app` + `db`, AGENTS.md 2.1). É preciso um ambiente acessível pela internet
para testes, com deploy disparado manualmente (não a cada push).

## Decisão

- Hospedagem no **Render**, descrita em `render.yaml` (Blueprint): um *web service* Docker e um PostgreSQL 17,
  ambos no plano **gratuito**, região `virginia` (o Render não tem região no Brasil).
- Imagem própria em `docker/render/Dockerfile`: código dentro da imagem, `composer install --no-dev`, assets
  compilados, processos como `www-data` (sem root). Um único container mantém, via Supervisor, PHP-FPM, Nginx,
  fila e agendador — o mesmo desenho do container `app`.
- Migrations e o `PermissoesSeeder` rodam no início do container (`docker/render/entrypoint.sh`), pois o plano
  gratuito não oferece *pre-deploy command*.
- **Deploy sob demanda:** `autoDeployTrigger: off` no Render. O deploy é disparado pelo workflow manual
  `.github/workflows/deploy-render.yml`, que roda Pint e Pest e, se passarem, chama o *Deploy Hook* do serviço
  (secret `RENDER_DEPLOY_HOOK_URL`). Também é possível clicar em "Manual Deploy" no painel do Render.
- E-mail em homologação: `MAIL_MAILER=log` (as mensagens aparecem nos logs do Render).

## Consequências e limitações do plano gratuito

- O serviço **hiberna após 15 min sem acesso**; enquanto dorme, fila e agendador (cobrança diária, geração
  mensal) não rodam. A primeira requisição depois disso leva cerca de 1 minuto.
- **Sistema de arquivos efêmero:** XMLs e documentos enviados são perdidos a cada deploy, reinício ou hibernação.
  Isso é incompatível com RN-750 — **não usar com dados reais**.
- **PostgreSQL gratuito expira 30 dias após a criação** (1 GB, sem backup). Depois disso precisa ser recriado
  ou migrado para um plano pago.
- Dados ficam nos EUA. Para dados reais de clientes, avaliar LGPD antes.
- Sem o `DesenvolvimentoSeeder` (só roda em `local`/`testing`), o banco sobe sem usuários.

Para produção: plano pago com disco persistente ou storage S3 privado (AGENTS.md 7), Postgres pago com backup,
e a avaliação de LGPD acima.
