#!/bin/sh
set -eu

test_database="${POSTGRES_DB}_test"

psql --set ON_ERROR_STOP=1 \
    --username "$POSTGRES_USER" \
    --dbname "$POSTGRES_DB" \
    --set test_database="$test_database" \
    --set database_owner="$POSTGRES_USER" <<'SQL'
SELECT format('CREATE DATABASE %I OWNER %I', :'test_database', :'database_owner')
WHERE NOT EXISTS (
    SELECT FROM pg_database WHERE datname = :'test_database'
) \gexec
SQL

psql --set ON_ERROR_STOP=1 \
    --username "$POSTGRES_USER" \
    --dbname "$test_database" \
    --command 'CREATE EXTENSION IF NOT EXISTS postgis;'
