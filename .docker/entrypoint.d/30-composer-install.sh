#!/bin/sh
script_name="composer-install"

# Global configurations
: "${DISABLE_DEFAULT_CONFIG:=false}"
: "${APP_BASE_DIR:=/var/www/html}"

# Set default values for automations
: "${AUTORUN_ENABLED:=false}"
: "${AUTORUN_DEBUG:=false}"
: "${AUTORUN_COMPOSER_INSTALL:=false}"
: "${AUTORUN_COMPOSER_INSTALL_ARGS:=--no-interaction --prefer-dist}"

debug_log() {
    if [ "$LOG_OUTPUT_LEVEL" = "debug" ] || [ "$AUTORUN_DEBUG" = "true" ]; then
        echo "👉 DEBUG ($script_name): $1" >&2
    fi
}

if [ "$DISABLE_DEFAULT_CONFIG" = "true" ] || [ "$AUTORUN_ENABLED" = "false" ]; then
    debug_log "Skipping because DISABLE_DEFAULT_CONFIG is true or AUTORUN_ENABLED is false."
    exit 0
fi

if [ "$AUTORUN_COMPOSER_INSTALL" != "true" ]; then
    debug_log "Skipping because AUTORUN_COMPOSER_INSTALL is not true."
    exit 0
fi

cd "$APP_BASE_DIR" || exit 1

echo "🚀 $script_name: composer install $AUTORUN_COMPOSER_INSTALL_ARGS"

# The arguments are split into words on purpose.
# shellcheck disable=SC2086
if ! composer install $AUTORUN_COMPOSER_INSTALL_ARGS; then
    echo "❌ $script_name: composer install failed." >&2
    exit 1
fi
