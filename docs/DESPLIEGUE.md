# Despliegue — AWS (EC2 + Docker) y Cloudflare

Runbook para publicar **PNK Carta Digital** (Node/Express/PostgreSQL en Docker) en
**AWS EC2**, con **Cloudflare** delante para DNS, HTTPS y protección. Pensado para
AWS Academy Learner Lab o una cuenta propia.

```
Usuario ─HTTPS─▶ Cloudflare (DNS, TLS, WAF, proxy) ─HTTPS/HTTP─▶ EC2 (Docker)
                                                              ├─ app  (Node/Express :3000)
                                                              └─ db   (PostgreSQL :5432, red interna)
```

## 1. AWS — Instancia EC2

### 1.1 Lanzar la instancia
- **AMI:** Amazon Linux 2023. **Tipo:** t3.small (o t2.micro para prueba).
- **Key pair:** la del laboratorio (p. ej. `vockey`).
- **Almacenamiento:** 20 GB gp3.

### 1.2 Security Group (mínimo privilegio de red)
| Puerto | Origen | Uso |
|--------|--------|-----|
| 22 (SSH) | **solo tu IP** | administración |
| 80 (HTTP) | Cloudflare / 0.0.0.0/0 | entra el proxy; redirige a HTTPS |
| 443 (HTTPS) | Cloudflare / 0.0.0.0/0 | tráfico web |
| 5432 | **nadie** | PostgreSQL NO se expone; vive en la red interna de Docker |

> Endurecimiento: cuando uses Cloudflare en modo proxy, restringe 80/443 a los
> [rangos de IP de Cloudflare](https://www.cloudflare.com/ips/) para que nadie llegue
> al origen saltándose Cloudflare.

### 1.3 Instalar Docker en la instancia (una vez)
```bash
sudo dnf update -y
sudo dnf install -y docker git
sudo systemctl enable --now docker
sudo usermod -aG docker ec2-user      # reconectar la sesión tras esto
# Docker Compose v2 (plugin)
sudo mkdir -p /usr/libexec/docker/cli-plugins
sudo curl -sSL https://github.com/docker/compose/releases/latest/download/docker-compose-linux-x86_64 \
  -o /usr/libexec/docker/cli-plugins/docker-compose
sudo chmod +x /usr/libexec/docker/cli-plugins/docker-compose
docker --version && docker compose version
```

### 1.4 Traer el código y configurar producción
```bash
git clone https://github.com/<TU_USUARIO>/pnk-carta-digital.git
cd pnk-carta-digital
git checkout main                       # rama de producción

# Crear el .env de producción con SECRETOS reales (no se versiona)
cp env/.env.production.example env/.env.production
# Generar secretos:
node -e "console.log('SESSION_SECRET='+require('crypto').randomBytes(32).toString('hex'))" 2>/dev/null \
  || openssl rand -hex 32   # si no hay node en el host, usar openssl y pegarlo
nano env/.env.production                 # completar contraseñas y secretos
```

Claves a definir en `env/.env.production`:
`POSTGRES_PASSWORD`, `DB_PASSWORD`, `SESSION_SECRET`, `LOG_PEPPER`, y si usas IA `IA_API_KEY`.
Deja `APP_FORCE_HTTPS=true` y `TRUST_PROXY=true` (vamos detrás de Cloudflare).

### 1.5 Levantar el stack de producción
```bash
APP_ENV=production docker compose -p pnk_prod \
  -f docker-compose.yml -f docker-compose.prod.yml up -d --build

docker compose -p pnk_prod ps            # ver estado y healthchecks
docker compose -p pnk_prod logs -f app   # logs de la app
```
La primera vez, el contenedor de PostgreSQL ejecuta `db/init/` (esquema + datos + rol
de app). El puerto 3000 queda publicado en el host para que Cloudflare/NGINX lo tome.

### 1.6 Verificación en el servidor
```bash
curl -s localhost:3000/health            # {"status":"ok","db":"up"}
curl -s "localhost:3000/carta/1" | grep -o "<title>[^<]*"   # restaurante
APP_BASE_URL=http://localhost:3000 node scripts/smoke_test.js   # smoke end-to-end
```

## 2. Cloudflare — DNS, HTTPS y protección

1. **Agregar el dominio** en Cloudflare y cambiar los *nameservers* del registrador a los de Cloudflare.
2. **DNS**: crear un registro `A` → IP pública del EC2, con el **proxy activado (nube naranja)**.
   - `tudominio.cl` → IP EC2 (proxied)
   - `qa.tudominio.cl` → IP EC2 (si publicas QA)
3. **SSL/TLS**: modo **Full (strict)** si pones un certificado en el origen, o **Full** para empezar.
   Activar *Always Use HTTPS* y *Automatic HTTPS Rewrites*.
4. **TLS en el origen (recomendado)**: emitir un *Origin Certificate* de Cloudflare y
   terminar TLS en un NGINX/Caddy delante de la app, o dejar que Cloudflare hable HTTP
   al origen solo si 80/443 están restringidos a sus IPs.
5. **Protección**:
   - **WAF** (managed rules) activado.
   - **Rate limiting** a nivel de Cloudflare para `/auth/login` y `/api/*`.
   - *Bot Fight Mode* y reglas de país si aplica.
6. La app ya emite **HSTS** y cookies `Secure`/`__Host-` cuando `APP_FORCE_HTTPS=true`,
   y confía en `X-Forwarded-Proto` de Cloudflare gracias a `TRUST_PROXY=true`.

## 3. Persistencia y backups

- Los datos viven en el volumen Docker `pnk_prod_pgdata` (sobrevive a reinicios/recreación de contenedores).
- **Backup lógico** periódico:
  ```bash
  docker exec pnk_prod-db-1 pg_dump -U pnk_admin pnk_carta_prod | gzip > backup_$(date +%F).sql.gz
  ```
- **Snapshot** del volumen EBS de la instancia para respaldo completo.
- (Opcional producción real) migrar la BD a **Amazon RDS for PostgreSQL** y apuntar
  `DB_HOST` al endpoint de RDS con `DB_SSL=true`; el código ya soporta TLS a la BD.

## 4. Actualizar la aplicación (deploy continuo)
```bash
cd pnk-carta-digital
git pull origin main
APP_ENV=production docker compose -p pnk_prod \
  -f docker-compose.yml -f docker-compose.prod.yml up -d --build
docker image prune -f
```

## 5. Monitoreo y registros
- `docker compose -p pnk_prod logs` → la app emite **logs estructurados en JSON** (aptos para CloudWatch/un SIEM).
- Healthcheck de Docker (`/health`) marca el contenedor como *unhealthy* si la BD cae.
- Cloudflare Analytics para tráfico y amenazas.
