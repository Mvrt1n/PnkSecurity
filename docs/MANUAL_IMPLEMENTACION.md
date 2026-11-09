# Manual de Implementación — PnkSecurity · Carta Digital

**Asignatura:** Laboratorio de Programación Segura — INACAP Sede La Serena
**Evaluación:** Tercera Evaluación
**Autores:** Martín Díaz y Sujey Ramos
**Stack:** Node.js · Express · PostgreSQL · Docker · AWS · Cloudflare
**Versión del documento:** 1.0

---

## 1. Portada

Este documento es el **manual de implementación y procedimiento de despliegue**
del sistema *Carta Digital* de PnkSecurity. Describe, paso a paso y con comandos
reproducibles, cómo llevar el proyecto desde el código fuente hasta una
infraestructura productiva en AWS, administrada por DNS/HTTPS en Cloudflare.

El objetivo es que **otra persona pueda implementar el proyecto desde cero**
siguiendo estos pasos, sin conocimiento previo del sistema.

---

## 2. Introducción

*Carta Digital* es una aplicación web que permite a restaurantes publicar su
carta/menú en línea (categorías, ítems, precios, destacados), recibir
comentarios de clientes y ofrecer recomendaciones asistidas por IA. El sistema
nació como una aplicación PHP y, para esta evaluación, fue **migrado
completamente** a un stack moderno Node.js + Express + PostgreSQL con
arquitectura **MVC**, contenerizado con **Docker** y desplegable de forma
automatizada en **AWS** mediante **Infraestructura como Código (CloudFormation)**.

La migración conserva **todas** las funcionalidades y los datos originales de la
Carta Digital: no se eliminó ninguna característica ni se perdió información.

---

## 3. Objetivo

- Migrar la aplicación a **Node.js + Express + PostgreSQL** con arquitectura MVC.
- Separar **frontend** y **backend** y exponer una API/endpoints seguros.
- Contenerizar con **Docker** y operar **tres ambientes** (Desarrollo, QA,
  Producción) con configuraciones y credenciales independientes.
- Automatizar la infraestructura AWS por **código** (CloudFormation): **RDS
  PostgreSQL**, **Auto Scaling** y **Load Balancer**.
- Administrar dominio, **DNS y HTTPS** con **Cloudflare**.
- Integrar **IA** de forma segura (clave sólo en backend) al servicio de la Carta.
- Mantener buenas prácticas de **seguridad** (consultas parametrizadas, variables
  de entorno para secretos, manejo centralizado de errores, validación, HTTPS).

---

## 4. Arquitectura del sistema

```
                         Internet
                            │
                   ┌────────▼─────────┐
                   │    Cloudflare    │  DNS + HTTPS (TLS) + Proxy/WAF
                   └────────┬─────────┘
                            │  CNAME → DNS del ALB
                   ┌────────▼───────────────────────┐
                   │  AWS  (VPC 10.20.0.0/16)        │
                   │                                 │
                   │   ┌─────────────────────────┐   │
                   │   │ Application Load Balancer│   │  (subredes públicas)
                   │   └───────────┬─────────────┘   │
                   │       Target Group :3000        │
                   │   ┌───────────▼─────────────┐   │
                   │   │   Auto Scaling Group     │   │  (subredes públicas)
                   │   │  EC2 + Docker (app Node) │   │  min 2 / max 4
                   │   └───────────┬─────────────┘   │
                   │               │ 5432 (privado)  │
                   │   ┌───────────▼─────────────┐   │
                   │   │  RDS PostgreSQL          │   │  (subredes privadas)
                   │   │  cifrado + backups       │   │
                   │   └─────────────────────────┘   │
                   └─────────────────────────────────┘
```

**Separación frontend/backend.** El backend (Express) expone vistas server-side
(EJS) y endpoints. La capa de presentación (`src/views`, `public/`) está
desacoplada de la lógica (controladores/servicios/modelos). La comunicación con
PostgreSQL ocurre **sólo** desde los modelos, mediante consultas parametrizadas.

**Capas de red (defensa en profundidad).** El ALB es el único componente público
en HTTP/HTTPS; las instancias de la app sólo aceptan tráfico *desde* el ALB; y
RDS sólo acepta `5432` *desde* las instancias de la app. RDS no es accesible
públicamente.

---

## 5. Tecnologías utilizadas

