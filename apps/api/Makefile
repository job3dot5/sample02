DC := docker compose -f ../../docker-compose.yml
PHP := $(DC) exec -T php
COMPOSER := $(PHP) composer --working-dir=/var/www/api

.PHONY: help shell install lint cs-check cs-fix test jwt-keypair jwt-decode

help:
	@echo "Available targets:"
	@echo "  make shell        # Open shell in php container"
	@echo "  make install      # Install composer dependencies"
	@echo "  make lint         # Run PHPStan"
	@echo "  make cs-check     # Check code style"
	@echo "  make cs-fix       # Fix code style"
	@echo "  make test         # Run PHPUnit"
	@echo "  make jwt-decode   # Decode JWT payload (requires TOKEN=...)"
	@echo "  make jwt-keypair  # Generate JWT keypair (one time install)"

shell:
	$(DC) exec php /bin/bash

install:
	$(COMPOSER) install

lint:
	$(COMPOSER) lint

cs-check:
	$(COMPOSER) cs:check

cs-fix:
	$(COMPOSER) cs:fix

test:
	$(COMPOSER) test

jwt-keypair:
	$(PHP) php /var/www/api/bin/console lexik:jwt:generate-keypair --no-interaction

jwt-decode:
	@if [ -z "$(TOKEN)" ]; then echo "Usage: make jwt-decode TOKEN=<JWT_TOKEN>"; exit 1; fi
	@echo "$(TOKEN)" | cut -d '.' -f2 | tr '_-' '/+' | base64 -d 2>/dev/null | jq
