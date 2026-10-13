#!/bin/bash

# Function to switch PHP version using Homebrew
switch_php() {
    local version="$1"

    if [ -z "$version" ]; then
        echo "Error: PHP version not specified"
        return 1
    fi

    # Unlink current PHP
    brew unlink php > /dev/null 2>&1

    # Link requested version
    if brew link --force --overwrite php@${version} > /dev/null 2>&1; then
        echo "Switched to PHP ${version}"
        php -v | head -1
        return 0
    else
        echo "Failed to switch to PHP ${version}. Make sure it's installed with: brew install php@${version}"
        return 1
    fi
}

DEFAULT_PHP="8.3"
TARGET_PHP_VERSIONS=(8.3 8.4 8.5)
COMPOSE_FILE="tests/dbunit/compose.yaml"

# Extra arguments forwarded to PHPUnit by the "test" and "dbunit" commands (e.g. --filter SomeTest)
PHPUNIT_ARGS=()

# Runs the "phpunit" test suite (no external services) and PHPStan on the currently linked PHP.
# Sets STEP_DETAIL for the summary, returns non-zero if anything failed.
test_step() {
    local failed=0
    local phpunit_result phpstan_result

    echo "--- PHPUnit (phpunit suite) ---"
    if php bin/phpunit --configuration phpunit.xml.dist --testsuite phpunit "${PHPUNIT_ARGS[@]}"; then
        phpunit_result="PASS"
    else
        phpunit_result="FAIL"
        failed=1
    fi

    echo "--- PHPStan (level 5) ---"
    if php bin/phpstan analyse -c phpstan.neon --memory-limit 4G --level 5; then
        phpstan_result="PASS"
    else
        phpstan_result="FAIL"
        failed=1
    fi

    STEP_DETAIL="(PHPUnit: $phpunit_result | PHPStan: $phpstan_result)"
    return $failed
}

# Runs the "dbunit" test suite (requires the database containers) on the currently linked PHP.
dbunit_step() {
    echo "--- PHPUnit (dbunit suite) ---"
    if php bin/phpunit --configuration phpunit.xml.dist --testsuite dbunit "${PHPUNIT_ARGS[@]}"; then
        STEP_DETAIL="(PHPUnit dbunit: PASS)"
        return 0
    else
        STEP_DETAIL="(PHPUnit dbunit: FAIL)"
        return 1
    fi
}

# Runs the given step function once per target PHP version (or only on the explicitly requested version when
# "php <ver>" was given) and prints a PASS/FAIL/SKIPPED summary. Exits 1 if any version failed.
run_across_versions() {
    local step="$1"
    local versions summary="" overall_failed=0 ver

    if [ "$EXPLICIT_PHP" == "1" ]; then
        local current_ver
        current_ver=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')
        versions=("$current_ver")
    else
        versions=("${TARGET_PHP_VERSIONS[@]}")
    fi

    for ver in "${versions[@]}"; do
        echo ""
        echo "===== PHP $ver ====="

        if [ "$EXPLICIT_PHP" != "1" ]; then
            if ! switch_php "$ver"; then
                echo "WARNING: PHP $ver not available, skipping"
                summary="${summary}  PHP $ver : SKIPPED\n"
                continue
            fi
        fi

        STEP_DETAIL=""
        if $step; then
            summary="${summary}  PHP $ver : PASS\n"
        else
            summary="${summary}  PHP $ver : FAIL  $STEP_DETAIL\n"
            overall_failed=1
        fi
    done

    if [ "$EXPLICIT_PHP" != "1" ]; then
        switch_php "$DEFAULT_PHP" > /dev/null 2>&1
    fi

    echo ""
    echo "============================="
    echo "         SUMMARY"
    echo "============================="
    printf "%b" "$summary"
    echo "============================="
    echo ""

    if [ "$overall_failed" == "1" ]; then
        echo "FAILED: one or more PHP versions did not pass all checks"
        exit 1
    else
        echo "All checks passed!"
    fi
}

# Check if specific PHP version is requested, otherwise use default
EXPLICIT_PHP=0
if [ "$1" == "php" ] && [ -n "$2" ]; then
    switch_php "$2" || exit 1
    EXPLICIT_PHP=1
    shift 2
