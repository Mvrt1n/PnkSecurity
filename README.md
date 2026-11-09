# PNK Carta Digital — Node.js + Express + PostgreSQL (Tercera evaluación)

Backend **MVC** que sirve la **Carta Digital** multi-restaurante. Es el puerto seguro
del proyecto PHP/MySQL original a **Node.js + Express + PostgreSQL**, en **Docker**, con
**tres ambientes** (dev/QA/prod), listo para **Git + Cloudflare + AWS** e integra un
**asistente de IA** del menú.

> La versión PHP original se conserva en el historial del repositorio (rama de la 2ª
> evaluación). Aquí no se elimina funcionalidad: se migra de stack.

## 1. Arquitectura MVC

| Capa | Carpeta | Responsabilidad |
|------|---------|-----------------|
| **Model** | `src/models/` | Acceso a datos PostgreSQL con consultas **parametrizadas** |
| **View** | `src/views/` (EJS) | Interfaz: carta, carrito, portada, error |
| **Controller** | `src/controllers/` | Lógica de cada caso de uso |
| Rutas | `src/routes/` | Mapea URLs → controllers (+ validación, rate-limit, CSRF) |
| Servicios | `src/services/` | Lógica reutilizable (seguridad, IA) |
| Middlewares | `src/middlewares/` | Seguridad, CSRF, auth, validación, errores |
| Config | `src/config/` | Entorno (`env.js`) y pool de BD (`db.js`) |

```
carta-digital-node/
├─ src/{config,models,views,controllers,services,middlewares,routes}/
│  ├─ app.js        # ensamblado Express (vistas, sesión PG, seguridad, rutas)
│  └─ server.js     # arranque + verificación real de BD + apagado ordenado
├─ db/init/         # 01_schema.sql, 02_seed.sql, 03_app_role.sh (autocarga en Postgres)
├─ public/          # css, js, img (frontend estático)
├─ env/             # .env.development | .env.qa.example | .env.production.example
├─ scripts/         # healthcheck, crear_usuario, smoke_test
├─ Dockerfile · docker-compose*.yml
└─ docs/            # SEGURIDAD_PHP2Shell.md, (DESPLIEGUE.md)
```

## 2. Funcionalidad de la Carta Digital (conservada)

- Portada con restaurantes y **carta por restaurante** (`/carta/:id`): cartas → categorías → ítems.
- **Destacados** del restaurante.
- **Carrito** en sesión del servidor (precios validados contra BD; “vaciar” ya **no** cierra la sesión — bug del PHP corregido).
- **Comentarios** con autor autenticado y **borrado solo del propio** (anti-IDOR).
- **Login/Logout** con anti-fuerza bruta, bcrypt y regeneración de sesión.
- **Asistente de IA** del menú (nuevo).

## 3. Cómo ejecutar

### 3.1 Con Docker (los tres ambientes)
Cada ambiente es un **stack independiente** (project name propio ⇒ red, volumen y **datos separados**):

```bash
# DESARROLLO (recarga en caliente, Postgres expuesto en localhost:5432)
docker compose -p pnk_dev up --build
#   App:  http://localhost:3000

# QA
APP_ENV=qa docker compose -p pnk_qa \
  -f docker-compose.yml -f docker-compose.qa.yml up -d --build
#   App:  http://localhost:3001   (APP_PORT en env/.env.qa)

# PRODUCCIÓN (endurecido: read-only, HTTPS forzado, sin debug)
APP_ENV=production docker compose -p pnk_prod \
  -f docker-compose.yml -f docker-compose.prod.yml up -d --build
```

Para QA/prod, primero copiar las plantillas y completar secretos:
```bash
cp env/.env.qa.example         env/.env.qa
cp env/.env.production.example env/.env.production
# generar secretos:  node -e "console.log(require('crypto').randomBytes(32).toString('hex'))"
```

El contenedor de Postgres carga **solo/schema+seed+rol** la primera vez (volumen vacío).

