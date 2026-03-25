# Symfony REST API Technical Showcase

[![PHP](https://img.shields.io/badge/PHP-8.3-blue)](https://www.php.net/)
[![Symfony](https://img.shields.io/badge/Symfony-7.4-black)](https://symfony.com/)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)
[![CI](https://github.com/job3dot5/sample02/actions/workflows/ci.yml/badge.svg)](https://github.com/job3dot5/sample02/actions)

This repository contains a Symfony REST API project designed as a technical showcase of modern backend practices.

Rather than building a full product, the focus is on designing a coherent and production-like API: contract-first OpenAPI specification, JWT-based authentication, and an asynchronous image processing pipeline.

The application exposes REST endpoints backed by a Dockerized environment, with background workers (Symfony Messenger) handling image processing and optional AI-powered enrichment (OpenAI Vision).

It demonstrates how to build a complete API workflow, from authentication and file upload to asynchronous processing and result retrieval.

## Features Demonstrated
- REST API without API Platform (explicit controllers/routes)
- Contract-first OpenAPI (`openapi/openapi.v1.yaml`)
- Minimal health endpoint (`/api/v1/health`)
- JWT authentication with `lexik/jwt-authentication-bundle`
- Authenticated async image pipeline (`symfony/messenger` + Doctrine transport + workers)
- Optional AI image enrichment (OpenAI Vision via `symfony/http-client`)
- Static GPT cost estimation per model (persisted for image analyses)

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
- `GET /api/v1/image-jobs/{job_id}` (poll async upload status, returns `status`, `image_id`, `error`, requires `Authorization: Bearer <token>`)
- `GET /api/v1/images?page=1&per_page=20` (paginated image list, includes `analysis.cost` when available, requires `Authorization: Bearer <token>`)
  optional filters: `id=<image_id>` or `ids=1,2,3`
- `GET /api/v1/image/{id}?variant=original|thumbnail|resized` (requires `Authorization: Bearer <token>`)
- `GET /docs/openapi.v1.yaml`

API client authentication and usage details are documented in [docs/api-client.md](docs/api-client.md).
Error responses follow `application/problem+json`.

## Example: processed image response

The following example illustrates a fully processed image, including metadata, generated variants, AI enrichment and estimated cost.

```json
    {
      "id": 16,
      "created_at": "2026-03-25T08:00:52+01:00",
      "updated_at": "2026-03-25T08:00:56+01:00",
      "original_filename": "dual-photo.jpg",
      "mime_type": "image/jpeg",
      "size_bytes": 274915,
      "width": 1200,
      "height": 630,
      "orientation": "landscape",
      "image_urls": {
        "original": "/api/v1/image/16?variant=original",
        "thumbnail": "/api/v1/image/16?variant=thumbnail",
        "resized": "/api/v1/image/16?variant=resized"
      },
      "analysis": {
        "status": "completed",
        "result": {
          "description": "A woman lying on grass and a person holding a cat inside.",
          "tags": [
            "cat",
            "grass",
            "outdoor",
            "pet",
            "woman"
          ],
          "category": "other",
          "image_variant": "resized"
        },
        "cost": {
          "model": "gpt-4.1-nano",
          "input_tokens": 2045,
          "output_tokens": 35,
          "total_tokens": 2080,
          "estimated_cost": 0.001075
        }
      }
    }
```
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

Estimated GPT API cost is persisted per analysis (`image_analysis_cost` table) using a static pricing map configured in `config/services.yaml` (`app.openai.model_pricing`).

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
persist job_tracking(status=queued)
  ↓
worker-upload (xN)
  ↓
processing async
  ↓
update job_tracking(status=processing)
  ↓
dispatch AnalyzeImageMessage
  ↓
worker-analysis (xN)
  ↓
OpenAI Vision call (Responses API, JSON schema contract)
  ↓
persist analysis payload in `image.analysis_*`
  ↓
update job_tracking(status=completed, image_id=...)
```

If upload processing fails in worker, `job_tracking.status` is set to `failed` and `error` contains the failure message.

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
