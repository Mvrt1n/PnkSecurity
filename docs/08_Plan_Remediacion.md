# Plan de Remediación, Riesgo Residual, Dependencias y RACI — PNK Security

## 1. Estado de los hallazgos

| ID | Título | Severidad | Estado | Prueba de cierre |
|---|---|---|---|---|
| PNK-01 | SQLi en el login / bypass | Crítica | Cerrado | T01, T08 |
| PNK-02 | SQLi en parámetros públicos | Crítica | Cerrado | T09, T10, T18 |
| PNK-03 | Contraseñas en claro / cuentas por defecto | Alta | Cerrado (código). Migrar la BD existente: ACC-04 | T02, T06 |
| PNK-04 | Credenciales `root` en el código | Alta | Cerrado (código). Vault: EXC-02 | T24 |
| PNK-05 | XSS almacenado | Alta | Cerrado | T13, T20 |
| PNK-06 | Control de acceso en comentarios | Alta | Cerrado | T12, T14-T16 |
| PNK-07 | CSRF | Media | Cerrado | T04, T05, T17 |
| PNK-08 | Sesiones | Media | Cerrado (Secure: EXC-01) | T02, T03, T05 |
| PNK-09 | Fuerza bruta | Media | Cerrado | T07, T08 |
| PNK-10 | Cuentas inactivas | Media | Cerrado | T06 |
| PNK-11 | Lógica del carrito | Baja | Cerrado | T18, T19 |
| PNK-12 | XSS reflejado | Alta | Cerrado | T11 |
| PNK-13 | Dependencias vulnerables | Media | Cerrado (Bootstrap 5: ACC-07) | SBOM |
| PNK-14 | Cabeceras / HTTPS | Media | Cabeceras cerradas. TLS: EXC-01 | T21 |
| PNK-15 | Sin logging | Media | Cerrado (SIEM: ACC-03) | T22 |
| PNK-16 | Errores / divulgación | Baja | Cerrado | T23 |
| PNK-17 | Archivos innecesarios | Baja | Cerrado (verificar en Apache) | Checklist del Gate 4 |

## 2. Riesgo residual

| Riesgo | Nivel | Motivo | Tratamiento |
|---|---|---|---|
| Tráfico sin cifrar (TM-14) | **Alto** en PROD sin TLS | La sesión y las credenciales viajan en claro | EXC-01 / ACC-01: obligatorio antes de PROD |
| Toma de cuentas sin MFA (TM-02) | Medio | Sólo un factor | EXC-03 / ACC-08 |
| Secretos en archivo local (TM-18) | Bajo-Medio | Sin vault ni rotación | EXC-02 |
| Logs sólo locales (TM-08) | Medio | Un atacante con acceso al servidor puede borrarlos | ACC-03 |
| `style-src 'unsafe-inline'` | Bajo | Necesario por la plantilla y Owl Carousel | Mover los estilos a CSS |
| Bootstrap 4 sin soporte del fabricante | Bajo | Sin CVE abiertos conocidos en 4.6.2 a la fecha. Revalidar | ACC-07 |
| Google Fonts (PRIV-04) | Bajo-Medio | Envía la IP a un tercero | ACC-06 |
| Validación sólo con SQLite | Bajo | SQL estándar y parametrizado | Smoke test en MySQL (Gate 4) |

## 3. SCA — inventario de componentes (resumen del SBOM `sbom.cdx.json`)

| Componente | Versión | Fuente | Licencia | Vulnerabilidades conocidas* | Uso |
|---|---|---|---|---|---|
| jQuery | 3.7.1 | github.com/jquery/jquery-dist (tag 3.7.1) | MIT | Ninguna conocida | Sí |
| Bootstrap | 4.6.2 | github.com/twbs/bootstrap (tag v4.6.2) | MIT | Ninguna conocida; rama 4.x sin soporte desde ene-2023 | Sí |
| Owl Carousel | 2.3.4 | Plantilla original | MIT | Ninguna conocida; proyecto sin mantenimiento | Sí |
| Themify Icons | 2014 | Plantilla original | SIL OFL / MIT (validar) | No aplica (CSS y fuentes) | Sí |
| PHP | ≥ 8.1 (probado en 8.3.6) | Servidor | PHP License | Mantener en una rama con soporte | Sí |
| MySQL | 8.x | Servidor | GPL | Mantener parchado | Sí |

\* Revalidar contra NVD / GitHub Advisory Database en cada release (ASVS v5.0.0-15.1.1).
**Plazos de remediación propuestos:** Crítica 7 días · Alta 30 días · Media 90 días · Baja en el siguiente release.

