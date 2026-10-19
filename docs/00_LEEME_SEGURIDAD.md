# PNK Security v2 — Versión corregida (SSDLC)

Proyecto corregido según el skill **SSDLC ISO/IEC 27001:2022 + ISO/IEC 27034 + OWASP ASVS v5.0.0 + Ley 19.628 / 21.719**.

## Novedades de esta versión (pendientes resueltos)

- **MFA (segundo factor TOTP):** opcional por usuario. Actívalo con `php herramientas/activar_mfa.php correo@ejemplo.cl` y escanea la URI con Google Authenticator/Aegis. Pruebas T25-T27.
- **HTTPS listo:** el código emite cookie `__Host-...; Secure` y HSTS bajo TLS. Para producción: `herramientas/generar_cert_dev.sh` (pruebas) o certbot, y `config/apache-vhost-ssl.conf`.
- **DAST:** `php tests/dast_scanner.php http://127.0.0.1:8082` corre un escaneo dinámico (SQLi, XSS, cabeceras, CSRF, etc.). v2: 0 críticos/altos.
- **CI:** `.github/workflows/seguridad.yml` corre lint + SAST + escaneo de secretos + 27 pruebas en cada push.
- **Secretos:** fuera del código; recomendado `chmod 600 config/config.local.php`.

## Informe consolidado

`Informe_PNK_Security.pdf` (raíz del paquete) — informe final para entregar/imprimir, con el resumen, los hallazgos y la **evidencia del pentest antes/después** (capturas en `docs/evidencia_pentest/`).

## Documentos

| # | Documento | Contenido (sección del skill) |
|---|---|---|
| 01 | `01_Informe_Auditoria.md` | Resumen ejecutivo, tecnologías, superficie de ataque, 17 hallazgos con código vulnerable y corregido, mapeos y pruebas (§23, §33, §46.6) |
| 02 | `02_Matriz_Cumplimiento.md` | Matriz Ley / ISO / ASC / ASVS con estados y evidencia (§21, §24) |
| 03 | `03_ANF_PNK.md` | ONF de referencia, ANF, clasificación, reglas de autorización, ASMP, nivel ASVS (§6, §9) |
| 04 | `04_Catalogo_ASC.md` | ASC seleccionados con la plantilla completa (§6.4, §7) |
| 05 | `05_Threat_Model.md` | DFD, fronteras de confianza, STRIDE (§31) |
| 06 | `06_Privacidad_Ley19628_21719.md` | RAT, PRIV-01 a 05, privacy by design, EIPD, incidentes, Privacy Gates (§3.x) |
| 07 | `07_Security_Gates.md` | Gates 1-5 (§12, §26) |
| 08 | `08_Plan_Remediacion.md` | Estado, riesgo residual, SCA, excepciones, acciones, RACI (§12, §28-§29) |
| 09 | `09_Revision_Requisitos.md` | Revisión de requisitos con el formato del skill (§25) |
| 10 | `10_Checklists.md` | Checklist SSDLC, checklist de programación segura, controles de proveedor (§11, §28) |
| 11 | `11_Plan_Pruebas_Seguridad.md` | Plan de pruebas de seguridad (§20, §32) |
| 12 | `12_Matriz_Evidencias.md` | Matriz de evidencias y trazabilidad (§36, §37) |
| 13 | `13_Pentest_Laboratorio.md` | Alcance, casos y formato de informe de pentest en localhost (§46) |
| — | `sbom.cdx.json` | SBOM CycloneDX 1.5 con hashes SHA-256 |

## Estructura

```text
pnkSecurity/
├── pnkSecurity/          ← WEBROOT (lo único que debe servir Apache)
│   ├── index.php, mostrar_carrito.php, carrito.php, grcomentarios.php, privacidad.php
│   ├── setup/            setup.php (núcleo de seguridad), procesalogin.php, cerrar_sesion.php
│   ├── js/ css/ img/ imagenes/ vendors/
│   └── .htaccess
├── config/               config.example.php → copiar a config.local.php (no versionar)
├── logs/                 security.log (JSON Lines, UTC)
├── Script_BD/            pnk_security.sql (instalación) · 02_usuario_app.sql · 03_migracion_v1_a_v2.sql
├── herramientas/         crear_usuario.php · migrar_passwords.php · purgar_retencion.php (sólo CLI)
├── tests/                pruebas_seguridad.php · sast_basico.php · fixture_sqlite.sql · evidencia/
└── docs/
```

## Instalación (XAMPP / Apache + MySQL 8, PHP ≥ 8.1)

1. **Ideal:** apuntar el `DocumentRoot` a `pnkSecurity/pnkSecurity`. Si se copia la carpeta completa a `htdocs`,
   los `.htaccess` deniegan el acceso a `config/`, `logs/`, `Script_BD/`, `herramientas/`, `tests/` y `docs/`.
   Verifíquelo con el checklist del Gate 4.
2. Como administrador de MySQL: importar `Script_BD/pnk_security.sql`, editar la contraseña en
   `Script_BD/02_usuario_app.sql` y ejecutarlo.
3. Copiar `config/config.example.php` a `config/config.local.php` y completar `DB_USER=pnk_app`, `DB_PASS` y
   `LOG_PEPPER` (`php -r "echo bin2hex(random_bytes(32));"`). En producción: `APP_ENV=prod` y `APP_FORCE_HTTPS=true`.
4. Crear un usuario (con credenciales de administrador de BD, porque `pnk_app` no tiene INSERT en `usuarios`):
   `DB_USER=admin_bd DB_PASS=... php herramientas/crear_usuario.php "Ana" ana@ejemplo.cl`
5. Programar a diario `php herramientas/purgar_retencion.php`.
6. **Si ya tiene la BD v1:** ejecutar `03_migracion_v1_a_v2.sql` y luego `php herramientas/migrar_passwords.php`
   (también con credenciales de administrador).

> La v2 **no arranca** con `root` ni con una contraseña vacía. Es intencional (PNK-04).

## Pruebas (evidencia)

```bash
php tests/pruebas_seguridad.php           # 24 pruebas; servidor local 127.0.0.1 + SQLite sintética
php tests/sast_basico.php pnkSecurity     # SAST por patrones
```

Resultado actual: **24/24 PASA** (`tests/resultados_pruebas.txt`).

## Cambios funcionales visibles

- El login pide el **correo** (antes era un campo "Usuario" que igual comparaba contra el email).
- "Cerrar sesión" es un botón (POST). "Eliminar todo" en el carrito ya no cierra la sesión.
- Cada usuario puede eliminar sus propios comentarios. Se agregó un aviso de privacidad.
- Un `id` inválido muestra "Restaurante no encontrado" (404) en vez de una página con errores.
- Se eliminaron las librerías y scripts de la plantilla que no se usaban. Los íconos SVG inline se reemplazaron
  por los equivalentes de Themify.
