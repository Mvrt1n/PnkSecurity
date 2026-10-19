# Catálogo de ASC seleccionados — PNK Security

> Taxonomía **corporativa** (skill §7). No es numeración oficial de ISO/IEC 27034.
> Estados según skill §21.

## 1. ASC críticos (plantilla completa, skill §6.4)

### ASC-10 — Validación, codificación y sanitización de entradas/salidas
```text
ID: ASC-10
Nombre: Validación, codificación y sanitización de entradas/salidas
Objetivo: Impedir inyección SQL y XSS
Riesgo que trata: TM-01, TM-05, TM-09, TM-11 (PNK-01, 02, 05, 12)
Activo / componente: Todos los endpoints PHP; controladorajax.js
Etapa del ciclo de vida: Desarrollo / QA
Requisito: ASVS v5.0.0-1.2.1, 1.2.2, 1.2.4, 2.2.1, 2.2.2, 3.2.2 · ISO 27001 A.8.28
Actividad de seguridad: Codificación segura + revisión de código + pruebas
Implementación esperada: PDO con EMULATE_PREPARES=false; consulta($sql,$params); entero_positivo(); texto_param(); e() en toda salida; archivo_seguro() para rutas de imagen; .text() en JS
Método de verificación: Pruebas automatizadas + SAST por patrones + revisión manual
Herramienta: tests/pruebas_seguridad.php, tests/sast_basico.php
Evidencia: T01, T09, T11, T13, T18, T20; tests/evidencia/sast_*.txt
Responsable de implementación: Desarrollo
Responsable de verificación: QA / AppSec
Criterio de aceptación: 0 consultas concatenadas; payloads rechazados o escapados; pruebas en PASA
Nivel de confianza: ASVS L2
Estado: CUMPLE
Riesgo residual: Bajo
Excepción: —
Fecha de revisión: 19-10-2026
```

### ASC-07 — Identidad y autenticación
```text
ID: ASC-07
Objetivo: Autenticación robusta frente a bypass, fuerza bruta y robo de credenciales
Riesgo que trata: TM-01, TM-02, TM-10 (PNK-01, 03, 09, 10)
Componente: setup/procesalogin.php, herramientas/crear_usuario.php
Requisito: ASVS v5.0.0-6.2.1, 6.2.5, 6.2.8, 6.3.1, 6.3.2, 6.3.3, 6.3.8, 11.4.2 · ISO A.5.17, A.8.5
Implementación: bcrypt coste 12; hash ficticio; bloqueo 5/cuenta y 30/IP por 15 min; estado='1'; mensaje genérico; sin cuentas por defecto
Verificación: T01, T02, T06, T07, T08
Criterio de aceptación: pruebas en PASA y sin contraseñas en claro en la BD
Estado: CUMPLE PARCIAL (falta MFA 6.3.3 y cambio de contraseña 6.2.2/6.2.3)
Riesgo residual: Medio — EXC-03
```

### ASC-08 — Autorización y control de acceso
```text
ID: ASC-08
Objetivo: Que cada acción la realice sólo quien tiene permiso
Riesgo que trata: TM-04, TM-19 (PNK-06)
Componente: grcomentarios.php
Requisito: ASVS v5.0.0-8.1.1, 8.2.1, 8.2.2, 8.2.3, 8.3.1 · ISO A.5.15, A.8.3
Implementación: verificación de sesión en el servidor; autor desde la sesión; DELETE ... WHERE usuario_id = sesión
Verificación: T12, T14, T15, T16
Estado: CUMPLE
Riesgo residual: Bajo
```

### ASC-09 — Gestión de sesiones (incluye CSRF)
```text
ID: ASC-09
Objetivo: Sesiones resistentes a fijación, robo y CSRF
Riesgo que trata: TM-03, TM-06 (PNK-07, PNK-08)
Componente: setup/setup.php (iniciar_sesion_segura, csrf_*), cerrar_sesion.php
Requisito: ASVS v5.0.0-3.3.1-3.3.4, 3.5.1, 3.5.3, 7.2.4, 7.3.1, 7.3.2, 7.4.1 · ISO A.8.5
Implementación: strict_mode; HttpOnly; SameSite=Lax; Secure/__Host- con HTTPS; regenerate_id; 30 min / 8 h; token CSRF de 256 bits + hash_equals; POST obligatorio
Verificación: T02, T03, T04, T05, T17
Estado: CUMPLE PARCIAL (Secure/__Host- no verificado sin TLS)
Riesgo residual: Medio hasta EXC-01
```