## 4. Excepciones (skill §12, Gate 5)

> **Actualización (19-10-2026):** EXC-01 y EXC-03 fueron **resueltas** (implementadas y demostradas en
> laboratorio). EXC-02 queda **mitigada**. Ya no son excepciones abiertas; se conservan como trazabilidad.

| ID | Control | Estado | Evidencia / solución |
|---|---|---|---|
| EXC-01 | TLS + HSTS + cookie Secure | **RESUELTO** (código) · pendiente el certificado en producción | HTTPS demostrado en laboratorio con cookie `__Host-PNKSESSID; Secure` y HSTS (`exc01_https`). `es_https()` soporta `APP_FORCE_HTTPS` y `X-Forwarded-Proto` con `TRUST_PROXY`. Se incluye `config/apache-vhost-ssl.conf` y `herramientas/generar_cert_dev.sh`. En producción falta sólo emitir el certificado (certbot). |
| EXC-02 | Gestor de secretos (13.3.1) | **MITIGADO** | Secretos fuera del código y del webroot (env o `config.local.php`), en `.gitignore`, con `.htaccess` deny y permisos 600 recomendados (`chmod 600 config/config.local.php`). Un vault es una mejora opcional para producción a gran escala. |
| EXC-03 | MFA (6.3.3) | **RESUELTO** | Segundo factor TOTP implementado (`totp_*` en `setup.php`), opcional por usuario, verificado contra los vectores de la RFC 6238 y con 3 pruebas automatizadas (T25-T27). Activación: `herramientas/activar_mfa.php`. |

## 5. Acciones pendientes

| ID | Acción | Responsable | Prioridad | Evidencia esperada |
|---|---|---|---|---|
| ACC-01 | Emitir el certificado TLS en producción (certbot) y redirección HTTPS | Infra | Alta | **Config lista** (`apache-vhost-ssl.conf`); el código ya emite HSTS/Secure (demostrado). Falta sólo el certificado del dominio real |
| ACC-02 | DAST en QA | **EJECUTADO en laboratorio** (`tests/dast_scanner.php`): v1 = 2 críticas/1 alta/3 medias/2 bajas; v2 = 0/0/0/1 (TRACE, del server de desarrollo). En QA repetir con OWASP ZAP sobre el ambiente desplegado | QA/AppSec | Media | `dast_v1.txt` / `dast_v2.txt` |
| ACC-03 | Centralizar logs, alertas por `login_bloqueado` / `csrf_invalido` | Infra | Media | Captura del SIEM |
| ACC-04 | Ejecutar `03_migracion` + `migrar_passwords.php`, forzar cambio de contraseñas | DBA | Alta | Columna `password` sólo con `$2y$` |
| ACC-05 | ~~Pipeline CI con SAST y secret scanning~~ **HECHO**: `.github/workflows/seguridad.yml` (lint + SAST + secret scan + 27 pruebas). Falta sólo conectar el repo Git con protección de rama | Dev | Media | `.github/workflows/seguridad.yml` |
| ACC-06 | Alojar las fuentes localmente y quitar Google de la CSP | Dev | Media | Sin peticiones a `fonts.googleapis.com` |
| ACC-07 | Migrar a Bootstrap 5.x | Dev | Baja | SBOM actualizado |
| ACC-08 | Cambio de contraseña (6.2.2/6.2.3), chequeo top-3000/breached (6.2.4/6.2.12) y MFA | Dev | Media | Pruebas nuevas |
| ACC-09 | Autoservicio: exportar mis datos, editar nombre, dar de baja la cuenta | Dev | Media | Pruebas nuevas; Privacy Gate P2 |
| ACC-10 | Rate limit por IP en `carrito.php` y comentarios | Dev | Baja | Prueba de límite |

## 6. RACI de seguridad

| Actividad | Dev | QA | AppSec | Infra/DBA | Responsable de datos / Propietario |
|---|---|---|---|---|---|
| Corrección de código | **R** | C | A | I | I |
| Pruebas de seguridad | C | **R** | A | I | I |
| Security Gate | C | C | **R** | C | **A** |
| TLS, secretos, logs | I | I | C | **R/A** | I |
| Migración de la BD | C | I | C | **R** | A |
| Aviso de privacidad, base de licitud, derechos | C | I | C | I | **R/A** |
| Gestión de incidentes con datos personales | C | I | R | R | **A** |

R = Responsable · A = Aprueba · C = Consultado · I = Informado
