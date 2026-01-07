.PHONY: install install-dev update build mozart-build dump-autoload dump-autoload-dev lint format test docker-build docker-run docker-shell release all clean

# Docker image name
DOCKER_IMAGE = aucteeno-runner:latest
DOCKER_RUN = docker run --rm -v $(PWD):/app -w /app $(DOCKER_IMAGE)

# Plugin name and version
PLUGIN_NAME = aucteeno
PLUGIN_VERSION = $(shell grep "Version:" aucteeno.php | sed 's/.*Version: *\([0-9.]*\).*/\1/')
PACKAGE_DIR = build/$(PLUGIN_NAME)
PACKAGE_FILE = build/$(PLUGIN_NAME)-$(PLUGIN_VERSION).zip

# Build Docker image
docker-build:
	docker build -t $(DOCKER_IMAGE) .

# Install composer dependencies without dev dependencies (runs in Docker)
install: docker-build
	$(DOCKER_RUN) composer install --no-dev

# Install composer dependencies with dev dependencies (runs in Docker)
install-dev: docker-build
	$(DOCKER_RUN) composer install

# Update composer dependencies (runs in Docker)
update: docker-build
	$(DOCKER_RUN) composer update

# Build Mozart dependencies (runs in Docker)
build: docker-build
	$(DOCKER_RUN) composer mozart-build

# Build Mozart dependencies in parallel (alias for build)
mozart-build: build

# Dump autoloader without dev dependencies (runs in Docker)
dump-autoload: docker-build
	$(DOCKER_RUN) composer dump-autoload --no-dev --optimize

# Dump autoloader with dev dependencies (runs in Docker)
dump-autoload-dev: docker-build
	$(DOCKER_RUN) composer dump-autoload

# Run PHPCS linter (runs in Docker)
lint: docker-build
	$(DOCKER_RUN) ./vendor/bin/phpcs --standard=.phpcs.xml.dist

# Format code using PHPCBF (runs in Docker)
format: docker-build
	$(DOCKER_RUN) composer phpcbf

# Run PHPUnit tests (runs in Docker)
test: docker-build
	$(DOCKER_RUN) php ./vendor/bin/phpunit

# Run Docker container interactively
docker-run:
	docker run -it --rm -v $(PWD):/app $(DOCKER_IMAGE)

# Open shell in Docker container
docker-shell:
	docker run -it --rm -v $(PWD):/app $(DOCKER_IMAGE) sh

# Package plugin for WordPress.org distribution (runs in Docker)
release: install build
	@mkdir -p build
	@$(DOCKER_RUN) ./scripts/release.sh

# Run all: install, build, lint, test (runs in Docker)
all: install-dev build lint test

# Clean vendor, dependencies, and build files
clean:
	rm -rf vendor/ dependencies/ composer.lock build/

