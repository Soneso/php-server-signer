.PHONY: install build run test test-coverage clean lint fmt docker-build docker-run docker-compose-up docker-compose-down help

# PHP parameters
PHP=php
COMPOSER=composer
PHPUNIT=./vendor/bin/phpunit
BIN_DIR=bin
SERVER_SCRIPT=$(BIN_DIR)/server

# Docker parameters
DOCKER_IMAGE=stellar-php-remote-signer
DOCKER_TAG=latest

all: install

# Install dependencies
install:
	$(COMPOSER) install

# Build (install dependencies with optimizations)
build:
	$(COMPOSER) install --optimize-autoloader

# Build for production (no dev dependencies)
build-prod:
	$(COMPOSER) install --no-dev --optimize-autoloader

# Run the application with config file
run:
	$(PHP) $(SERVER_SCRIPT) -c config.json

# Run with environment variables
run-env:
	$(PHP) $(SERVER_SCRIPT)

# Run tests
test:
	$(PHPUNIT)

# Run tests with coverage
test-coverage:
	$(PHPUNIT) --coverage-html coverage

# Clean build artifacts
clean:
	rm -rf vendor
	rm -rf coverage
	rm -f .phpunit.result.cache
	rm -f composer.lock

# Run PHP syntax check (lint)
lint:
	@echo "Checking PHP syntax..."
	@find src tests public -type f -name "*.php" -exec $(PHP) -l {} \; > /dev/null
	@$(PHP) -l bin/server > /dev/null
	@echo "All files have valid syntax."

# Format code (requires PHP_CodeSniffer)
fmt:
	@if [ -f ./vendor/bin/phpcbf ]; then \
		./vendor/bin/phpcbf --standard=PSR12 src tests bin || true; \
	else \
		echo "PHP_CodeSniffer not installed. Run 'composer require --dev squizlabs/php_codesniffer'"; \
	fi

# Check code style (requires PHP_CodeSniffer)
check-style:
	@if [ -f ./vendor/bin/phpcs ]; then \
		./vendor/bin/phpcs --standard=PSR12 src tests bin; \
	else \
		echo "PHP_CodeSniffer not installed. Run 'composer require --dev squizlabs/php_codesniffer'"; \
	fi

# Build Docker image
docker-build:
	docker build -t $(DOCKER_IMAGE):$(DOCKER_TAG) .

# Run Docker container with environment variables
docker-run:
	docker run -p 5003:5003 --env-file .env $(DOCKER_IMAGE):$(DOCKER_TAG)

# Start services with Docker Compose
docker-compose-up:
	docker-compose up --build

# Stop Docker Compose services
docker-compose-down:
	docker-compose down

# Show logs from Docker Compose
docker-logs:
	docker-compose logs -f

# Validate composer.json
validate:
	$(COMPOSER) validate

# Update dependencies
update:
	$(COMPOSER) update

# Show outdated dependencies
outdated:
	$(COMPOSER) outdated

# Security check (requires local-php-security-checker)
security:
	@if command -v local-php-security-checker >/dev/null 2>&1; then \
		local-php-security-checker; \
	else \
		echo "local-php-security-checker not installed."; \
		echo "Install from: https://github.com/fabpot/local-php-security-checker"; \
	fi

help:
	@echo "Available targets:"
	@echo "  install           - Install dependencies"
	@echo "  build             - Install dependencies with optimizations"
	@echo "  build-prod        - Install dependencies for production (no dev)"
	@echo "  run               - Run server with config file"
	@echo "  run-env           - Run server with environment variables"
	@echo "  test              - Run PHPUnit tests"
	@echo "  test-coverage     - Run tests with HTML coverage report"
	@echo "  clean             - Remove vendor directory and build artifacts"
	@echo "  lint              - Check PHP syntax"
	@echo "  fmt               - Format code with PHP_CodeSniffer"
	@echo "  check-style       - Check code style with PHP_CodeSniffer"
	@echo "  docker-build      - Build Docker image"
	@echo "  docker-run        - Run Docker container"
	@echo "  docker-compose-up - Start services with Docker Compose"
	@echo "  docker-compose-down - Stop Docker Compose services"
	@echo "  docker-logs       - Show Docker Compose logs"
	@echo "  validate          - Validate composer.json"
	@echo "  update            - Update dependencies"
	@echo "  outdated          - Show outdated dependencies"
	@echo "  security          - Check for security vulnerabilities"
	@echo "  help              - Show this help message"
