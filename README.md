# contab-admin

Sistema de gestão para escritórios de contabilidade (Laravel 13 + Filament 5 + PostgreSQL, em Docker).

- Regras de negócio: [regras.md](regras.md)
- Regras de desenvolvimento: [AGENTS.md](AGENTS.md)
- Descrição funcional para leitura do negócio: [docs/funcionalidades-fase-1.pdf](docs/funcionalidades-fase-1.pdf)

## Subir o ambiente local

```bash
docker compose up -d
```

| Endereço | O quê |
|---|---|
| http://localhost:8000 | Página inicial e login (`/login`) |
| http://localhost:8000/admin | Painel do escritório |
| http://localhost:8000/portal | Portal da empresa |
| http://localhost:8025 | MailHog — e-mails capturados (somente local) |

## Usuários de exemplo (senha `123`, somente local)

| E-mail | Tipo |
|---|---|
| admin@contab.test | Administrador titular do Escritório Demo |
| fernanda@contab.test | Administradora |
| colaborador@contab.test | Colaborador (Bruno) |
| daniela@contab.test | Colaboradora |
| empresa@contab.test | Usuária da Empresa Demo **e** da Padaria (troca de empresa no portal) |
| antonio@padaria.test · marcos@alicerce.test · helena@bemestar.test | Usuários de empresas |
| admin2@contab.test | Administrador de outro escritório (para conferir o isolamento) |

Recriar os dados de exemplo: `docker compose exec app php artisan migrate:fresh --seed`

## Comandos úteis

```bash
docker compose exec app ./vendor/bin/pest                       # testes
docker compose exec app ./vendor/bin/pint                       # padronização do código
docker compose exec app php artisan cobranca:documentacao-diaria --simular   # cobrança diária (sem enviar)
docker compose exec app php artisan cobranca:documentacao-diaria             # envia (e-mails no MailHog)
docker compose exec app php artisan competencias:gerar-mensal                # geração do mês
```
