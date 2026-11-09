#!/bin/sh
# Crea el rol de aplicación de MENOR PRIVILEGIO (solo DML, sin DDL).
# La app NUNCA se conecta como superusuario. Credenciales por variables de entorno.
set -e

: "${APP_DB_USER:=pnk_app}"
: "${APP_DB_PASSWORD:?APP_DB_PASSWORD es obligatorio}"

psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" <<-EOSQL
  DO \$do\$
  BEGIN
    IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = '${APP_DB_USER}') THEN
      CREATE ROLE ${APP_DB_USER} LOGIN PASSWORD '${APP_DB_PASSWORD}';
    END IF;
  END
  \$do\$;

  GRANT CONNECT ON DATABASE ${POSTGRES_DB} TO ${APP_DB_USER};
  GRANT USAGE  ON SCHEMA public TO ${APP_DB_USER};
  GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES    IN SCHEMA public TO ${APP_DB_USER};
  GRANT USAGE, SELECT                 ON ALL SEQUENCES  IN SCHEMA public TO ${APP_DB_USER};
  -- Objetos futuros heredan los mismos permisos mínimos
  ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO ${APP_DB_USER};
  ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT USAGE, SELECT ON SEQUENCES TO ${APP_DB_USER};
EOSQL

echo "Rol de aplicación '${APP_DB_USER}' listo (solo DML)."