### 3.2 Local sin Docker (requiere Node 20+ y PostgreSQL 16)
```bash
npm ci                      # o npm install
createdb pnk_carta
psql -d pnk_carta -f db/init/01_schema.sql
psql -d pnk_carta -f db/init/02_seed.sql
# crear rol pnk_app (ver db/init/03_app_role.sh) y ajustar env/.env.development
npm run dev                 # http://localhost:3000
node scripts/crear_usuario.js "Ana" ana@ejemplo.cl "ClaveSegura123"
```

## 4. Seguridad (requisitos del profesor + OWASP ASVS v5)

| Control | Implementación |
|--------|----------------|
| **SQL Injection** | Todas las consultas vía `db.query(text, params)` con `$1,$2…`; rol de app **solo DML** |
| **Credenciales** | Siempre por **variables de entorno**; nunca en código; `.env` reales en `.gitignore` |
| **XSS** | Escape automático de EJS (`<%= %>`) en todo dato de BD/usuario |
| **CSRF** | Token por sesión + verificación tiempo-constante en POST/PUT/DELETE |
| **Autenticación** | bcrypt (coste 12), comparación constante anti-enumeración, anti-fuerza bruta (cuenta+IP) |
| **Sesión** | `httpOnly`, `sameSite`, `Secure` en prod, `__Host-`; regeneración al login; store en PostgreSQL |
| **Cabeceras** | helmet: CSP, HSTS (prod), nosniff, frameguard |
| **Autorización** | Control de propiedad anti-IDOR en comentarios |
| **Errores** | Manejo **centralizado**; sin stack traces en producción |
| **Rate limiting** | Límites por ruta (login, IA, escrituras) |
| **Validación** | `express-validator` en entradas (tipos, longitudes) |
| **PHP2Shell** | Eliminado de raíz al retirar PHP; ver `docs/SEGURIDAD_PHP2Shell.md` |

## 5. Pruebas ejecutadas (reales)

Ejecutadas el 2026-11-09 en este entorno:

- ✅ **Sintaxis**: `node --check` en los **25** archivos `.js` → OK.
- ✅ **PostgreSQL 16**: carga de `01_schema.sql` + `02_seed.sql` sin errores.
- ✅ **Consultas de modelos** (como rol `pnk_app`): restaurante #1 = *Afríca Restobar*; 3 destacados; 8 cartas visibles; 5 ítems.
- ✅ **Anti-IDOR**: usuario B no puede borrar comentario de A (`DELETE 0`); el dueño sí (`DELETE 1`).
- ✅ **Mínimo privilegio**: `pnk_app` no puede `DROP TABLE` ni `CREATE TABLE` (denegado).

Pendiente de ejecutar donde el registro npm esté accesible (no lo está en este contenedor):
`npm ci` y luego **smoke test end-to-end** del servidor:
```bash
npm run smoke        # scripts/smoke_test.js: /health, portada, /carta/1, 404, CSRF 403
```

## 6. Git · Cloudflare · AWS (resumen; detalle en docs/DESPLIEGUE.md)

- **Git flow**: `develop` → `qa` → `main` (prod). `.gitignore` excluye `.env`/secretos. PRs por ambiente.
- **Cloudflare**: DNS del dominio, TLS/HTTPS gestionado, proxy (naranja) con WAF/rate-limit delante del origen AWS; `APP_FORCE_HTTPS=true` + `TRUST_PROXY=true` en prod.
- **AWS**: EC2 con Docker ejecutando el stack prod (`docker-compose.prod.yml`); Security Group abre solo 80/443 (y SSH restringido); Postgres en contenedor con volumen persistente (o migrar a RDS); backups del volumen/snapshot.

## 7. IA — Asistente del menú

`POST /api/ia/recomendar { pregunta, restauranteId }` recomienda platos **del propio menú**.
- La **API key vive solo en el backend** (`IA_API_KEY`), nunca llega al frontend.
- Si `IA_ENABLED=false` o no hay key, usa un **motor local determinista** (sin red), así la demo funciona siempre.
- Rate-limit dedicado. Proveedor configurable (`IA_PROVIDER`, por defecto Anthropic Messages API).
