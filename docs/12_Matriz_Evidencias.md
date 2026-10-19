# Matriz de Evidencias — PNK Security v2

Trazabilidad (skill §37): **Riesgo → Requisito → Control ISO → ASC → ASVS → Implementación → Prueba → Evidencia → Resultado.**

| Riesgo / hallazgo | Requisito | ISO 27001 | ASC | ASVS v5.0.0 | Implementación | Prueba | Evidencia (archivo) | Resultado |
|---|---|---|---|---|---|---|---|---|
| PNK-01 SQLi login | R01 | A.8.28 | ASC-10 | 1.2.4 | `procesalogin.php` (PDO) | T01 | `tests/resultados_pruebas.txt` | PASA |
| PNK-02 SQLi parámetros | R01, R03 | A.8.28 | ASC-10 | 1.2.4, 2.2.1 | `consulta()`, `entero_positivo()` | T09, T10, T18 | ídem + `tests/evidencia/sast_corregido.txt` | PASA |
| PNK-03 Contraseñas en claro | R04, R05 | A.5.17, A.8.24 | ASC-12 | 11.4.2, 6.3.2 | bcrypt, seed sin cuentas | T02, T06 | ídem + `Script_BD/pnk_security.sql` | PASA |
| PNK-04 root en el código | R21, R22 | A.8.2, A.8.24 | ASC-13 | 13.2.2, 13.2.3, 13.3.1 | `config()`, `02_usuario_app.sql` | T24 | ídem | PASA (vault pendiente) |
| PNK-05 XSS almacenado | R02 | A.8.28 | ASC-10 | 1.2.1, 3.2.2 | `e()`, CSP | T13, T20 | ídem + `tests/evidencia/captura_app_corregida.png` | PASA |
| PNK-06 Control de acceso en comentarios | R16, R18 | A.5.15, A.8.3 | ASC-08 | 8.2.1, 8.2.2, 8.2.3 | `grcomentarios.php` | T12, T14-T16 | ídem | PASA |
| PNK-07 CSRF | R15 | A.8.28 | ASC-09 | 3.5.1, 3.5.3 | `exigir_post_csrf()` | T04, T05, T17 | ídem | PASA |
| PNK-08 Sesiones | R10-R14 | A.8.5 | ASC-09 | 7.2.4, 7.4.1, 3.3.4 | `iniciar_sesion_segura()` | T02, T03, T05 | ídem | PASA (Secure pendiente) |
| PNK-09 Fuerza bruta | R06, R07 | A.8.5 | ASC-07 | 6.3.1, 6.3.8 | `login_intentos` | T07, T08 | ídem | PASA |
| PNK-10 Cuentas inactivas | R05 | A.5.18 | ASC-07 | 7.4.2 | `estado = '1'` | T06 | ídem | PASA |
| PNK-11 Lógica del carrito | R31 | A.8.28 | ASC-14 | 2.3.1, 2.4.1 | `carrito.php` | T18, T19 | ídem | PASA |
| PNK-12 XSS reflejado | R02 | A.8.28 | ASC-10 | 1.2.1, 1.2.2 | validación + `e()` | T11 | ídem | PASA |
| PNK-13 Dependencias | R29, R30 | A.8.8 | ASC-15 | 15.1.2, 15.2.1 | jQuery 3.7.1, Bootstrap 4.6.2 | Hash vs SRI | `docs/sbom.cdx.json` | Verificado |
| PNK-14 Cabeceras / TLS | R19, R20 | A.8.20 | ASC-06 | 3.4.3-3.4.6 | `cabeceras_seguridad()` | T21 | `tests/resultados_pruebas.txt` | PASA (TLS pendiente) |
| PNK-15 Logging | R26-R28 | A.8.15 | ASC-27 | 16.2.1, 16.3.1, 16.4.1 | `log_seguridad()` | T22 | `tests/evidencia/log_seguridad_ejemplo.jsonl` | PASA |
| PNK-16 Errores | R24, R25 | A.8.28 | ASC-28 | 16.5.1, 13.4.2 | `set_exception_handler` | T23 | `tests/resultados_pruebas.txt` | PASA |
| PNK-17 Archivos sobrantes | R23 | A.8.9 | ASC-06 | 13.4.1, 13.4.3, 15.2.3 | Limpieza + `.htaccess` | Checklist del Gate 4 | `07_Security_Gates` | Pendiente en Apache |
| PRIV-02 Aviso | R35 | A.5.34 | ASC-03 | 14.1.1 | `privacidad.php` | Revisión | `06_Privacidad` | Parcial |
| PRIV-03 Derechos | R36 | A.5.34 | ASC-11 | 8.2.2 | Eliminar mi comentario | T16 | `tests/resultados_pruebas.txt` | Parcial |
| PRIV-05 Retención | R37 | A.8.10 | ASC-11 | 14.2.7 | `purgar_retencion.php` | Ejecución manual | Log `purga_retencion` | Parcial |

## Evidencias documentales (skill §36)
| Evidencia | ¿Existe? | Archivo |
|---|---|---|
| Procedimiento SSDLC / estándar de codificación | Sí (propuesto) | `03_ANF`, `10_Checklists §B` |
| Matriz de requisitos | Sí | `09_Revision_Requisitos`, `02_Matriz` |
| Threat model y diagramas | Sí | `05_Threat_Model` |
| Inventario de dependencias / SBOM | Sí | `sbom.cdx.json` |
| SAST / secret scanning | Sí (por patrones) | `tests/evidencia/` |
| Pruebas de seguridad | Sí | `tests/resultados_pruebas.txt` |
| DAST / pentest | No (sólo plantilla) | `13_Pentest_Laboratorio` |
| Repositorio, PR, protección de rama, logs de CI | No | ACC-05 |
| Excepciones aprobadas | Plantilla sin firma | `08_Plan §4` |
| Matriz de datos de prueba | Sí | `tests/fixture_sqlite.sql` (100 % sintéticos) |