| Capa | Tecnología | Uso |
|---|---|---|
| Runtime | Node.js 22 | Ejecución del backend |
| Framework | Express 4 | Rutas, middlewares, API |
| Vistas | EJS | Renderizado server-side de la Carta |
| Base de datos | PostgreSQL 16 | Persistencia (RDS en producción) |
| Driver | node-postgres (`pg`) | Consultas parametrizadas |
| Seguridad | helmet, express-rate-limit, express-validator, bcryptjs, express-session | Cabeceras, anti-fuerza bruta, validación, hashing, sesiones |
| Contenedores | Docker + Docker Compose | Build y orquestación |
| IaC | AWS CloudFormation | VPC, RDS, ALB, Auto Scaling |
| Cómputo | AWS EC2 + Auto Scaling | Escalabilidad horizontal |
| Balanceo | AWS Application Load Balancer | Distribución de tráfico |
| BD gestionada | AWS RDS PostgreSQL | Base de datos productiva |
| DNS/HTTPS | Cloudflare | Dominio, TLS, proxy/WAF |
| IA | API Messages (backend) | Recomendaciones de la Carta |

---

## 6. Requisitos previos

- Cuenta de AWS (en este proyecto: **AWS Academy Learner Lab**).
- **AWS CLI v2** configurado con las credenciales del laboratorio.
- **Git** y una cuenta de GitHub (repositorio público para la rama de entrega).
- **Docker** y **Docker Compose v2** (sólo para desarrollo/QA local).
- **Node.js 22** (opcional, para ejecutar/probar en local sin Docker).
- Un **dominio** administrado en **Cloudflare** (para DNS/HTTPS).
- Cliente **psql** (para migraciones manuales de BD).

---

## 7. Preparación del entorno

```bash
# 1) Clonar el repositorio y situarse en la rama de la 3ra evaluación
git clone https://github.com/Mvrt1n/PnkSecurity-Eval3.git
cd PnkSecurity-Eval3
git checkout feature/tercera-evaluacion

# 2) Revisar la estructura MVC
#   src/routes        -> rutas
#   src/controllers   -> controladores
#   src/models        -> acceso a datos (consultas parametrizadas)
#   src/services      -> lógica (seguridad, IA)
#   src/middlewares   -> seguridad, CSRF, validación, errores
#   src/views         -> vistas EJS (frontend server-side)
#   public            -> estáticos (css/js)
#   db/init           -> esquema + datos + rol de menor privilegio
#   deploy            -> CloudFormation y scripts de despliegue AWS
```

---

## 8. Configuración de Docker

El proyecto incluye un `Dockerfile` multi-etapa (imagen `node:22-alpine`,
usuario sin privilegios, `tini` para señales y `HEALTHCHECK`), y varios archivos
Compose:

| Archivo | Ambiente | Notas |
|---|---|---|
| `docker-compose.yml` | base | app + Postgres local |
| `docker-compose.override.yml` | Desarrollo | hot-reload, debug |
| `docker-compose.qa.yml` | QA | config aislada |
| `docker-compose.prod.yml` | Producción local | endurecido (read-only, límites) |
| `deploy/docker-compose.aws.yml` | AWS | **app-only** (BD = RDS) |

```bash
# Desarrollo (incluye Postgres en contenedor)
APP_ENV=development docker compose -p pnk_dev up -d --build

# QA (stack independiente)
APP_ENV=qa docker compose -p pnk_qa -f docker-compose.yml -f docker-compose.qa.yml up -d --build

# Producción local
APP_ENV=production docker compose -p pnk_prod -f docker-compose.yml -f docker-compose.prod.yml up -d --build
```

Cada `-p` crea un **proyecto Compose independiente** (contenedores, red y
volúmenes separados): así los tres ambientes conviven sin interferir.

---

## 9. Configuración de PostgreSQL

El modelo de datos conserva las tablas originales de la Carta Digital
(`restautantes`, `cartas`, `categorias`, `items`, `direcciones`, `comentarios`,
`usuarios`, `login_intentos`) portadas a PostgreSQL 16.

Scripts en `db/init/`:

- `01_schema.sql` — tablas, claves foráneas, índices.
- `02_seed.sql` — datos reales (restaurantes, cartas, categorías, ítems...).
- `03_app_role.sh` / rol `pnk_app` — usuario de **menor privilegio** (sólo DML).

En **desarrollo/QA** estos scripts se ejecutan automáticamente al crear el
contenedor de Postgres. En **producción (RDS)** se aplican con `deploy-db.sh` o
por el `user-data` de las instancias (ver §11).

