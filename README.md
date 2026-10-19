# PNK Security — Versión corregida (Desarrollo Seguro)

Rama `mejoras`: versión endurecida de la aplicación tras la auditoría de seguridad.
Se corrigieron **17 vulnerabilidades** (2 críticas, 5 altas, 7 medias, 3 bajas),
verificadas con **27 pruebas automatizadas** y un escaneo dinámico (DAST).

## Estructura
- `pnkSecurity/` — aplicación corregida (PHP/PDO, MFA, cabeceras, CSRF, logging).
- `Script_BD/` — esquema e scripts de migración de la base de datos.
- `config/` — ejemplo de configuración y vhost Apache con TLS.
- `herramientas/` — utilidades CLI (crear usuario, MFA, migración, retención).
- `tests/` — pruebas de seguridad y escáner DAST (`php tests/pruebas_seguridad.php`).
- `docs/` — informe de auditoría, matrices ISO/ASVS, threat model, privacidad, etc.
- `.github/workflows/` — pipeline CI con SAST y pruebas.
- `Informe_Tecnico_Desarrollo_Seguro.docx` — informe en formato de entrega.

## Marcos aplicados
OWASP ASVS v5.0.0 · OWASP Top 10:2025 · ISO/IEC 27001:2022 · ISO/IEC 27034 · Ley 19.628 / 21.719.

## Verificar
```bash
php tests/pruebas_seguridad.php     # 27/27 pruebas de seguridad
php tests/dast_scanner.php http://127.0.0.1:8000
```
