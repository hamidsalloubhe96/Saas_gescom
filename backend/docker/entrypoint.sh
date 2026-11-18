#!/usr/bin/env bash
set -euo pipefail

cd /var/www/html

# Render fournit DATABASE_URL via render.yaml (fromDatabase.connectionString) -> DB_URL
if [ -n "${DATABASE_URL:-}" ] && [ -z "${DB_URL:-}" ]; then
  export DB_URL="$DATABASE_URL"
fi

php artisan config:cache
php artisan route:cache || true
php artisan event:cache || true
php artisan view:cache || true
php artisan storage:link || true

if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
  echo "==> Migrations base centrale (tenants, domains, users...)"
  php artisan migrate --force --no-interaction

  echo "==> Migrations de tous les tenants (un schéma PostgreSQL par tenant)"
  php artisan tenants:migrate --force --no-interaction
fi

# Premier d�marrage : premier administrateur de la console et premi�re entreprise (variables BOOTSTRAP_*, sans e-mail).
php artisan platform:bootstrap || echo "platform:bootstrap a �chou� (non bloquant)"

# Planificateur : exécute les tâches quotidiennes de la plateforme (abonnements, nettoyage, sauvegarde) à 6 h.
# Un seul processus suffit (un seul service). Désactivable avec SCHEDULER_ENABLED=false.
if [ "${SCHEDULER_ENABLED:-true}" = "true" ]; then
  echo "==> Planificateur démarré (console:daily à 6 h)"
  php artisan schedule:work >> /proc/1/fd/1 2>&1 &
fi

exec "$@"
