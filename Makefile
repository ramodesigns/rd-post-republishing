# RD Post Republishing - Development Makefile
#
# Usage:
#   make install    - Install dependencies and set up hooks
#   make check      - Run all code quality checks
#   make test       - Run PHPUnit tests
#   make fix        - Auto-fix coding standards issues

.PHONY: install check test fix phpstan phpcs phpcbf hooks clean help

# Default target
help:
	@echo "RD Post Republishing - Development Commands"
	@echo ""
	@echo "  make install    Install dependencies and set up git hooks"
	@echo "  make check      Run all code quality checks (PHPStan + PHPCS)"
	@echo "  make test       Run PHPUnit tests"
	@echo "  make fix        Auto-fix coding standards issues"
	@echo "  make phpstan    Run PHPStan static analysis"
	@echo "  make phpcs      Run PHPCS code style check"
	@echo "  make phpcbf     Run PHPCBF auto-fixer"
	@echo "  make hooks      Install git pre-commit hooks"
	@echo "  make clean      Clean build artifacts"
	@echo ""

# Install dependencies and set up environment
install: hooks
	composer install

# Set up git hooks
hooks:
	git config core.hooksPath .githooks
	@echo "Git hooks configured to use .githooks directory"

# Run all checks
check: phpstan phpcs

# Run PHPStan
phpstan:
	./vendor/bin/phpstan analyse

# Run PHPCS
phpcs:
	./vendor/bin/phpcs

# Run PHPCBF auto-fixer
phpcbf:
	./vendor/bin/phpcbf || true

# Alias for phpcbf
fix: phpcbf

# Run PHPUnit tests
test:
	./vendor/bin/phpunit

# Run tests with coverage
test-coverage:
	./vendor/bin/phpunit --coverage-html build/coverage

# Clean build artifacts
clean:
	rm -rf build/
	rm -rf .phpunit.cache/
	rm -rf vendor/

# Check syntax of all PHP files
syntax:
	find . -name "*.php" -not -path "./vendor/*" -print0 | xargs -0 -n1 php -l

# Build release package
build:
	@echo "Creating release package..."
	mkdir -p build/release
	rsync -av --exclude='.git*' --exclude='build' --exclude='tests' \
		--exclude='vendor' --exclude='node_modules' --exclude='.phpunit.cache' \
		--exclude='composer.lock' --exclude='Makefile' --exclude='.githooks' \
		--exclude='phpunit.xml.dist' --exclude='phpstan.neon' --exclude='phpcs.xml.dist' \
		. build/release/rd-post-republishing/
	cd build/release && zip -r rd-post-republishing.zip rd-post-republishing
	@echo "Release package created at build/release/rd-post-republishing.zip"
