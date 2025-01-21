#!/bin/bash

php -v || exit 1

CMD="${1}"

if [ "$CMD" == "phpstan" ]
then
  DEFAULT2="5"
  LEVEL="${2:-$DEFAULT2}"
  echo "Running PHPStan with level $LEVEL"
  php bin/phpstan analyse -c phpstan.neon --memory-limit 4G --level $LEVEL || exit 1

else
  echo "Unknown command: [$CMD]"
  exit 1
fi

exit 0
