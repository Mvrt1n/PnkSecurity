# Guía de Despliegue en AWS — PNK Security (versión corregida)

> Despliegue de la rama `mejoras` (versión corregida) en AWS. Elige UNA de las dos
> opciones. La Opción A (EC2) es la más común para una app PHP/MySQL de laboratorio.

## Opción A — EC2 (Amazon Linux 2023) con Apache + PHP + MariaDB

### 1. Crear la instancia
- EC2 → Launch instance → Amazon Linux 2023, tipo `t3.micro` (capa gratuita).
- Security group: abrir **80 (HTTP)**, **443 (HTTPS)** y **22 (SSH)** solo desde tu IP.
- Descarga el par de claves (.pem) para conectarte por SSH.

### 2. Instalar el stack (conéctate por SSH y ejecuta)
```bash
sudo dnf update -y
sudo dnf install -y httpd php php-mysqlnd php-mbstring php-xml mariadb105-server git
sudo systemctl enable --now httpd mariadb
sudo mysql_secure_installation          # define clave de root de MySQL
```

### 3. Publicar la aplicación
```bash
cd /var/www
sudo git clone -b mejoras https://github.com/TU_USUARIO/pnk-security.git repo
sudo rm -rf /var/www/html && sudo ln -s /var/www/repo/pnkSecurity /var/www/html
sudo sed -i 's/AllowOverride None/AllowOverride All/' /etc/httpd/conf/httpd.conf
sudo systemctl restart httpd
```

### 4. Base de datos y usuario de mínimo privilegio
```bash
sudo mysql -u root -p < /var/www/repo/Script_BD/pnk_security.sql
# edita la clave en 02_usuario_app.sql y ejecútalo
sudo mysql -u root -p < /var/www/repo/Script_BD/02_usuario_app.sql
```

### 5. Configuración (secretos FUERA del código)
```bash
sudo cp /var/www/repo/config/config.example.php /var/www/repo/config/config.local.php
sudo nano /var/www/repo/config/config.local.php   # APP_ENV=prod, DB_USER=pnk_app, DB_PASS=...
sudo chmod 600 /var/www/repo/config/config.local.php
sudo chown apache:apache /var/www/repo/config/config.local.php
sudo mkdir -p /var/www/repo/logs && sudo chown apache:apache /var/www/repo/logs
```

### 6. HTTPS (resuelve EXC-01)
```bash
sudo dnf install -y certbot python3-certbot-apache mod_ssl
sudo certbot --apache -d TU-DOMINIO.cl        # requiere un dominio apuntando a la IP
# si no tienes dominio: usa el vhost config/apache-vhost-ssl.conf con un cert de prueba
```
En `config.local.php` deja `APP_FORCE_HTTPS=true`. Verifica la cookie `__Host-...; Secure` y HSTS.

### 7. Probar
Abre `http://IP-PUBLICA/index.php?id=1`. Crea un usuario:
```bash
cd /var/www/repo && sudo php herramientas/crear_usuario.php "Admin" admin@ejemplo.cl
```

---

## Opción B — Contenedor (Docker) en EC2 o ECS

El repositorio incluye `Dockerfile` y `docker-compose.yml`:
```bash
git clone -b mejoras https://github.com/TU_USUARIO/pnk-security.git && cd pnk-security
docker compose up -d --build
# la app queda en http://localhost:8080  (o la IP pública de la EC2)
```

---

## Evidencia para el informe
- Captura de la instancia EC2 en ejecución (consola AWS).
- Captura del navegador con la URL pública mostrando la carta.
- Salida de `php tests/pruebas_seguridad.php` en el servidor (27/27).
- Captura de las cabeceras con HSTS/Secure (`curl -I https://TU-DOMINIO`).

> Nota de costos: usa `t3.micro` (capa gratuita) y **apaga/termina la instancia** al terminar
> para no generar cobros.
