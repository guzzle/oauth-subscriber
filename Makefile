help:
	@echo "Please use \`make <target>' where <target> is one of"
	@echo "  start-server                   to start the test server"
	@echo "  stop-server                    to stop the test server"
	@echo "  test                           to run tests. Provide TEST to run a specific test."
	@echo "  static                         to run phpstan and php-cs-fixer on the codebase"
	@echo "  static-phpstan                 to run phpstan on the codebase"
	@echo "  static-phpstan-update-baseline to regenerate the phpstan baseline file"
	@echo "  static-codestyle-fix           to run php-cs-fixer on the codebase, writing changes"
	@echo "  static-codestyle-check         to run php-cs-fixer on the codebase"
	@echo "  clean                          to remove build artifacts"

start-server: stop-server
	node vendor/guzzlehttp/test-server/src/server.js $${OAUTH_SUBSCRIBER_TEST_SERVER_PORT:-8126} &> /dev/null &

stop-server:
	@PID=$(shell ps axo pid,command \
	  | grep 'vendor/guzzlehttp/test-server/src/server.js' \
	  | grep -v grep \
	  | cut -f 1 -d " "\
	) && [ -n "$$PID" ] && kill $$PID || true

test: start-server
	vendor/bin/phpunit $(TEST)
	$(MAKE) stop-server

static: static-phpstan static-codestyle-check

static-phpstan:
	composer install
	composer bin phpstan update
	vendor/bin/phpstan analyze $(PHPSTAN_PARAMS)

static-phpstan-update-baseline:
	composer install
	composer bin phpstan update
	$(MAKE) static-phpstan PHPSTAN_PARAMS="--generate-baseline"

static-codestyle-fix:
	composer install
	composer bin php-cs-fixer update
	vendor/bin/php-cs-fixer fix --diff $(CS_PARAMS)

static-codestyle-check:
	$(MAKE) static-codestyle-fix CS_PARAMS="--dry-run"

clean:
	rm -rf build/artifacts/*

.PHONY: help start-server stop-server test static static-phpstan static-phpstan-update-baseline static-codestyle-fix static-codestyle-check clean
