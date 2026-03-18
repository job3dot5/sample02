# Symfony REST API Technical Showcase

[![PHP](https://img.shields.io/badge/PHP-8.3-blue)](https://www.php.net/)
[![Symfony](https://img.shields.io/badge/Symfony-7.4-black)](https://symfony.com/)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)
[![CI](https://github.com/job3dot5/sample02/actions/workflows/ci.yml/badge.svg)](https://github.com/job3dot5/sample02/actions)

The goal of this application is to illustrate pragmatic API development practices rather than building a full product with :
- a clean development environment
- basic API features
- automated code quality checks

## Features Demonstrated
- REST API without API Platform (explicit controllers/routes)
- Contract-first OpenAPI (`openapi/openapi.v1.yaml`)
- Minimal health endpoint (`/api/v1/health`)
- JWT authentication with `lexik/jwt-authentication-bundle`
- Authenticated async image pipeline (`symfony/messenger` + Doctrine transport + workers)
- Optional AI image enrichment (OpenAI Vision via `symfony/http-client`)


## Screenshots

## Technical Stack

- PHP 8.3
- Symfony (LTS)
- REST API (JSON + Symfony Routing/Controllers)
- OpenAPI 3.1 (`openapi/openapi.v1.yaml`)
- JWT auth (`lexik/jwt-authentication-bundle`, bearer tokens)
- Doctrine DBAL
- SQLite (database file: `var/data.db`)
- Symfony HttpClient
- Docker / Docker Compose

## Demo endpoints

- `POST /api/v1/login` (body: `{"username":"<API_USERNAME>","password":"<API_PASSWORD>"}`)
- `GET /api/v1/health`
- `GET /api/v1/me` (requires `Authorization: Bearer <token>`)
- `POST /api/v1/images` (multipart field `file`, returns `202 Accepted` with `job_id`, requires `Authorization: Bearer <token>`)
- `GET /api/v1/images?page=1&per_page=20` (paginated image list, requires `Authorization: Bearer <token>`)
- `GET /api/v1/image/{id}?variant=original|thumbnail|resized` (requires `Authorization: Bearer <token>`)
- `GET /docs/openapi.v1.yaml`

API client authentication details are documented in [docs/api-client-auth.md](docs/api-client-auth.md).
Error responses follow `application/problem+json`.

## How to install

### 1. Follow the docker environment install [here](../../README.md)

### 2. Bootstrap application (recommended)

```bash
make setup
```

This command ensures runtime permissions, installs dependencies, generates JWT keys, and sets up Messenger transports.

Optional manual alternative (if you want step-by-step):

```bash
make install
make permissions
make jwt-keypair
make setup-transports
```

### 3. Generate JWT keypair

Generate private/public keys used by `lexik/jwt-authentication-bundle`:

```bash
make jwt-keypair
```

Notes:
- Keys are generated in `config/jwt/private.pem` and `config/jwt/public.pem`.
- These files are ignored by Git.

### 4. Configure API credentials

The demo in-memory user is:
- `username`: value of `API_USERNAME` in `.env` (default committed value: `change_me`)
- `password`: value of `API_PASSWORD` in `.env` (default committed value: `change_me`)
- JWT private key passphrase: `JWT_PASSPHRASE` (default committed value: `change_me`)
- JWT issuer claim: `JWT_ISSUER` (default: `urn:sample02:api`)
- JWT audience claim: `JWT_AUDIENCE` (default: `urn:sample02:client`)
- OpenAI API key (optional): `OPENAI_API_KEY` (if empty, AI analysis is skipped)
- OpenAI model: `OPENAI_MODEL` (default: `gpt-4.1-nano`)

For local/dev usage, set local values in `.env.local` (not versioned), for example:

```dotenv
API_USERNAME=demo
API_PASSWORD=demo
JWT_PASSPHRASE=your_local_passphrase
JWT_ISSUER=urn:sample02:api
JWT_AUDIENCE=urn:sample02:client
OPENAI_API_KEY=sk-...
OPENAI_MODEL=gpt-4.1-nano
```

### 5. Git hooks

Hooks are not installed automatically by Git. Use symlinks so updates to the hook files are picked up automatically.

From `[project-root]/.git/hooks`

```bash
ln -s ../../apps/api/.githooks/pre-commit pre-commit
ln -s ../../apps/api/.githooks/pre-push pre-push
```

If a hook already exists remove it.

Hook behavior:
- `pre-commit`: runs `composer cs:check` then `composer test`
- `pre-push`: runs `composer lint`

## Development Tooling

Use the Makefile in `apps/api` to run all common commands from your host:

```bash
make help
```

Permission diagnostics:

```bash
make doctor
```

## Async worker (Messenger)

Image processing is consumed asynchronously by two Docker workers:
- `worker-upload`: upload processing (original/thumbnail/resized + metadata row)
- `worker-analysis`: optional AI analysis on `resized` image (description/tags/category enrichment)

Workflow:

```text
API
  ↓
dispatch ProcessImageUploadMessage
  ↓
DB / queue
  ↓
worker-upload (xN)
  ↓
processing async
  ↓
dispatch AnalyzeImageMessage
  ↓
worker-analysis (xN)
  ↓
OpenAI Vision call (Responses API, JSON schema contract)
  ↓
persist analysis payload in `image.analysis_*`
```

When stack is up, workers execute:
- `php bin/console messenger:setup-transports`
- `php bin/console messenger:consume transport_async_image_upload`
- `php bin/console messenger:consume transport_async_image_analysis`

Transport/queue mapping:
- `transport_async_image_upload` → queue `image_upload`
- `transport_async_image_analysis` → queue `image_analysis`

Image processing trace logs:
- `var/log/image_processing.log` (dev/test)
- `stderr` in production


Note : Messenger workers must be restarted after code changes in development.

## Git hooks from host machine

You can run Git from the host machine without installing Composer locally.

- If host `composer` exists, hooks run Composer commands directly in `apps/api`.
- If host `composer` is missing, hooks run Composer inside the `php` container via `docker compose exec`.
- If neither `composer` nor `docker` is available, hooks fail.

Requirement when using container mode: the `php` service must be running (`docker compose up -d`).

## CI checks

GitHub Actions runs:
- `composer lint`
- `composer cs:check`
- `composer test`