else
    switch_php "$DEFAULT_PHP" || exit 1
fi

php -v || exit 1

CMD="${1}"

if [ "$CMD" == "phpstan" ]; then
    DEFAULT2="5"
    LEVEL="${2:-$DEFAULT2}"
    echo "Running PHPStan with level $LEVEL on $(php -v | head -1)"
    php bin/phpstan analyse -c phpstan.neon --memory-limit 4G --level $LEVEL || exit 1

elif [ "$CMD" == "test" ]; then
    PHPUNIT_ARGS=("${@:2}")
    run_across_versions test_step

elif [ "$CMD" == "dbunit" ]; then
    if ! docker compose version > /dev/null 2>&1; then
        echo "Error: Docker Compose is required for the dbunit suite; database servers run in containers ($COMPOSE_FILE)"
        exit 1
    fi

    echo "Starting database containers from $COMPOSE_FILE (waiting until healthy)..."
    if ! docker compose -f "$COMPOSE_FILE" up -d --wait; then
        echo "Error: database containers did not start or did not become healthy"
        exit 1
    fi

    PHPUNIT_ARGS=("${@:2}")
    run_across_versions dbunit_step

elif [ "$CMD" == "dbunit-down" ]; then
    echo "Stopping and removing database containers from $COMPOSE_FILE..."
    docker compose -f "$COMPOSE_FILE" down -v || exit 1

elif [ "$CMD" == "versions" ]; then
    echo "Available PHP versions:"
    # Check both possible Homebrew paths (Intel and Apple Silicon)
    if [ -d "/opt/homebrew/Cellar" ]; then
        ls /opt/homebrew/Cellar/ | grep "php@" | sort -V
    elif [ -d "/usr/local/Cellar" ]; then
        ls /usr/local/Cellar/ | grep "php@" | sort -V
    else
        echo "No Homebrew installation found"
    fi
    echo ""
    echo "Current version:"
    php -v | head -1

elif [ "$CMD" == "install-php" ]; then
    if [ -z "$2" ]; then
        echo "Usage: ./dev.sh install-php <version>"
        echo "Example: ./dev.sh install-php 8.3"
        exit 1
    fi
    echo "Installing PHP $2..."
    brew install php@$2

else
    echo "Unknown command: [$CMD]"
    echo ""
    echo "Default PHP version: $DEFAULT_PHP"
    echo ""
    echo "Available commands:"
    echo "  phpstan [level]        - Run PHPStan analysis (default level: 5)"
    echo "  test [phpunit args]    - Run the phpunit suite (no external services) + PHPStan on PHP 8.3, 8.4 and 8.5"
    echo "  dbunit [phpunit args]  - Start database containers (Docker) and run the dbunit suite on PHP 8.3, 8.4 and 8.5"
    echo "  dbunit-down            - Stop and remove the database containers"
    echo "  versions               - Show available PHP versions"
    echo "  install-php <ver>      - Install PHP version (e.g., 8.3)"
    echo "  php <ver> <cmd> [args] - Use specific PHP version instead of default"
    echo ""
    echo "Examples:"
    echo "  ./dev.sh phpstan 6              - Run PHPStan level 6 on PHP $DEFAULT_PHP"
    echo "  ./dev.sh test                   - Run PHPUnit (phpunit suite) + PHPStan on PHP 8.3, 8.4, 8.5"
    echo "  ./dev.sh test --filter UtilTest - Run only UtilTest on all target PHP versions"
    echo "  ./dev.sh dbunit                 - Run database tests against PostgreSQL, MariaDB, MySQL and SQLite on PHP 8.3, 8.4, 8.5"
    echo "  ./dev.sh dbunit --filter PostgreSQLInsertReturningTest"
    echo "  ./dev.sh dbunit-down            - Remove the database containers when done"
    echo "  ./dev.sh php 8.3 phpstan        - Run PHPStan on PHP 8.3"
    echo "  ./dev.sh php 8.4 test           - Run PHPUnit + PHPStan on PHP 8.4 only"
    echo "  ./dev.sh php 8.4 dbunit         - Run database tests on PHP 8.4 only"
    exit 1
fi

exit 0
