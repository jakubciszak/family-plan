# Production deployment checklist

## One-time setup

- [ ] Domain A record points to the Hostinger VPS.
- [ ] TCP 80/443 and UDP 443 are available on the VPS.
- [ ] The three GHCR packages are public.
- [ ] `jakubciszak/family-plan` has write access under each package's
      "Manage Actions access" setting.
- [ ] The GitHub `production` environment contains every required secret and
      variable from `HOSTINGER_DEPLOYMENT.md`.
- [ ] Hostinger VPS uses the Docker template.

## Pipeline

- [ ] "Validate deployment configuration" passes.
- [ ] All three images are published with the same commit SHA.
- [ ] Hostinger accepts the `family-plan` Docker project.
- [ ] The public health check succeeds.

## Runtime

```bash
curl --fail --show-error https://your-domain.example/api/health
curl --include https://your-domain.example/api/auth/me
```

- [ ] `/api/health` returns HTTP 200 with `{"status":"ok"}`.
- [ ] Unauthenticated `/api/auth/me` returns HTTP 401.
- [ ] The TLS certificate is valid for the configured domain.
- [ ] Login works with the configured super-admin account.
- [ ] A session survives page refresh.
- [ ] A database write survives a full container restart.
- [ ] `database`, `backend`, and `frontend` report healthy.
- [ ] No database or PHP port is published on the VPS.

## Database and backups

```bash
docker compose exec php php bin/console doctrine:migrations:status
docker compose exec database-backup ls -lah /backups
```

- [ ] All Doctrine migrations are applied.
- [ ] At least one compressed SQL dump exists.
- [ ] A dump has been restored in a disposable environment.
- [ ] Hostinger off-server VPS backups are enabled.

## Rollback drill

- [ ] The last healthy Git commit SHA is recorded.
- [ ] Deploying that SHA through `IMAGE_TAG` restores the previous revision.
- [ ] The rollback leaves `database_data` and `database_backups` intact.
