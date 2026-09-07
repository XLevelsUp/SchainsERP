#!/bin/bash
set -euo pipefail

# Render gives this service no shell. Everything the app needs in order to
# come up healthy has to happen here, and this log is the only diagnostic
# anyone gets when it doesn't - so each step says what it decided and why.

PORT="${PORT:-10000}"

echo "===================================="
echo "Starting Laravel application"
echo "Render PORT: ${PORT}"
echo "===================================="

# Configure Apache to use Render's assigned port
sed -i "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf

sed -i \
    "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/" \
    /etc/apache2/sites-available/000-default.conf

echo "Clearing Laravel caches..."

php artisan config:clear || true
php artisan route:clear || true
php artisan view:clear || true

# --- Passport migration ledger baseline ------------------------------------
# The duplicate-migration bug left the possibility of a database where the
# five oauth_* tables exist but their `migrations` rows do not - in which
# case the surviving migration set tries to re-create them and fails with
# the same "Duplicate table" error the duplicates caused. Without a shell on
# Render that is undiagnosable, so repair it here: for each Passport table
# that already exists with no ledger row, record it as applied. Idempotent,
# and a no-op on a healthy or empty database.
echo "Checking the Passport migration ledger..."

php artisan tinker --execute='
    $map = [
        "oauth_auth_codes"    => "2026_09_01_000437_create_oauth_auth_codes_table",
        "oauth_access_tokens" => "2026_09_01_000438_create_oauth_access_tokens_table",
        "oauth_refresh_tokens"=> "2026_09_01_000439_create_oauth_refresh_tokens_table",
        "oauth_clients"       => "2026_09_01_000440_create_oauth_clients_table",
        "oauth_device_codes"  => "2026_09_01_000441_create_oauth_device_codes_table",
    ];
    if (! Schema::hasTable("migrations")) {
        echo "  No migrations table yet - fresh database, nothing to baseline.\n";
    } else {
        $batch = max(1, (int) DB::table("migrations")->max("batch"));
        $added = 0;
        foreach ($map as $table => $migration) {
            if (Schema::hasTable($table) && ! DB::table("migrations")->where("migration", $migration)->exists()) {
                DB::table("migrations")->insert(["migration" => $migration, "batch" => $batch]);
                echo "  Baselined {$migration} - table existed with no ledger row.\n";
                $added++;
            }
        }
        echo $added === 0
            ? "  Ledger is consistent, no baseline needed.\n"
            : "  Baselined {$added} Passport migration(s).\n";
    }
' || echo "!! Ledger check failed - continuing, migrate will report the real problem."

# --- Database migrations ---------------------------------------------------
# Kept fatal on purpose: booting Apache against a half-applied schema turns
# one clear failure here into scattered 500s on random endpoints. The status
# dump on failure is what replaces `php artisan migrate:status` in a shell.
echo "Running database migrations..."

if ! php artisan migrate --force --no-interaction; then
    echo "!! Migrations FAILED - refusing to start Apache against a partial schema."
    echo "!! Ledger at the point of failure:"
    php artisan migrate:status || true
    exit 1
fi

# --- Passport signing keys -------------------------------------------------
# storage/*.key is gitignored, so the image never carries a key pair and the
# container filesystem is ephemeral. Without this block every login 500s on
# a missing key path.
if [ -n "${PASSPORT_PRIVATE_KEY:-}" ] && [ -n "${PASSPORT_PUBLIC_KEY:-}" ]; then
    echo "Passport keys: using PASSPORT_PRIVATE_KEY / PASSPORT_PUBLIC_KEY from the environment."
elif [ -f storage/oauth-private.key ] && [ -f storage/oauth-public.key ]; then
    echo "Passport keys: already present in storage/, leaving them untouched."
else
    echo "Passport keys: none found - generating a pair."
    echo "   WARNING: storage/ is not persisted on Render, so this pair is"
    echo "   regenerated on every restart and every access token issued"
    echo "   before the restart stops validating. Set PASSPORT_PRIVATE_KEY"
    echo "   and PASSPORT_PUBLIC_KEY as Render environment variables to make"
    echo "   tokens survive a redeploy."
    php artisan passport:keys --force --no-interaction
fi

# --- Passport personal access client ---------------------------------------
# AuthController uses $user->createToken(...)->accessToken, which resolves a
# client row with the `personal_access` grant. Nothing creates that row, and
# `passport:client --personal` is not idempotent, so check before creating.
# Non-fatal: a bug in this block should not be able to brick every deploy.
echo "Ensuring a Passport personal access client exists..."

if ! php artisan tinker --execute='
    $repo = app(Laravel\Passport\ClientRepository::class);
    $provider = config("auth.guards.api.provider");
    try {
        $client = $repo->personalAccessClient($provider);
        echo "Personal access client already present (id {$client->id}).\n";
    } catch (RuntimeException $e) {
        $client = $repo->createPersonalAccessGrantClient("SchainsERP Personal Access Client", $provider);
        echo "Created personal access client (id {$client->id}).\n";
    }
'; then
    echo "!! Could not verify or create the personal access client."
    echo "!! Starting anyway - non-auth routes will work, but POST /api/login"
    echo "!! will 500 with \"Personal access client not found\" until this is fixed."
fi

echo "Caching Laravel configuration..."

php artisan config:cache

echo "Starting Apache..."

exec apache2-foreground
