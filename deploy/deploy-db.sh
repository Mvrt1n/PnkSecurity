#!/usr/bin/env bash
# =============================================================================
# deploy-db.sh  —  Despliegue / migraciones de la BASE DE DATOS en RDS.
#
# Nota: el user-data del Launch Template YA ejecuta estas migraciones la
# primera vez que arranca una instancia. Este script es el procedimiento
# MANUAL equivalente, para:
#   - Re-aplicar el esquema/seed en una RDS recien creada.
#   - Ejecutar las migraciones desde tu equipo o un bastion.
#
# Corre, en orden, contra RDS:
#   1) db/init/01_schema.sql   (tablas, claves, indices)
#   2) db/init/02_seed.sql     (datos reales de la Carta Digital)
#   3) Rol de aplicacion de MENOR PRIVILEGIO (solo DML): pnk_app
#
# Requisitos: cliente psql; acceso de red a RDS (el endpoint es privado, asi
# que se corre desde una instancia dentro de la VPC o por tunel SSM/SSH).
#
# Uso:
#   set -a; source deploy/deploy.env; set +a
#   RDS_ENDPOINT=<endpoint-de-rds> ./deploy/deploy-db.sh
# =============================================================================
set -Eeuo pipefail

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "$HERE/.." && pwd)"

: "${RDS_ENDPOINT:?Define RDS_ENDPOINT (output RdsEndpoint del stack)}"
: "${DB_MASTER_PASSWORD:?Define DB_MASTER_PASSWORD}"
: "${DB_APP_PASSWORD:?Define DB_APP_PASSWORD}"

DB_NAME="${DB_NAME:-pnk_carta_prod}"
DB_MASTER_USER="${DB_MASTER_USER:-pnk_admin}"
DB_APP_USER="${DB_APP_USER:-pnk_app}"
PORT="${DB_PORT:-5432}"

export PGPASSWORD="$DB_MASTER_PASSWORD"
PSQL=(psql -h "$RDS_ENDPOINT" -p "$PORT" -U "$DB_MASTER_USER" -d "$DB_NAME" -v ON_ERROR_STOP=1)

echo "==> Verificando conectividad a RDS ($RDS_ENDPOINT:$PORT)"
pg_isready -h "$RDS_ENDPOINT" -p "$PORT" -U "$DB_MASTER_USER"

echo "==> [1/3] Esquema"
"${PSQL[@]}" -f "$ROOT/db/init/01_schema.sql"

echo "==> [2/3] Datos (seed) de la Carta Digital"
"${PSQL[@]}" -f "$ROOT/db/init/02_seed.sql"

echo "==> [3/3] Rol de aplicacion de menor privilegio ($DB_APP_USER)"
"${PSQL[@]}" <<SQL
DO \$\$ BEGIN
  IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname='${DB_APP_USER}') THEN
    CREATE ROLE ${DB_APP_USER} LOGIN PASSWORD '${DB_APP_PASSWORD}';
  END IF;
END \$\$;
GRANT CONNECT ON DATABASE ${DB_NAME} TO ${DB_APP_USER};
GRANT USAGE ON SCHEMA public TO ${DB_APP_USER};
GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO ${DB_APP_USER};
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO ${DB_APP_USER};
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO ${DB_APP_USER};
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT USAGE, SELECT ON SEQUENCES TO ${DB_APP_USER};
SQL

unset PGPASSWORD
echo ""
echo "BD lista. El rol ${DB_APP_USER} solo tiene DML (sin DDL): previene"
echo "cambios de esquema desde la app (defensa en profundidad)."
