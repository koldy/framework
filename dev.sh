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
if [ "$1" == "php" ] && [ -n "$2" ]; then
    switch_php "$2" || exit 1
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
    echo "Running tests on $(php -v | head -1)"
    php bin/phpunit --configuration phpunit.xml.dist "${@:2}" || exit 1

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
    echo "  test [phpunit args]    - Run tests (extra args passed to PHPUnit)"
    echo "  versions               - Show available PHP versions"
    echo "  install-php <ver>      - Install PHP version (e.g., 8.3)"
    echo "  php <ver> <cmd> [args] - Use specific PHP version instead of default"
    echo ""
    echo "Examples:"
    echo "  ./dev.sh phpstan 6              - Run PHPStan level 6 on PHP $DEFAULT_PHP"
    echo "  ./dev.sh test                   - Run tests on PHP $DEFAULT_PHP"
    echo "  ./dev.sh test --filter UtilTest - Run only UtilTest on PHP $DEFAULT_PHP"
    echo "  ./dev.sh php 8.1 phpstan        - Run PHPStan on PHP 8.1"
    echo "  ./dev.sh php 8.1 test           - Run tests on PHP 8.1"
    echo "  ./dev.sh php 8.5 versions       - Show versions using PHP 8.5"
    exit 1
fi

exit 0