**Por qué un rol de menor privilegio:** la app se conecta como `pnk_app`, que
sólo puede `SELECT/INSERT/UPDATE/DELETE`. No puede alterar el esquema (`DDL`).
Si un atacante lograra inyección, el daño posible queda acotado.

---

## 10. Configuración de AWS

Toda la infraestructura AWS se crea por **código** con CloudFormation
(`deploy/cloudformation/pnk-infra.yaml`). No se crean recursos a mano en la
consola, salvo verificación.

```bash
# 1) Configurar AWS CLI con las credenciales del Learner Lab
aws configure         # o exportar las credenciales temporales del lab

# 2) Preparar variables de despliegue (secretos fuera de Git)
cp deploy/deploy.env.example deploy/deploy.env
#   editar deploy/deploy.env con secretos fuertes (openssl rand -base64 24)
set -a; source deploy/deploy.env; set +a

# 3) Desplegar TODA la infraestructura por código
./deploy/deploy-app.sh
```

El script valida la plantilla (`aws cloudformation validate-template`) y luego
ejecuta `aws cloudformation deploy`, que crea/actualiza el stack completo.

> **Nota Learner Lab:** la plantilla reutiliza el perfil `LabInstanceProfile` y
> **no** crea roles IAM (el laboratorio restringe IAM). Las instancias usan
> tipos `t3.micro` y RDS `db.t3.micro`, dentro de los límites del lab.

---

## 11. Configuración de RDS

RDS PostgreSQL se define en la plantilla (`Database`):

- Motor **postgres 16**, clase `db.t3.micro`, almacenamiento `gp3` **cifrado**.
- En **subredes privadas** (`DbSubnetGroup`), `PubliclyAccessible: false`.
- **Security Group** `DbSecurityGroup`: sólo acepta `5432` desde el SG de la app.
- **Backups** automáticos (retención 7 días).
- `DeletionPolicy: Snapshot` (toma snapshot antes de borrar).

**Migraciones.** El `user-data` del Launch Template espera a que RDS acepte
conexiones (`pg_isready`), verifica si el esquema ya existe y, si no, aplica
`01_schema.sql`, `02_seed.sql` y crea el rol `pnk_app`. También puede hacerse
manualmente:

```bash
set -a; source deploy/deploy.env; set +a
RDS_ENDPOINT=<output-RdsEndpoint-del-stack> ./deploy/deploy-db.sh
```

**Conexión del backend.** La app lee `DB_HOST`, `DB_USER=pnk_app`, `DB_SSL=true`,
etc., desde `env/.env.production`, que el `user-data` genera apuntando al
endpoint de RDS. El backend ya soporta TLS a RDS (`ssl.rejectUnauthorized`).

---

## 12. Configuración de Auto Scaling

Definido en la plantilla:

- `LaunchTemplate` — AMI Amazon Linux 2023, `t3.micro`, SG de la app,
  `user-data` que instala Docker, clona el repo y levanta la app.
- `AutoScalingGroup` — `MinSize=2`, `DesiredCapacity=2`, `MaxSize=4`, repartido
  en dos subredes públicas (dos zonas de disponibilidad). `HealthCheckType: ELB`.
- `CpuScalingPolicy` — *Target Tracking* al **50 % de CPU promedio**: si la carga
  sube, agrega instancias; si baja, las retira.

**Cómo demostrarlo académicamente.** Generar carga (por ejemplo con `ab` o
`hey`) contra la URL del ALB y observar en la consola EC2 → Auto Scaling cómo el
grupo pasa de 2 a 3-4 instancias; al cesar la carga, vuelve a 2. Mostrar también
que, al terminar una instancia manualmente, el ASG crea otra para mantener el
mínimo.

---

## 13. Configuración del Load Balancer

- `LoadBalancer` — **Application Load Balancer** *internet-facing* en subredes
  públicas, con `AlbSecurityGroup` (80/443 desde Internet).
- `TargetGroup` — puerto 3000, **Health Check** a `/health` (la app expone ese
  endpoint). Sólo recibe tráfico quien responde `200`.
- `HttpListener` — puerto 80. Si se configura certificado TLS, redirige a 443;
  si no, sirve directo a la app.
- `HttpsListener` — puerto 443 (condicional, si se entrega `SSLCertificateArn`).

El output `AppUrl` entrega la URL pública `http://<alb-dns>/`. Ese DNS del ALB es
el destino del registro DNS en Cloudflare (§14-15).

---

## 14. Configuración de Cloudflare

