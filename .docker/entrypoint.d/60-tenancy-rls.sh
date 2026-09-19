#!/bin/sh
script_name="tenancy-rls"

# Global configurations
: "${DISABLE_DEFAULT_CONFIG:=false}"
: "${APP_BASE_DIR:=/var/www/html}"

# Same defaults as the image's laravel-automations, so this runs wherever it migrates
: "${AUTORUN_ENABLED:=false}"
: "${AUTORUN_DEBUG:=false}"
: "${AUTORUN_LARAVEL_MIGRATION:=true}"

debug_log() {
    if [ "$LOG_OUTPUT_LEVEL" = "debug" ] || [ "$AUTORUN_DEBUG" = "true" ]; then
        echo "👉 DEBUG ($script_name): $1" >&2
    fi
}

if [ "$DISABLE_DEFAULT_CONFIG" = "true" ] || [ "$AUTORUN_ENABLED" = "false" ]; then
    debug_log "Skipping because DISABLE_DEFAULT_CONFIG is true or AUTORUN_ENABLED is false."
    exit 0
fi

if [ "$AUTORUN_LARAVEL_MIGRATION" != "true" ]; then
    debug_log "Skipping because AUTORUN_LARAVEL_MIGRATION is not true."
    exit 0
fi

# The entrypoint runs with set -e, so a failed migrate in laravel-automations
# has already stopped the container before this script is reached.
echo "🚀 $script_name: php artisan tenants:rls"

if ! php "$APP_BASE_DIR/artisan" tenants:rls; then
    echo "❌ $script_name: tenants:rls failed." >&2
    exit 1
fi
