# Production quick reference

## Public checks

```bash
curl --fail --show-error https://your-domain.example/api/health
curl --include https://your-domain.example/api/auth/me
```

The second request should return `401 Unauthorized` when no session cookie is
provided.

## Hostinger Docker Manager

Project name:

```text
family-plan
```

Useful commands from the project directory or its Hostinger terminal:

```bash
docker compose ps
docker compose logs --tail=200
docker compose logs --follow php
docker compose logs --follow edge
docker compose exec php php bin/console about
docker compose exec php php bin/console doctrine:migrations:status
docker compose exec database pg_isready
```

## Manual database backup

```bash
docker compose exec -T database \
    pg_dump --username=family_plan --dbname=family_plan \
    | gzip > "family-plan-$(date -u +%Y%m%dT%H%M%SZ).sql.gz"
```

Automatic dumps are stored in the `database_backups` Docker volume:

```bash
docker compose exec database-backup ls -lah /backups
```

## Restore a dump

Stop application writes first, then run:

```bash
gzip --decompress --stdout backup.sql.gz \
    | docker compose exec -T database \
        psql --username=family_plan --dbname=family_plan
```

## Application maintenance

```bash
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec php php bin/console cache:clear --env=prod
docker compose exec php php bin/console app:create-super-admin
```

## Deployment configuration

- Workflow: `.github/workflows/deploy-hostinger.yml`
- Runtime: `docker-compose.hostinger.yml`
- Setup guide: `HOSTINGER_DEPLOYMENT.md`
- Local template: `.env.prod`
- Local validation: `./verify-deployment.sh .env.prod.local`

Do not run `docker compose down --volumes` in production. It removes the
database, certificates, and local backup volumes.
