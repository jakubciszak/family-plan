#!/usr/bin/env bash

set -Eeuo pipefail

environment_file="${1:-.env.prod.local}"
compose_file="docker-compose.hostinger.yml"

required_files=(
    ".github/workflows/deploy-hostinger.yml"
    "${compose_file}"
    "docker/nginx/Dockerfile.prod"
    "docker/nginx/default.conf"
    "docker/php/Dockerfile.prod"
    "docker/php/docker-entrypoint.sh"
    "frontend/Dockerfile.prod"
    "frontend/nginx.conf"
)

for file in "${required_files[@]}"; do
    if [[ ! -f "${file}" ]]; then
        printf 'Missing required file: %s\n' "${file}" >&2
        exit 1
    fi
done

if ! command -v docker >/dev/null 2>&1; then
    printf 'Docker is required to validate the deployment.\n' >&2
    exit 1
fi

if ! docker compose version >/dev/null 2>&1; then
    printf 'Docker Compose v2 is required to validate the deployment.\n' >&2
    exit 1
fi

if [[ ! -f "${environment_file}" ]]; then
    printf 'Environment file not found: %s\n' "${environment_file}" >&2
    printf 'Create it with: cp .env.prod %s\n' "${environment_file}" >&2
    exit 1
fi

if grep -Eq '(^|=)CHANGE_ME' "${environment_file}"; then
    printf 'Replace every CHANGE_ME value in %s before deployment.\n' "${environment_file}" >&2
    exit 1
fi

docker compose \
    --env-file "${environment_file}" \
    -f "${compose_file}" \
    config \
    --quiet

printf 'Deployment configuration is valid.\n'
