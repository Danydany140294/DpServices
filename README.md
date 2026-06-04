# DP Services

Application de gestion de ménage — Symfony 7 + PostgreSQL + Docker

## Stack technique
- Symfony 7
- PostgreSQL 16
- Redis 7
- Nginx
- Mailpit (emails dev)
- Docker / Docker Compose

## Lancer le projet

```bash
cp .env.example .env
docker compose up -d
docker compose exec php php bin/console doctrine:database:create
docker compose exec php php bin/console doctrine:migrations:migrate
```

## Accès
- App : http://localhost:8080
- Emails : http://localhost:8025