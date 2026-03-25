# Symfony REST API Technical Showcase

[![PHP](https://img.shields.io/badge/PHP-8.3-blue)](https://www.php.net/)
[![Symfony](https://img.shields.io/badge/Symfony-7.4-black)](https://symfony.com/)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)
[![CI](https://github.com/job3dot5/sample02/actions/workflows/ci.yml/badge.svg)](https://github.com/job3dot5/sample02/actions)

This repository contains a Symfony REST API project designed as a technical showcase of modern backend practices.

Rather than building a full product, the focus is on designing a coherent and production-like API: contract-first OpenAPI specification, JWT-based authentication, and an asynchronous image processing pipeline.

The application exposes REST endpoints backed by a Dockerized environment, with background workers (Symfony Messenger) handling image processing and optional AI-powered enrichment (OpenAI Vision).

It demonstrates how to build a complete API workflow, from authentication and file upload to asynchronous processing and result retrieval.

## Stack

- PHP 8.3 FPM
- Symfony 7.4
- REST API (JSON)
- OpenAPI 3.1 contract
- JWT authentication (`lexik/jwt-authentication-bundle`)
- Async image pipeline (`symfony/messenger` + Doctrine transport + Docker workers)
- OpenAI Vision enrichment (Responses API via `symfony/http-client`, model configurable)
- Static GPT cost estimation per model (persisted for image analyses)
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

The `worker-upload` service consumes `transport_async_image_upload` messages to process image uploads asynchronously.
The `worker-analysis` service consumes `transport_async_image_analysis` messages for optional AI tagging/enrichment.

Queue names:
- upload: `image_upload`
- analysis: `image_analysis`

Async upload status can be polled through `GET /api/v1/image-jobs/{job_id}` (JWT required), returning `status`, `image_id`, and `error`.
Image listing (`GET /api/v1/images`) includes AI payload and estimated GPT cost when analysis is available.

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
