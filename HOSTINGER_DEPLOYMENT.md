# Hostinger production deployment

The production deployment is fully driven by GitHub Actions. A push to `main`:

1. validates `docker-compose.hostinger.yml`;
2. builds the PHP, backend Nginx, and React images;
3. publishes immutable images tagged with the commit SHA to GHCR;
4. asks Hostinger Docker Manager to replace the `family-plan` project;
5. waits until the public `/api/health` endpoint confirms that Symfony and
   PostgreSQL are ready.

The Hostinger step uses the official `hostinger/deploy-on-vps` action pinned to
the commit behind its `v2` tag.

## Runtime architecture

| Service | Public | Purpose |
| --- | --- | --- |
| `edge` | ports 80, 443/tcp, 443/udp | Caddy, automatic HTTPS and certificate renewal |
| `frontend` | no | React SPA and same-origin `/api/*` proxy |
| `backend` | no | Nginx FastCGI gateway |
| `php` | no | Symfony PHP-FPM, migrations, super-admin bootstrap |
| `database` | no | PostgreSQL 16 |
| `database-backup` | no | Daily compressed `pg_dump`, seven-day retention by default |

Only Caddy is attached directly to public ports. PostgreSQL and PHP are on an
internal Docker network.

## 1. Prepare the Hostinger VPS

- Use a Hostinger VPS with the Docker template installed.
- Point the domain's A record to the VPS IPv4 address. Add an AAAA record only
  if IPv6 is configured on the VPS.
- Make sure TCP ports 80 and 443 and UDP port 443 are allowed and are not used
  by another project.
- Generate an API key in hPanel under account API settings.
- Copy the VPS ID from the hPanel URL or the default hostname
  (`srv123456.hstgr.cloud` means VM ID `123456`).

For an initial deployment by IP without TLS, set `APP_SCHEME=http` and
`APP_DOMAIN` to the VPS IP address. A real domain with HTTPS is the production
setup.

## 2. Give the workflow access to existing GHCR packages

The three images already exist as public packages. GitHub Actions must be
granted write access once for each package:

- `family-plan-php`
- `family-plan-nginx`
- `family-plan-frontend`

Open the package settings, go to "Manage Actions access", add
`jakubciszak/family-plan`, and grant write access. Keep the packages public so
Hostinger can pull them without storing a GitHub token on the VPS.

This fixes `permission_denied: write_package`. The workflow already declares
`packages: write`; changing that YAML permission alone cannot repair an
existing package that is not connected to the repository.

## 3. Configure the GitHub `production` environment

Create or open:

`Repository settings -> Environments -> production`

Add these secrets:

| Secret | Required | Value |
| --- | --- | --- |
| `HOSTINGER_API_KEY` | yes | API key generated in hPanel |
| `APP_SECRET` | yes | random hex value, at least 32 bytes |
| `POSTGRES_PASSWORD` | yes | random URL-safe value |
| `SUPER_ADMIN_PASSWORD` | yes | long unique password |
| `MAILER_DSN` | no | production SMTP DSN; mail is disabled when omitted |
| `SMS_API_TOKEN` | no | SMSAPI.pl token |

Generate URL-safe secrets locally:

```bash
openssl rand -hex 32
```

Add these environment variables:

| Variable | Required | Example/default |
| --- | --- | --- |
| `HOSTINGER_VM_ID` | yes | `123456` |
| `APP_DOMAIN` | yes | `family.example.com` |
| `APP_SCHEME` | no | `https` |
| `SUPER_ADMIN_EMAIL` | yes | `admin@example.com` |
| `SUPER_ADMIN_NAME` | no | `Super Admin` |
| `POSTGRES_DB` | no | `family_plan` |
| `POSTGRES_USER` | no | `family_plan` |
| `BACKUP_RETENTION_DAYS` | no | `7` |
| `MAILER_FROM_EMAIL` | no | `noreply@familyplan.local` |
| `MAILER_FROM_NAME` | no | `Family Plan` |

For compatibility, the workflow also accepts the old
`HOSTINGER_API_TOKEN` and `HOSTINGER_VPS_ID` secret names.

Never paste real production values into `.env.prod`. That file is only a local
template and is committed intentionally without usable credentials.

## 4. Deploy

Merge to `main`, or run the "Deploy to Hostinger" workflow manually from the
Actions tab.

The first run can take longer because all images are built from scratch and
Caddy must obtain a certificate. The workflow waits up to five minutes for:

```text
https://APP_DOMAIN/api/health
```

A successful action means the public endpoint reached Symfony and completed a
database query, not merely that Hostinger accepted an asynchronous API request.

## Database migrations and admin bootstrap

The PHP entrypoint waits for PostgreSQL, executes Doctrine migrations with
`--allow-no-migration`, and then runs `app:create-super-admin`. The command
must remain idempotent because it runs whenever the PHP container is recreated.

## Backups

`database-backup` creates one compressed database dump every 24 hours in the
`database_backups` Docker volume. Old dumps are deleted after
`BACKUP_RETENTION_DAYS`.

These backups live on the same VPS. Enable Hostinger VPS backups or export the
volume off-server as protection against disk or VPS loss.

## Rollback

Every image is tagged with the full Git commit SHA. To roll back:

1. find the last healthy SHA in GitHub Actions;
2. set `IMAGE_TAG` for the Hostinger project to that SHA;
3. redeploy the Compose project.

Alternatively, revert the breaking commit on `main`; the normal pipeline will
build and deploy a new immutable revision.

## Local validation

```bash
cp .env.prod .env.prod.local
# Replace every CHANGE_ME value.
./verify-deployment.sh .env.prod.local
```

This validates the resolved Compose model. Building the images still requires
network access to Composer, npm, Docker Hub, and GHCR.
