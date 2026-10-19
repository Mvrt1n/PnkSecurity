# Checklists — PNK Security

Marcado según la verificación hecha sobre la v2 (19-10-2026). `[x]` = verificado con evidencia · `[ ]` = pendiente · `N/A` = no aplica (justificado).

## A. Checklist SSDLC (skill §11)

| Etapa | Ítem | Estado | Evidencia |
|---|---|---|---|
| Clasificación | Aplicación clasificada (Perfil B) | [x] | `03_ANF §5` |
| Clasificación | Datos clasificados y privacidad identificada | [x] | `03_ANF §3`, `06_Privacidad §1` |
| Requisitos | Requisitos de seguridad y privacidad definidos | [x] | `09_Revision_Requisitos`, `02_Matriz` |
| Requisitos | Base de licitud, finalidad y minimización | [ ] | Base de licitud por validar (aviso con campos entre corchetes) |
| Riesgo | Análisis de riesgo / necesidad de EIPD | [x] | `06_Privacidad §4` (EIPD no requerida a la fecha) |
| Diseño | Threat model | [x] | `05_Threat_Model` |
| Diseño | Arquitectura segura (fronteras, secretos, mínimo privilegio) | [x] | `05_Threat_Model §1`, `02_usuario_app.sql` |
| Diseño | Selección de ASC y nivel ASVS | [x] | `04_Catalogo_ASC`, L2 |
| Desarrollo | Code review | [x] | `01_Informe` |
| Desarrollo | SAST | [x] | `tests/evidencia/sast_*.txt` (por patrones) |
| Desarrollo | SCA | [x] | `sbom.cdx.json`, `08_Plan §3` |
| Desarrollo | Secret scanning | [x] | Reglas `SECRET-*` sin coincidencias |
| QA | Pruebas funcionales de seguridad | [x] | 24/24 PASA |
| QA | DAST | [ ] | ACC-02 (requiere ambiente QA autorizado) |
| QA | API security testing | [x] | T17, T18 (`carrito.php`) |
| QA | Pentesting según riesgo | [ ] | Plantilla en `13_Pentest_Laboratorio.md` |
| Gate | Security Gate | [x] | `07_Security_Gates` |
| Producción | TLS, monitoreo, logs centralizados | [ ] | EXC-01, ACC-03 |
| Producción | Gestión de vulnerabilidades continua | [ ] | Plazos definidos en `08_Plan §3`, falta proceso operativo |

## B. Checklist de programación segura (PHP)

| # | Regla | Estado | Dónde |
|---|---|---|---|
| 1 | Toda consulta SQL usa PDO con parámetros (`consulta($sql, $params)`), nunca concatenación | [x] | Todos los PHP |
| 2 | `PDO::ATTR_EMULATE_PREPARES = false` y `ERRMODE_EXCEPTION` | [x] | `setup.php::conectar()` |
| 3 | IDs validados con `entero_positivo()`; textos con `texto_param()` (largo y UTF-8) | [x] | index, carrito, grcomentarios, mostrar_carrito |
| 4 | Toda salida dinámica pasa por `e()`; en URLs sólo enteros validados | [x] | index, mostrar_carrito, privacidad |
| 5 | En JS, `.text()` / `textContent`, nunca `.html()` con datos | [x] | `controladorajax.js` |
| 6 | Sin JS inline (compatible con CSP `script-src 'self'`) | [x] | Todas las vistas |
| 7 | Acciones que cambian estado: POST + `exigir_post_csrf()` | [x] | login, logout, comentarios, carrito |
| 8 | Autorización verificada en el servidor (no sólo ocultar botones) | [x] | `grcomentarios.php` |
| 9 | Identidad del autor desde `$_SESSION`, nunca desde el formulario | [x] | `grcomentarios.php` |
| 10 | Contraseñas: `password_hash` (bcrypt 12) / `password_verify`; nunca en SQL ni logs | [x] | `procesalogin.php` |
| 11 | `session_regenerate_id(true)` al autenticar; cookie HttpOnly/SameSite/Secure | [x] | `setup.php`, `procesalogin.php` |
| 12 | Secretos sólo en env o `config/config.local.php` (fuera del webroot) | [x] | `config()` |
| 13 | `declare(strict_types=1)` y comparaciones estrictas (`===`) | [x] | Todos los PHP |
| 14 | Errores: mensaje genérico al usuario, detalle al log; `display_errors=0` en prod | [x] | `set_exception_handler` |
| 15 | Eventos de seguridad registrados con `log_seguridad()`, sin datos sensibles | [x] | `setup.php` |
| 16 | Rutas de archivo construidas sólo con nombres validados (`archivo_seguro()`) | [x] | Imágenes en index y carrito |
| 17 | Librerías actualizadas y verificadas por hash; nada sin uso en el webroot | [x] | `vendors/`, SBOM |
| 18 | Herramientas administrativas sólo por CLI (`PHP_SAPI === 'cli'`) | [x] | `herramientas/` |
| 19 | Datos de prueba sintéticos | [x] | `tests/fixture_sqlite.sql` |
| 20 | Carga de archivos validada | N/A | La aplicación no tiene carga de archivos |

## C. Controles de proveedor / desarrollo de terceros (skill §28)

**NO APLICA a la fecha.** El desarrollo es propio (laboratorio académico) y no hay código desarrollado por
terceros. Los componentes de terceros se gestionan como dependencias (ASC-15, SBOM). Los servicios de terceros
(hosting, Google Fonts) se tratan en privacidad (`06_Privacidad §5`, PRIV-04). Si se subcontrata desarrollo, deben
aplicarse los 12 puntos de la sección 28 del skill (ISO A.5.19-A.5.22, A.8.30; ASC-30) y exigir ASVS L2 por
contrato.
