# Symfony REST API Technical Showcase

[![PHP](https://img.shields.io/badge/PHP-8.3-blue)](https://www.php.net/)
[![Symfony](https://img.shields.io/badge/Symfony-LTS-black)](https://symfony.com/)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)
[![CI](https://github.com/job3dot5/sample02/actions/workflows/ci.yml/badge.svg)](https://github.com/job3dot5/sample02/actions)

The goal of this application is to illustrate pragmatic API development practices rather than building a full product with :
- a clean development environment
- basic API features
- automated code quality checks

## Features Demonstrated
- REST API without API Platform (explicit controllers/routes)
- Contract-first OpenAPI (`openapi/openapi.yaml`)
- Minimal health endpoint (`/api/health`)
- JWT authentication with `lexik/jwt-authentication-bundle`


## Screenshots

## Technical Stack

- PHP 8
- Symfony (LTS)
- REST API (JSON + Symfony Routing/Controllers)
- OpenAPI 3.1 (`openapi/openapi.yaml`)
- JWT auth (`lexik/jwt-authentication-bundle`, bearer tokens)
- Doctrine DBAL
- SQLite (database file: `var/app.db`)
- Docker / Docker Compose

## Demo endpoints

- `POST /api/login` (body: `{"username":"<API_USERNAME>","password":"<API_PASSWORD>"}`)
- `GET /api/health`
- `GET /api/me` (requires `Authorization: Bearer <token>`)
- `GET /docs/openapi.yaml`

API client authentication details are documented in [docs/api-client-auth.md](docs/api-client-auth.md).
Error responses follow `application/problem+json`.

## How to install

### 1. Follow the docker environment install [here](../../README.md)

### 2. Install dependencies
Install Composer dependencies in the `php` container:

```bash
make install
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

For local/dev usage, set local values in `.env.local` (not versioned), for example:

```dotenv
API_USERNAME=demo
API_PASSWORD=demo
JWT_PASSPHRASE=your_local_passphrase
JWT_ISSUER=urn:sample02:api
JWT_AUDIENCE=urn:sample02:client
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

## Git hooks from host machine

You can run Git from the host machine without installing Composer locally.

- If host `composer` exists, hooks run Composer commands directly in `apps/api`.
- If host `composer` is missing, hooks run Composer inside the `php` container via `docker compose exec`.
- If neither `composer` nor `docker` is available, hooks fail.

Requirement when using container mode: the `php` service must be running (`docker compose up -d`).

## Tools

Decode a JWT payload locally (without verification):

```bash
make jwt-decode TOKEN='<JWT_TOKEN>'
```

Notes:
- This only decodes the payload for inspection.
- It does not verify signature/expiration/claims.

## CI checks

GitHub Actions runs:
- `composer lint`
- `composer cs:check`
- `composer test`
