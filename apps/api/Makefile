SHELL := /bin/bash
.SHELLFLAGS := -eu -o pipefail -c
.DEFAULT_GOAL := help

DC ?= docker compose -f ../../docker-compose.yml
APP_DIR ?= /var/www/api
PHP ?= $(DC) exec -T php
COMPOSER ?= $(PHP) composer --working-dir=$(APP_DIR)

.PHONY: help require-php check-token setup install permissions jwt-keypair setup-transports doctor shell lint cs-check cs-fix test jwt-decode

help:
	@echo "Setup / Install (one-time or occasional):"
	@echo "  make setup            # Full bootstrap: permissions + deps + jwt keys + messenger transports"
	@echo "  make install          # Install composer dependencies"
	@echo "  make permissions      # Initialize runtime files/directories permissions"
	@echo "  make jwt-keypair      # Generate JWT keypair"
	@echo "  make setup-transports # Create messenger transport tables/queues"
	@echo ""
	@echo "Daily scripts:"
	@echo "  make test             # Run PHPUnit"
	@echo "  make lint             # Run PHPStan"
	@echo "  make cs-check         # Check code style"
	@echo "  make cs-fix           # Fix code style"
	@echo ""
	@echo "Debug scripts:"
	@echo "  make doctor           # Check runtime permissions and writable paths"
	@echo "  make jwt-decode       # Decode JWT payload (requires TOKEN=...)"
	@echo "  make shell            # Open shell in php container"

shell:
	$(DC) exec php /bin/bash

require-php:
	@if ! $(DC) ps --status running --services | grep -qx 'php'; then \
		echo "php service is not running. Start it first: docker compose up -d php"; \
		exit 1; \
	fi

setup: require-php permissions install jwt-keypair setup-transports

permissions: require-php
	$(PHP) $(APP_DIR)/bin/runtime-permissions.sh init

doctor: require-php
	$(PHP) $(APP_DIR)/bin/runtime-permissions.sh doctor

setup-transports: require-php
	$(PHP) php $(APP_DIR)/bin/console messenger:setup-transports --no-interaction

install: require-php
	$(COMPOSER) install

lint: require-php
	$(COMPOSER) lint

cs-check: require-php
	$(COMPOSER) cs:check

cs-fix: require-php
	$(COMPOSER) cs:fix

test: require-php
	$(COMPOSER) test

jwt-keypair: require-php
	$(PHP) php $(APP_DIR)/bin/console lexik:jwt:generate-keypair --no-interaction --skip-if-exists

check-token:
	@if [ -z "$(TOKEN)" ]; then echo "Usage: make jwt-decode TOKEN=<JWT_TOKEN>"; exit 1; fi

jwt-decode: require-php check-token
	@echo "Note: This only decodes the payload for inspection."
	@echo "Note: It does not verify signature/expiration/claims."
	@echo "$(TOKEN)" | cut -d '.' -f2 | tr '_-' '/+' | base64 -d 2>/dev/null | jq
