#!/bin/bash
source $HOME/.profile
# Run this script with bash if it's being run with something else (like sh).
if [ ! "$BASH_VERSION" ] ; then
    exec /bin/bash "$0" "$@"
fi

# Where the script file lives.
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

cd $SCRIPT_DIR

DRUSH="/usr/local/bin/drush"

$DRUSH cc drush

$DRUSH azure:upload

exit 1
