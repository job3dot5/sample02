# Symfony REST API Technical Showcase

> Contract-first API design with a production-like consumption architecture (BFF + frontend).

[![PHP](https://img.shields.io/badge/PHP-8.3-blue)](https://www.php.net/)
[![Symfony](https://img.shields.io/badge/Symfony-7.4-black)](https://symfony.com/)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)
[![CI](https://github.com/job3dot5/sample02/actions/workflows/ci.yml/badge.svg)](https://github.com/job3dot5/sample02/actions)

This repository contains a Symfony REST API project designed as a technical showcase of modern backend practices.

Rather than building a full product, the focus is on designing a coherent and production-like API: contract-first OpenAPI specification, JWT-based authentication, and an asynchronous image processing pipeline.

The application exposes REST endpoints backed by a Dockerized environment, with background workers (Symfony Messenger) handling image processing and optional AI-powered enrichment (OpenAI Vision).

It demonstrates how to build a complete API workflow, from authentication and file upload to asynchronous processing and result retrieval.

## API Stack

- PHP 8.3 FPM
- Symfony 7.4 (LTS)
- REST API (JSON)
- OpenAPI 3.1 contract
- JWT authentication (`lexik/jwt-authentication-bundle`)
- Async image pipeline (`symfony/messenger` + Doctrine transport + Docker workers)
- OpenAI Vision enrichment (Responses API via `symfony/http-client`, configurable model)
- Static GPT cost estimation per model (persisted for image analyses)
- Nginx
- Docker Compose

## API Development Tooling

- PHPStan (static analysis)
- PHP-CS-Fixer (code style)
- PHPUnit
- Git hooks (pre-commit / pre-push)
- GitHub Actions CI

More details about the Symfony application can be found in:
[apps/api/README.md](apps/api/README.md)

## API Consumption (Reference Implementation)

```text
Client (Vue.js)
   ↓
Backend-for-Frontend (Node.js / Express)
   ↓
Symfony API (main project)
```
This repository also includes a reference implementation demonstrating how the API can be consumed in a secure B2B context.

The main Symfony API remains the core of the project. The additional applications illustrate a typical architecture where API credentials and authentication flows are handled server-side, while frontend clients interact only with controlled endpoints.

This pattern reflects real-world usage of exposed APIs in multi-client environments.

- `apps/backend` (BFF): [apps/backend/README.md](apps/backend/README.md)

  Node.js + Express application acting as a secure proxy.
  It stores API credentials, manages JWT acquisition/refresh, and exposes controlled endpoints to the frontend.

- `apps/frontend` (UI): [apps/frontend/README.md](apps/frontend/README.md)

  Vue.js application consuming only the BFF.
  No API credentials or authentication logic are exposed to the browser.

## Application Overview

| Application | Role | Stack |
|------------|------|------|
| `apps/api` | Core REST API | PHP 8.3, Symfony 7.4, OpenAPI 3.1, JWT, Messenger |
| `apps/backend` | BFF / Secure proxy | Node.js 22, Express 4, OpenAPI-driven client |
| `apps/frontend` | UI client | Vue 3, Vite 5 |

This setup highlights a contract-first API design combined with a realistic consumption model reflecting production-grade architectures.

## Application Setup : local domain

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

Before starting Docker Compose for BFF + frontend example, create a non-versioned root `.env` file from `.env.example`:

```bash
cp .env.example .env
```

## Run

```bash
docker compose up -d --build
```

## Access Modes

Two access modes are available after startup:

- Main API domain (HTTPS via Nginx + mkcert): `https://sample02.dev`
- BFF + frontend dev mode (localhost):
  - Frontend UI: `http://localhost:5173`
  - BFF health: `http://localhost:3000/health`

Use `sample02.dev` when you want to test the API domain setup with HTTPS.
Use `localhost` when you want to iterate quickly on the frontend/BFF pair.

## Workers 

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