> **Importante:** Cloudflare se configura **manualmente** en su panel. No se
> inventan registros ni certificados. A continuación, exactamente qué hacer.

1. Ingresar a **dash.cloudflare.com** y seleccionar el dominio.
2. Ir a **DNS → Records**.
3. Crear un registro que apunte al **DNS del ALB** (output `LoadBalancerDNS`):
   - **Tipo:** `CNAME`
   - **Name:** `@` (o `www`, o un subdominio como `carta`)
   - **Target:** el valor de `LoadBalancerDNS` (ej. `pnk-prod-alb-xxxx.us-east-1.elb.amazonaws.com`)
   - **Proxy status:** *Proxied* (nube naranja) para activar HTTPS/WAF de Cloudflare.
4. Guardar.

---

## 15. Configuración DNS

- Si el ápex del dominio (`@`) no admite CNAME en tu plan, usar **CNAME flattening**
  (Cloudflare lo soporta de forma nativa) o un subdominio (`carta.tudominio.cl`).
- Verificar propagación:

```bash
dig +short carta.tudominio.cl
nslookup carta.tudominio.cl
```

- El tráfico queda: **Cliente → Cloudflare (TLS) → ALB (HTTP/HTTPS) → app**.

---

## 16. Configuración HTTPS

Dos opciones, ambas válidas:

**A. TLS terminado en Cloudflare (recomendado para el lab).**
- En Cloudflare → **SSL/TLS**, modo **Full**.
- Cloudflare emite y renueva el certificado del borde automáticamente.
- El ALB puede quedar en HTTP :80; Cloudflare entrega HTTPS al cliente.
- La app corre tras proxy: `TRUST_PROXY=true`.

**B. TLS también en el ALB (extremo a extremo).**
- Solicitar un certificado en **AWS Certificate Manager (ACM)**.
- Pasar su ARN al stack: `SSL_CERT_ARN=<arn>` en `deploy/deploy.env`.
- La plantilla crea el `HttpsListener` :443 y redirige 80 → 443.
- En Cloudflare usar modo **Full (strict)**.

---

## 17. Configuración de Git

Flujo de ramas:

```
main      ← versión estable
 ├─ develop  ← integración
 │   └─ qa       ← validación
 └─ feature/tercera-evaluacion  ← ESTA entrega (aislada)
```

```bash
# Crear/ubicar la rama de la evaluación
git checkout -b feature/tercera-evaluacion       # si no existe
# ...commits ordenados por tema (feat/docs/chore)...
git push -u origin feature/tercera-evaluacion
```

Reglas aplicadas:
- **Nunca** se versionan `.env` reales ni `deploy/deploy.env` (están en `.gitignore`).
- Commits temáticos y descriptivos.
- La rama de la 3ra evaluación no sobrescribe `main`.

---

## 18. Despliegue del frontend

El frontend es server-side (EJS + estáticos en `public/`), servido por el mismo
backend Express dentro del contenedor. No requiere un despliegue separado: al
levantar la app (Docker en las instancias del ASG), las vistas de la Carta quedan
disponibles tras el ALB.

```bash
# Local (ver el frontend de la Carta)
APP_ENV=development docker compose -p pnk_dev up -d --build
# navegar a http://localhost:3000/  y  /carta/1
```

---

## 19. Despliegue del backend

En AWS, el backend se despliega automáticamente vía `user-data` (CloudFormation):
cada instancia instala Docker, clona el repo, genera `env/.env.production` hacia
RDS y ejecuta:

```bash
docker compose -f deploy/docker-compose.aws.yml -p pnk_aws up -d --build
```

No hay pasos manuales por instancia: el Auto Scaling Group las crea idénticas
desde el Launch Template.

---

## 20. Configuración de variables de entorno

Las credenciales **nunca** van en el código. Se cargan desde `env/.env.<ambiente>`:

| Variable | Descripción |
|---|---|
| `DB_HOST/DB_PORT/DB_NAME` | Conexión PostgreSQL (RDS en prod) |
| `DB_USER/DB_PASSWORD` | Rol de **menor privilegio** `pnk_app` |
| `DB_SSL` | `true` en RDS (conexión cifrada) |
| `SESSION_SECRET` | Firma de sesiones |
| `LOG_PEPPER` | Pepper para HMAC de datos sensibles |
| `BCRYPT_ROUNDS` | Coste de hashing de contraseñas |
| `IA_ENABLED/IA_API_KEY/IA_MODEL` | IA (clave **sólo backend**) |

