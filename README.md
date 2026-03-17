# Symfony REST API Technical Showcase

[![PHP](https://img.shields.io/badge/PHP-8.3-blue)](https://www.php.net/)
[![Symfony](https://img.shields.io/badge/Symfony-LTS-black)](https://symfony.com/)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)
[![CI](https://github.com/job3dot5/sample02/actions/workflows/ci.yml/badge.svg)](https://github.com/job3dot5/sample02/actions)

This repository contains a small Symfony REST API project used as a technical showcase.

The goal is not to build a full product but to demonstrate pragmatic backend architecture, API contracts, authentication, and development tooling.

The project includes a Docker development environment and a minimal Symfony application exposing REST endpoints, OpenAPI documentation, and JWT-protected routes.

## Stack

- PHP 8.3 FPM
- Symfony 7.4
- REST API (JSON)
- OpenAPI 3.1 contract
- JWT authentication (`lexik/jwt-authentication-bundle`)
- Async message queue for image processing (`symfony/messenger` + Doctrine transport + Docker worker)
- Nginx
- Docker Compose

## Development Tooling

- PHPStan (static analysis)
- PHP-CS-Fixer (code style)
- PHPUnit
- Git hooks (pre-commit / pre-push)
- GitHub Actions CI

More details about the Symfony application can be found in:
[apps/api/README.md](apps/api/README.md)

## Local domain

This setup serves the project on `sample02.dev` with HTTPS.

### 1. Install mkcert (one-time)

```bash
sudo apt update
sudo apt install mkcert libnss3-tools
mkcert -install
```

### 2. Generate certificate

```bash
mkcert sample02.dev
```

Generated files:
- `sample02.dev.pem`
- `sample02.dev-key.pem`

### 3. Copy certificate files

```bash
mv sample02.dev.pem [project-path]/docker/nginx/ssl/sample02.dev.crt
mv sample02.dev-key.pem [project-path]/docker/nginx/ssl/sample02.dev.key
```

### 4. Add hosts entry

Edit `/etc/hosts` as root and add:

```text
127.0.0.1 sample02.dev
```

## Run

```bash
docker compose up -d --build
```

Open: `https://sample02.dev`

The `worker` service consumes `transport_async_images` messages to process image uploads asynchronously.

For first-time app bootstrap inside `apps/api`, run:

```bash
make setup
```

You can validate writable runtime paths and key files with:

```bash
make doctor
```

## Stop

```bash
docker compose down
```
