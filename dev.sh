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
    if [ "$EXPLICIT_PHP" == "1" ]; then
        current_ver=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')
        PHP_VERSIONS=("$current_ver")
    else
        PHP_VERSIONS=(8.3 8.4 8.5)
    fi

    SUMMARY=""
    OVERALL_FAILED=0

    for ver in "${PHP_VERSIONS[@]}"; do
        echo ""
        echo "===== PHP $ver ====="

        if [ "$EXPLICIT_PHP" != "1" ]; then
            if ! switch_php "$ver"; then
                echo "WARNING: PHP $ver not available, skipping"
                SUMMARY="${SUMMARY}  PHP $ver : SKIPPED\n"
                continue
            fi
        fi

        version_failed=0

        echo "--- PHPUnit ---"
        if php bin/phpunit --configuration phpunit.xml.dist "${@:2}"; then
            phpunit_result="PASS"
        else
            phpunit_result="FAIL"
            version_failed=1
        fi

        echo "--- PHPStan (level 5) ---"
        if php bin/phpstan analyse -c phpstan.neon --memory-limit 4G --level 5; then
            phpstan_result="PASS"
        else
            phpstan_result="FAIL"
            version_failed=1
        fi

        if [ "$version_failed" == "1" ]; then
            SUMMARY="${SUMMARY}  PHP $ver : FAIL  (PHPUnit: $phpunit_result | PHPStan: $phpstan_result)\n"
            OVERALL_FAILED=1
        else
            SUMMARY="${SUMMARY}  PHP $ver : PASS\n"
        fi
    done

    if [ "$EXPLICIT_PHP" != "1" ]; then
        switch_php "$DEFAULT_PHP" > /dev/null 2>&1
    fi

    echo ""
    echo "============================="
    echo "         SUMMARY"
    echo "============================="
    printf "%b" "$SUMMARY"
    echo "============================="
    echo ""

    if [ "$OVERALL_FAILED" == "1" ]; then
        echo "FAILED: one or more PHP versions did not pass all checks"
        exit 1
    else
        echo "All checks passed!"
    fi

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
    echo "  test [phpunit args]    - Run PHPUnit + PHPStan on PHP 8.3, 8.4 and 8.5"
    echo "  versions               - Show available PHP versions"
    echo "  install-php <ver>      - Install PHP version (e.g., 8.3)"
    echo "  php <ver> <cmd> [args] - Use specific PHP version instead of default"
    echo ""
    echo "Examples:"
    echo "  ./dev.sh phpstan 6              - Run PHPStan level 6 on PHP $DEFAULT_PHP"
    echo "  ./dev.sh test                   - Run PHPUnit + PHPStan on PHP 8.3, 8.4, 8.5"
    echo "  ./dev.sh test --filter UtilTest - Run only UtilTest on all target PHP versions"
    echo "  ./dev.sh php 8.3 phpstan        - Run PHPStan on PHP 8.3"
    echo "  ./dev.sh php 8.4 test           - Run PHPUnit + PHPStan on PHP 8.4 only"
    echo "  ./dev.sh php 8.5 versions       - Show versions using PHP 8.5"
    exit 1
fi

exit 0