En AWS, los secretos se inyectan como **parámetros del stack** (`NoEcho`) y el
`user-data` escribe `env/.env.production` con permisos `600`. Las plantillas
`*.example` se versionan; los valores reales no.

---

## 21. Verificación del funcionamiento

```bash
# Outputs del stack (URL pública, endpoint RDS, etc.)
aws cloudformation describe-stacks --stack-name pnk-eval3 \
  --query "Stacks[0].Outputs" --output table

# Salud de la app a través del Load Balancer
curl -i http://<alb-dns>/health         # espera 200 + JSON {status:"ok"}
curl -i http://<alb-dns>/carta/1         # espera 200 + HTML de la Carta

# Estado del Target Group (instancias sanas)
aws elbv2 describe-target-health --target-group-arn <tg-arn> \
  --query "TargetHealthDescriptions[].TargetHealth.State"
```

---

## 22. Pruebas

- **Humo (smoke):** `npm run smoke` (`scripts/smoke_test.js`) verifica endpoints
  clave contra una instancia en ejecución.
- **Base de datos:** consultas parametrizadas verificadas; el rol `pnk_app`
  confirmado sin privilegios DDL.
- **Escalabilidad:** prueba de carga contra el ALB y observación del ASG.

> **Principio aplicado:** no se declara exitosa una prueba que no se ejecutó. Las
> pruebas end-to-end (HTTP) requieren el entorno donde `npm` y la red estén
> disponibles (instancia EC2 / equipo local), y se ejecutan allí mostrando la
> salida real.

---

## 23. Seguridad

- **PHP2Shell (CVE-2026-109618 / CVE-2026-109619):** documentado en
  `docs/SEGURIDAD_PHP2Shell.md`. El proyecto migrado **no usa PHP**, por lo que
  no es afectado; se dejó constancia de las versiones PHP seguras para el
  despliegue PHP anterior.
- **SQL Injection:** todas las consultas usan placeholders `$1,$2,...` (`pg`).
- **Secretos:** sólo en variables de entorno / parámetros del stack; nunca en Git.
- **Cabeceras:** `helmet` (CSP, HSTS). **HTTPS** forzado tras proxy.
- **Sesiones:** `express-session` + store en Postgres; regeneración al autenticar.
- **Contraseñas:** `bcrypt`. **Anti-fuerza bruta:** `express-rate-limit` + registro
  de intentos (`login_intentos`) + hash señuelo (timing uniforme).
- **CSRF:** token de sesión con comparación en tiempo constante.
- **Validación:** `express-validator` en entradas.
- **Errores:** manejador centralizado (sin filtrar detalles internos).
- **IDOR:** verificación de propiedad al eliminar comentarios.
- **Red:** RDS privado; SGs por capas (ALB → app → BD).
- **Menor privilegio:** rol `pnk_app` sólo DML.

---

## 24. Solución de problemas

| Síntoma | Causa probable | Acción |
|---|---|---|
| Target Group *unhealthy* | app aún arrancando / build en curso | revisar `/var/log/pnk-userdata.log` en la instancia; esperar el *grace period* |
| `pg_isready` falla | SG de RDS o endpoint | confirmar `DbSecurityGroup` y que la app esté en el SG correcto |
| 502/504 en el ALB | app no escucha en 3000 | `docker ps`, `docker logs` en la instancia |
| CloudFormation `ROLLBACK` | parámetro inválido / límite del lab | leer *Events* del stack; corregir parámetro |
| Cloudflare error 521/522 | ALB no accesible | verificar DNS del ALB y SG 80/443 |
| IA no responde | `IA_API_KEY` vacía | usa el *fallback* local determinístico (es esperado) |

---

## 25. Conclusiones

La aplicación *Carta Digital* quedó **migrada, contenerizada, automatizada y
desplegable**: un stack Node.js + Express + PostgreSQL con arquitectura MVC,
tres ambientes Docker, e infraestructura AWS por código (CloudFormation) con RDS,
Auto Scaling y Load Balancer, además de DNS/HTTPS gestionados en Cloudflare y una
integración de IA segura. Se preservaron todas las funcionalidades y datos
originales y se aplicaron controles de seguridad en profundidad (consultas
parametrizadas, secretos fuera del código, menor privilegio, segmentación de red,
HTTPS y validación). El proyecto es reproducible siguiendo este manual y está
listo para demostrarse académicamente.

---

*Documento generado para la Tercera Evaluación — Laboratorio de Programación
Segura, INACAP Sede La Serena. Autores: Martín Díaz y Sujey Ramos.*
