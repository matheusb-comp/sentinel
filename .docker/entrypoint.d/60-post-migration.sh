#!/bin/sh
script_name="post-migration"

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

artisan() {
    echo "🚀 $script_name: php artisan $*"

    if ! php "$APP_BASE_DIR/artisan" "$@"; then
        echo "❌ $script_name: $1 failed." >&2
        exit 1
    fi
}

# Everything below is derived from the migrated schema. --pending takes a value,
# which becomes the exit code when a migration is pending; without one it
# succeeds either way.
artisan migrate:status --pending=1

artisan series:setup
artisan series:maintain-partitions

# Last: the policies are derived from the tables that exist, partitions included.
artisan tenants:rls
