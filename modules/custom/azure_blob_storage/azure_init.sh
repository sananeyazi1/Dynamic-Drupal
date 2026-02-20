#!/bin/bash
# Run this script with bash if it's being run with someting else (like sh).
if [ ! "$BASH_VERSION" ] ; then
    exec /bin/bash "$0" "$@"
fi

# Where the script file lives.
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

cd $SCRIPT_DIR

DRUSH="/usr/local/bin/drush"

$DRUSH cc drush

$DRUSH azure:init

exit 1
