# Matriz de Cumplimiento — PNK Security v2

Estados permitidos: `CUMPLE` · `CUMPLE PARCIAL` · `NO CUMPLE` · `NO APLICA` · `NO VERIFICADO`.
"CUMPLE" sólo se marca cuando existe **evidencia verificable** en este paquete (prueba, código o archivo).
Las referencias legales siguen la regla temporal: Ley 19.628 vigente y Ley 21.719 como *readiness*
(ver `06_Privacidad`).

## 1. Matriz de requisitos (formato skill §24)

| ID | Requisito | Ley 19.628 / 21.719 | ISO 27001 | ISO 27034 / ASC | ASVS v5.0.0 | Evidencia | Estado | Brecha | Acción | Responsable | Prioridad |
|---|---|---|---|---|---|---|---|---|---|---|---|
| R01 | Consultas parametrizadas | 19.628 art. 11 / 21.719 art. 14 quinquies | A.8.28 | ASC-10, ASC-14 | 1.2.4 | T01, T09, T18; `sast_corregido.txt` | CUMPLE | — | — | Dev | — |
| R02 | Codificación de salida contextual | ídem | A.8.28 | ASC-10 | 1.2.1, 1.2.2, 3.2.2 | T11, T13, T20; captura | CUMPLE | — | — | Dev | — |
| R03 | Validación de entradas en el servidor (lista blanca) | — | A.8.28 | ASC-10 | 2.2.1, 2.2.2 | `entero_positivo()`, `texto_param()`; T09, T18 | CUMPLE | — | — | Dev | — |
| R04 | Contraseñas con función de hash computacionalmente costosa | 19.628 art. 11 | A.5.17, A.8.24 | ASC-12 | 11.4.2 | bcrypt coste 12; T02 | CUMPLE | — | — | Dev | — |
| R05 | Sin cuentas por defecto | — | A.5.17 | ASC-07 | 6.3.2 | Seed v2 sin usuarios; migración deshabilita `admin@gmail.com` | CUMPLE PARCIAL | En BD v1 existente depende de ejecutar la migración | Ejecutar `03_migracion` | DBA | Alta |
| R06 | Anti fuerza bruta | — | A.8.5 | ASC-07 | 6.3.1, 6.1.1 | T07; documentado en Informe PNK-09 | CUMPLE | — | — | Dev | — |
| R07 | Mensajes de autenticación genéricos | — | A.8.5 | ASC-07 | 6.3.8 (L3) | T08 | CUMPLE | — | — | Dev | — |
| R08 | MFA para acceso | — | A.8.5 | ASC-07 | 6.3.3 (L2) | T25-T27; `totp_*` en setup.php | CUMPLE | Opcional por usuario | Activar en cuentas sensibles con `activar_mfa.php` | Dev | — |
| R09 | Política de contraseñas (largo ≥ 8, sin reglas de composición, ≥ 64 permitidos) | — | A.5.17 | ASC-07 | 6.2.1, 6.2.5, 6.2.9 | `crear_usuario.php` (8-128) | CUMPLE PARCIAL | No hay cambio de contraseña (6.2.2/6.2.3) ni chequeo contra top-3000 (6.2.4: sólo una lista corta) | ACC-08 | Dev | Media |
| R10 | Regenerar sesión al autenticar | — | A.8.5 | ASC-09 | 7.2.4 | T02 | CUMPLE | — | — | Dev | — |
| R11 | Expiración por inactividad y absoluta | — | A.8.5 | ASC-09 | 7.3.1, 7.3.2, 7.1.1 | `SESION_INACTIVIDAD`=30 min, `SESION_MAXIMA`=8 h | CUMPLE PARCIAL | Implementado pero sin prueba automatizada del vencimiento | Agregar prueba con reloj simulado | QA | Baja |
| R12 | Logout invalida la sesión | — | A.8.5 | ASC-09 | 7.4.1 | T05 | CUMPLE | — | — | Dev | — |
| R13 | Cookie de sesión HttpOnly / SameSite | — | A.8.5 | ASC-09 | 3.3.2, 3.3.4 | T03 | CUMPLE | — | — | Dev | — |
| R14 | Cookie Secure + prefijo `__Host-` | — | A.8.24 | ASC-09 | 3.3.1, 3.3.3 | `exc01_https` (demostrado con TLS) | CUMPLE | En producción, con certificado | ACC-01 (certificado) | Infra | Media |
| R15 | Protección CSRF y métodos seguros | — | A.8.28 | ASC-09, ASC-29 | 3.5.1, 3.5.3 | T04, T05, T17 | CUMPLE | — | — | Dev | — |
| R16 | Autorización en el servidor / anti-IDOR | 21.719 art. 14 quinquies | A.5.15, A.8.3 | ASC-08 | 8.2.1, 8.2.2, 8.3.1 | T12, T15 | CUMPLE | — | — | Dev | — |
| R17 | Documentación de reglas de autorización | — | A.5.15 | ASC-08 | 8.1.1 | `03_ANF §4` | CUMPLE | — | — | AppSec | — |
| R18 | Autor tomado de la sesión (no del cliente) | — | A.8.3 | ASC-08 | 8.2.3, 15.3.3 | T14 | CUMPLE | — | — | Dev | — |
| R19 | Cabeceras de seguridad (CSP, nosniff, Referrer, COOP, frame-ancestors) | — | A.8.20 | ASC-06 | 3.4.3-3.4.6, 3.4.8 | T21 | CUMPLE | `style-src 'unsafe-inline'` | Mover estilos inline a CSS | Dev | Baja |
| R20 | TLS y HSTS | — | A.8.20, A.8.24 | ASC-06, ASC-12 | 12.1.1, 12.2.1, 12.2.2, 3.4.1 | `exc01_https`: HSTS emitido bajo TLS | CUMPLE PARCIAL | Demostrado en lab; falta certificado de producción | ACC-01 | Infra | Media |
| R21 | Secretos fuera del código y del webroot | — | A.8.24 | ASC-13 | 13.3.1 (L2) | `config.example.php`, env vars, `.gitignore`, T24 | CUMPLE | Mitigado; vault opcional en producción | EXC-02 | Infra | Baja |
| R22 | BD con mínimo privilegio, sin credencial por defecto | — | A.8.2 | ASC-06, ASC-13 | 13.2.2, 13.2.3 | `02_usuario_app.sql`, T24 | CUMPLE PARCIAL | Script entregado, ejecución en servidor no verificada | Ejecutar y evidenciar `SHOW GRANTS` | DBA | Alta |
| R23 | Sin metadatos de control de versiones / listados / archivos extra | — | A.8.9 | ASC-06, ASC-26 | 13.4.1, 13.4.3, 13.4.7, 15.2.3 | `.htaccess`, limpieza de archivos | CUMPLE PARCIAL | `.htaccess` no verificado en Apache | Checklist del Gate 4 | QA | Media |
| R24 | Modo debug desactivado en PROD | — | A.8.9 | ASC-28 | 13.4.2 | `APP_ENV=prod` por defecto; T23 | CUMPLE | — | — | Dev | — |
| R25 | Errores genéricos y manejador global | — | A.8.28 | ASC-28 | 16.5.1, 16.5.4 (L3) | T23 | CUMPLE | — | — | Dev | — |
| R26 | Logging de eventos de seguridad con metadatos y UTC | 21.719 art. 14 sexies (soporte) | A.8.15 | ASC-27 | 16.2.1, 16.2.2, 16.3.1-16.3.3 | T22; `log_seguridad_ejemplo.jsonl` | CUMPLE | — | — | Dev | — |
| R27 | Logs sin datos sensibles y protegidos contra inyección | 19.628 art. 11 | A.8.15 | ASC-27 | 16.2.5, 16.4.1 | T22 (sin clave ni correo en claro); `json_encode` | CUMPLE | — | — | Dev | — |
| R28 | Logs protegidos y enviados a un sistema separado | — | A.8.15, A.8.16 | ASC-31 | 16.4.2, 16.4.3 | `logs/.htaccess` (deny) | CUMPLE PARCIAL | Sin SIEM ni centralización | ACC-03 | Infra | Media |
| R29 | Inventario de componentes (SBOM) y plazos de remediación | — | A.8.8, A.5.21 | ASC-15 | 15.1.1, 15.1.2 | `sbom.cdx.json`, `08_Plan §3` | CUMPLE | — | — | Dev | — |
| R30 | Componentes sin vulnerabilidades conocidas fuera de plazo | — | A.8.8 | ASC-15, ASC-22 | 15.2.1 | jQuery 3.7.1, Bootstrap 4.6.2 | CUMPLE PARCIAL | Bootstrap 4 sin soporte del fabricante | ACC-07 (migrar a 5.x) | Dev | Baja |
| R31 | Anti-automatización en funciones (carrito) | — | A.8.28 | ASC-14 | 2.4.1 | Límite de 50 items | CUMPLE PARCIAL | Sin rate limit por IP en carrito/comentarios | ACC-10 | Dev | Baja |
| R32 | Content-Type correcto en respuestas | — | A.8.28 | ASC-29 | 4.1.1 | `carrito.php` JSON, `cabeceras_seguridad()` | CUMPLE | — | — | Dev | — |
| R33 | Anti-caché en respuestas sensibles | — | A.8.12 | ASC-11 | 14.3.2 | `Cache-Control: no-store` (T21) | CUMPLE | — | — | Dev | — |
| R34 | Clasificación de datos sensibles y requisitos de protección | 19.628 / 21.719 art. 3 | A.5.12*, A.5.34 | ASC-01, ASC-11 | 14.1.1, 14.1.2 | `06_Privacidad §1`, `03_ANF §2` | CUMPLE | — | — | AppSec | — |
| R35 | Aviso de privacidad / información al titular | 19.628 art. 4 / 21.719 art. 3 | A.5.34 | ASC-03 | — | `privacidad.php` (plantilla) | CUMPLE PARCIAL | Faltan datos del responsable y la base de licitud | Completar y validar | Responsable | Alta |
| R36 | Soporte técnico al ejercicio de derechos | 19.628 art. 12 / 21.719 | A.5.34 | ASC-11 | 8.2.2 | T16 (supresión de comentario) | CUMPLE PARCIAL | Acceso, portabilidad, rectificación y baja de cuenta manuales | ACC-09 | Dev | Media |
| R37 | Retención y eliminación | 19.628 art. 6 / 21.719 art. 14 quáter | A.8.10 | ASC-11 | 14.2.7 (L3) | `purgar_retencion.php` | CUMPLE PARCIAL | Sin rotación de `security.log` | logrotate a 180 días | Infra | Baja |
| R38 | Transferencia internacional (Google Fonts) | 21.719 art. 27 y ss. | A.5.23 | ASC-30 | 14.2.3 | `06_Privacidad PRIV-04` | NO CUMPLE | IP enviada a un tercero sin necesidad | ACC-06 (fuentes locales) | Dev | Media |
| R39 | Datos de prueba sintéticos | 21.719 art. 14 quáter | A.8.33 | ASC-24 | — | `tests/fixture_sqlite.sql` | CUMPLE | — | — | QA | — |
| R40 | Separación DEV/QA/PROD | — | A.8.31 | ASC-23 | 13.4.2 | `APP_ENV`, config por ambiente | NO VERIFICADO | No existen ambientes formales | Definir ambientes | Infra | Media |
| R41 | Control de cambios y revisión de código (PR) | — | A.8.32, A.8.4 | ASC-16, ASC-17, ASC-25 | — | — | NO VERIFICADO | El proyecto no se entregó como repositorio Git | ACC-05 | Dev | Media |
| R42 | SAST/SCA/secret scanning en CI | — | A.8.29, A.8.8 | ASC-18 | — | `.github/workflows/seguridad.yml` | CUMPLE | Falta conectar el repo Git | ACC-05 (repo) | Dev | Baja |
| R43 | DAST / pentest | — | A.8.29, A.8.34 | ASC-19, ASC-21 | `dast_v1/v2.txt`, pentest §0 | CUMPLE PARCIAL | DAST y pentest ejecutados en lab; falta ZAP en QA desplegado | ACC-02 | QA/AppSec | Media |
| R44 | Carga de archivos | — | — | — | V5 | — | NO APLICA | La aplicación no permite subir archivos. Las imágenes se colocan por el servidor | — | — | — |
| R45 | OAuth/OIDC, tokens autocontenidos, WebRTC | — | — | — | V9, V10, V17 | — | NO APLICA | No se usan JWT, OAuth/OIDC ni WebRTC | — | — | — |
| R46 | Segundo factor TOTP (implementación) | — | A.8.5 | ASC-07 | 6.3.3 | T25-T27, RFC 6238 | CUMPLE | — | — | Dev | — |

