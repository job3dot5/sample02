# Symfony Backend Technical Showcase

[![PHP](https://img.shields.io/badge/PHP-8.3-blue)](https://www.php.net/)
[![Symfony](https://img.shields.io/badge/Symfony-LTS-black)](https://symfony.com/)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)
[![CI](https://github.com/job3dot5/sample02/actions/workflows/ci.yml/badge.svg)](https://github.com/job3dot5/sample02/actions)

The goal of this application is to illustrate pragmatic API development practices rather than building a full product with :
- a clean development environment
- basic API features
- automated code quality checks

## Features Demonstrated



## Screenshots


## Technical Stack

- PHP 8
- Symfony (LTS)
- Doctrine DBAL
- SQLite (database file: `var/app.db`)
- Docker / Docker Compose

## Development tooling

- PHPStan (static analysis)
- PHP-CS-Fixer (code style)
- PHPUnit
- Git hooks (pre-commit / pre-push)
- GitHub Actions CI

## How to install

### 1. Follow the docker environment install [here](../../README.md)

### 2. composer install
Enter your web container from your host machine to install composer modules:

```bash
docker compose exec php /bin/bash
composer install
```

### 3. Git hooks

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

## Hooks from host machine

You can run Git from the host machine without installing Composer locally.

- If host `composer` exists, hooks run Composer commands directly in `apps/api`.
- If host `composer` is missing, hooks run Composer inside the `php` container via `docker compose exec`.
- If neither `composer` nor `docker` is available, hooks fail.

Requirement when using container mode: the `php` service must be running (`docker compose up -d`).

## Run tests

Run the full PHPUnit suite (including smoke test and any test files in `tests/`):

From host machine (containerized tooling):

```bash
docker compose -f ../../docker-compose.yml exec -T php composer --working-dir=/var/www/api test
```

## CI checks

GitHub Actions runs:
- `composer lint`
- `composer cs:check`
- `composer test`