### ASC-12 — Criptografía
```text
ID: ASC-12
Objetivo: Proteger credenciales y generar valores aleatorios seguros
Componente: password_hash/verify, random_bytes, hash_hmac
Requisito: ASVS v5.0.0-11.4.1, 11.4.2, 7.2.3, 12.2.1 · ISO A.8.24
Implementación: bcrypt coste 12; CSPRNG para el token CSRF y las posiciones del carrito; HMAC-SHA256 para seudónimos; TLS (pendiente)
Verificación: revisión de código; T02
Estado: CUMPLE PARCIAL (TLS no verificado)
```

### ASC-13 — Gestión de secretos
```text
ID: ASC-13
Objetivo: Ningún secreto en el código ni en el webroot
Riesgo que trata: TM-18 (PNK-04)
Requisito: ASVS v5.0.0-13.2.2, 13.2.3, 13.3.1, 13.3.2 · ISO A.8.24, A.5.17
Implementación: config() lee env o config/config.local.php (fuera del webroot y en .gitignore); rechazo de root y de contraseña vacía; usuario pnk_app
Verificación: T24; sast_corregido.txt sin SECRET-*
Estado: CUMPLE PARCIAL (sin vault, EXC-02)
```

## 2. Resto de ASC seleccionados

| ID | Control | Implementación / actividad | Verificación / evidencia | Estado |
|---|---|---|---|---|
| ASC-01 | Clasificación y criticidad | Perfil B, ASVS L2 | `03_ANF §5` | CUMPLE |
| ASC-02 | Requisitos de seguridad | Matriz R01-R45 | `02_Matriz` | CUMPLE |
| ASC-03 | Requisitos regulatorios y de privacidad | RAT, aviso, Privacy Gates | `06_Privacidad` | CUMPLE PARCIAL |
| ASC-04 | Evaluación de riesgos | Severidad por hallazgo | Informe §4 | CUMPLE |
| ASC-05 | Threat Modeling | STRIDE con 20 amenazas | `05_Threat_Model` | CUMPLE |
| ASC-06 | Arquitectura segura | Cabeceras, `.htaccess`, config fuera del webroot, BD de mínimo privilegio | T21; R19-R23 | CUMPLE PARCIAL |
| ASC-11 | Protección de datos | Minimización, seudonimización, retención, supresión, no-store | T16, T22; `purgar_retencion.php` | CUMPLE PARCIAL |
| ASC-14 | Codificación segura | `strict_types`, tipos estrictos (15.3.5), lógica del carrito | Revisión; T19 | CUMPLE |
| ASC-15 | Dependencias | Actualización + SBOM + integridad SRI | `sbom.cdx.json` | CUMPLE PARCIAL (Bootstrap 4 sin soporte) |
| ASC-17 | Revisión de código | Revisión manual completa | `01_Informe` | CUMPLE |
| ASC-18 | SAST | SAST por patrones antes/después | `tests/evidencia/` | CUMPLE PARCIAL (sin herramienta completa ni CI) |
| ASC-20 | Pruebas de seguridad | 24 pruebas automatizadas + navegador | `resultados_pruebas.txt` | CUMPLE |
| ASC-22 | Gestión de vulnerabilidades | Registro PNK-01 a 17 con plan y plazos | `08_Plan` | CUMPLE |
| ASC-24 | Datos de prueba | Fixture sintético `@example.com` | `tests/fixture_sqlite.sql` | CUMPLE |
| ASC-27 | Logging y trazabilidad | `log_seguridad()` JSON/UTC | T22 | CUMPLE |
| ASC-28 | Manejo seguro de errores | Manejador global, `display_errors=0` | T23 | CUMPLE |
| ASC-29 | Seguridad de APIs | `carrito.php`: POST + CSRF, JSON, validación, códigos HTTP | T17, T18 | CUMPLE |
| ASC-31 | Monitoreo postproducción | Eventos definidos; falta centralización y alertas | — | NO VERIFICADO |
| ASC-32 | Evidencias y aseguramiento | Este paquete documental y `tests/evidencia/` | — | CUMPLE |