\* A.5.12 = Clasificación de la información (Anexo A, ISO/IEC 27001:2022).

## 2. Resumen por estado

| Estado | Cantidad |
|---|---|
| CUMPLE | 28 |
| CUMPLE PARCIAL | 13 |
| NO CUMPLE | 1 |
| NO VERIFICADO | 2 |
| NO APLICA | 2 |

> **Esto no es una certificación ni una declaración de cumplimiento global** de ISO/IEC 27001, 27034 o ASVS.
> Es una verificación de la muestra de requisitos seleccionada para esta aplicación, con la evidencia indicada.

## 3. Capítulos ASVS v5.0.0 aplicables

| Capítulo | Aplica | Justificación |
|---|---|---|
| V1 Encoding and Sanitization | Sí | SQL y HTML dinámico |
| V2 Validation and Business Logic | Sí | Parámetros, carrito |
| V3 Web Frontend Security | Sí | Cookies, cabeceras, CSRF |
| V4 API and Web Service | Parcial | Endpoint AJAX `carrito.php` |
| V5 File Handling | No | Sin carga de archivos |
| V6 Authentication | Sí | Login por contraseña |
| V7 Session Management | Sí | Sesiones PHP |
| V8 Authorization | Sí | Comentarios con propiedad |
| V9 Self-contained Tokens | No | Sin JWT |
| V10 OAuth and OIDC | No | Sin OAuth |
| V11 Cryptography | Parcial | Hash de contraseñas, CSPRNG |
| V12 Secure Communication | Sí | TLS (pendiente de infraestructura) |
| V13 Configuration | Sí | Secretos, debug, `.htaccess` |
| V14 Data Protection | Sí | Datos personales |
| V15 Secure Coding and Architecture | Sí | Dependencias, tipos |
| V16 Security Logging and Error Handling | Sí | Logs, errores |
| V17 WebRTC | No | Sin WebRTC |
