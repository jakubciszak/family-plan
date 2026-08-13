# Production deployment summary

Family Plan is deployed from GitHub Actions to Hostinger Docker Manager.

## Delivery flow

1. Validate the production Compose file.
2. Build PHP, Nginx, and React images on GitHub-hosted runners.
3. Publish public GHCR images tagged with the full commit SHA.
4. Deploy the exact SHA through the official Hostinger GitHub Action.
5. Wait for `/api/health` to confirm that Symfony and PostgreSQL are ready.

## Production properties

- Caddy terminates TLS and renews certificates automatically.
- The frontend and API share one origin.
- PHP, backend Nginx, and PostgreSQL have no public port mappings.
- Database data, TLS data, and backups use named volumes.
- Doctrine migrations and super-admin bootstrap run during PHP startup.
- A compressed PostgreSQL dump is created daily with configurable retention.
- Container logs rotate to avoid unbounded disk usage.

The complete one-time setup and rollback procedure is documented in
[HOSTINGER_DEPLOYMENT.md](HOSTINGER_DEPLOYMENT.md). Operational commands are in
[QUICK_REFERENCE.md](QUICK_REFERENCE.md).
