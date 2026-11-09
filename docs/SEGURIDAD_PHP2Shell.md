# Remediación de seguridad — PHP2Shell (CVE-2026-109618 / CVE-2026-109619)

**Fecha:** 2026-11-09 · **Responsables:** Martín Díaz, Sujey Ramos (INACAP La Serena)

## 1. Qué es

Dos vulnerabilidades **críticas** en el núcleo de PHP, publicadas el **21-oct-2026**,
agrupadas como **“PHP2Shell”**. Permiten **ejecución remota de código (RCE) sin
autenticación**: una sola petición puede dispararlas antes de que corra el código de la app.

| CVE | Causa raíz | Vector |
|-----|-----------|--------|
| **CVE-2026-109618** | Corrupción por manejo de Out-Of-Memory: limpieza de la petición trata datos parcialmente inicializados como válidos | Remoto, sin auth |
| **CVE-2026-109619** | Estado global “stale”: estado de una petición sobrevive tras liberar su memoria y lo reutiliza otra petición en el mismo worker | Remoto, sin auth |

Afecta a **toda instalación que sirva PHP por web** (PHP-FPM, Apache mod_php, php-cgi,
FrankenPHP, LiteSpeed). mod_php es el escenario de mayor riesgo.

**Fuentes oficiales/técnicas:** Help Net Security, Tenable, Datadog Security Labs,
Sansec, OffSec, VulnCheck, watchTowr, SOCRadar (21-oct-2026).

## 2. Versiones corregidas (fuente oficial)

| Rama PHP | Primera versión CORREGIDA |
|----------|---------------------------|
| 8.5 | **8.5.12** |
| 8.4 | **8.4.27** |
| 8.3 | **8.3.36** |
| 8.2 | **8.2.35** |
| 8.1 y anteriores | Sin soporte: backports mínimos del equipo de seguridad o parche del proveedor de la distro |

## 3. Diagnóstico en nuestro proyecto

| Entorno | PHP detectado | ¿Vulnerable? | Acción |
|---------|---------------|--------------|--------|
| Contenedor de desarrollo (lab) | **8.3.6** | **Sí** (fix: 8.3.36) | Migración a Node elimina PHP; ver §4 |
| EC2 del despliegue anterior (AL2023) | **8.5.11** | **Sí** (fix: 8.5.12) | Parchear mientras exista (§4) |

## 4. Remediación

### 4.1 Eliminación de raíz (recomendado y en curso)
La **Tercera evaluación migra el backend a Node.js/Express/PostgreSQL**. Al retirar PHP
de la ruta de ejecución web, **esta clase de RCE deja de aplicar al producto final**:
ya no hay intérprete PHP sirviendo peticiones.

### 4.2 Parche inmediato del EC2 aún en PHP (mientras conviva)
En Amazon Linux 2023, con la rama 8.5:

```bash
sudo dnf clean all && sudo dnf makecache
sudo dnf update -y 'php*'          # debe llevar php a >= 8.5.12
php -v                              # verificar 8.5.12+
sudo systemctl restart php-fpm httpd 2>/dev/null || sudo systemctl restart httpd
```

Si el repositorio aún no ofrece 8.5.12, aplicar mitigación temporal (reducir superficie):
limitar exposición por WAF/Cloudflare, y priorizar el corte a Node.

### 4.3 Verificación
```bash
php -r 'echo PHP_VERSION, PHP_EOL;'   # >= 8.3.36 / 8.5.12 según rama
```
Comparar contra las versiones de §2. Documentar fecha, versión previa y nueva.

## 5. Dependencias del nuevo stack (Node) — estado

- **Node.js**: usar **LTS** (20.x o 22.x). Este proyecto declara `engines.node >= 20`.
- Dependencias fijadas en `package.json` con rangos recientes; ejecutar
  `npm audit` en CI y mantener `npm ci` reproducible con lockfile.
- PostgreSQL 16 (imagen oficial `postgres:16-alpine`).

> Nota: mantener este documento junto al informe. La verificación de versiones se hizo
> contra fuentes oficiales el 2026-11-09; revalidar antes de la presentación.
