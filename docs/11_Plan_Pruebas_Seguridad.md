# Plan de Pruebas de Seguridad — PNK Security

## 1. Objetivo y alcance
Verificar que los controles de la v2 cumplen los criterios de aceptación de `09_Revision_Requisitos.md` y el
objetivo ASVS L2 (`03_ANF §5`).

- **En alcance:** `index.php`, `mostrar_carrito.php`, `carrito.php`, `grcomentarios.php`, `privacidad.php`,
  `setup/procesalogin.php`, `setup/cerrar_sesion.php`, `.htaccess`, scripts de BD.
- **Fuera de alcance:** servidores, dominios o bases que no sean del ambiente autorizado, infraestructura del
  hosting y Google Fonts.

## 2. Ambientes y datos
| Ambiente | Uso | Datos | Autorización |
|---|---|---|---|
| Local (php -S + SQLite) | Pruebas automatizadas en cada cambio | Sintéticos (`fixture_sqlite.sql`) | Propia del desarrollador |
| Laboratorio XAMPP (Apache + MySQL) | Checklist del Gate 4 y pentest de laboratorio | Sintéticos | Propia, sólo en `localhost` |
| QA | DAST | Sintéticos | **Autorización escrita requerida** (skill §46.1) |
| PROD | Sólo monitoreo y verificación pasiva | Reales | No hacer pruebas activas sin autorización expresa |

## 3. Casos de prueba
| Tipo | Casos | Herramienta | Frecuencia | Criterio de aprobación |
|---|---|---|---|---|
| Inyección (SQL/XSS) | T01, T09, T11, T13, T18, T20 | `tests/pruebas_seguridad.php` | Cada cambio | 100 % PASA |
| Autenticación | T01, T02, T06, T07, T08 | ídem | Cada cambio | 100 % PASA |
| Sesión / CSRF | T02-T05, T17 | ídem | Cada cambio | 100 % PASA |
| Autorización | T12, T14, T15, T16 | ídem | Cada cambio | 100 % PASA |
| Lógica de negocio | T18, T19 | ídem | Cada cambio | 100 % PASA |
| Cabeceras / config / errores | T21, T23, T24 | ídem | Cada cambio | 100 % PASA |
| Logging | T22 | ídem | Cada cambio | 100 % PASA |
| SAST | Reglas SQLI, XSS, SECRET, DEPRECATED | `tests/sast_basico.php` (+ Semgrep recomendado) | Cada cambio | 0 coincidencias nuevas |
| SCA | Revisión del SBOM contra NVD / GitHub Advisory | Manual o `npm audit` | Mensual y por release | Sin CVE altos o críticos abiertos |
| Despliegue | Checklist del Gate 4 (`.htaccess`, GRANTs) | curl / navegador / MySQL | Por despliegue | Todo en [x] |
| Vencimiento de sesión | 30 min de inactividad / 8 h absoluta | Manual (pendiente de automatizar) | Por release | Vuelve a pedir login |
| DAST | ZAP baseline + escaneo autenticado | OWASP ZAP | Por release, en QA | Sin hallazgos altos |
| Pentest de laboratorio | `13_Pentest_Laboratorio.md` | Navegador / Burp Community | Una vez por versión mayor | Todos los casos v2 = "No explotable" |

## 4. Ejecución
```bash
php tests/pruebas_seguridad.php        # genera tests/resultados_pruebas.txt
php tests/sast_basico.php pnkSecurity
```

## 5. Gestión de resultados
Una prueba que falla bloquea el Gate 3/4. El hallazgo se registra como PNK-xx con el formato de §23 y se aplican
los plazos de `08_Plan §3`. Se cierra sólo con la prueba en PASA y la evidencia archivada en `tests/evidencia/`.

## 6. Roles
Ejecuta: QA. Revisa: AppSec. Aprueba el Gate: el propietario de la aplicación (RACI en `08_Plan §6`).
