# Security Gates — PNK Security v2

## Gate 1 — Requisitos: **APROBADO**
Existen la clasificación (Perfil B), los datos tratados (RAT), la criticidad, los requisitos de seguridad y
privacidad (R01-R45), las dependencias (SBOM), las integraciones (Google Fonts) y el ASVS objetivo (L2).

## Gate 2 — Arquitectura: **APROBADO CON OBSERVACIONES**
Hay threat model, flujos y fronteras de confianza, autenticación, autorización, logging, protección de secretos
y BD de mínimo privilegio. **Observación:** falta definir TLS, el gestor de secretos y la centralización de logs
(infraestructura).

## Gate 3 — Desarrollo: **APROBADO**
Revisión de código hecha, SAST por patrones con 51 → 1 coincidencias, SCA hecho, sin credenciales en el código
(T24), pruebas unitarias e integración de seguridad en 24/24.
Pendiente que no bloquea: repositorio Git con PR y revisor (ACC-05).

## Gate 4 — QA: **APROBADO CON EXCEPCIÓN (sólo laboratorio)**
Autenticación, autorización, sesiones, API, validación, errores y logging verificados en local.
**No ejecutado:** DAST y pruebas sobre Apache/MySQL reales. Checklist a completar en QA:

- [ ] `GET /config/config.local.php`, `/logs/security.log`, `/Script_BD/pnk_security.sql` → 403/404
- [ ] `GET /vendors/` → sin listado de directorio
- [ ] `SHOW GRANTS FOR 'pnk_app'@'localhost'` coincide con `02_usuario_app.sql`
- [ ] `php tests/pruebas_seguridad.php` → 24/24 en el servidor de QA
- [ ] OWASP ZAP (baseline + autenticado) con autorización escrita: sin hallazgos Altos

## Gate 5 — Producción

```markdown
# Security Gate

## Aplicación
PNK Security — carta digital

## Versión
v2 (paquete corregido, 19-10-2026)

## Ambiente
PROD (Internet pública)

## Resultado
- Laboratorio / LAN sin exposición a Internet: APROBADO CON EXCEPCIÓN (EXC-01…EXC-03 aprobadas y con fecha de expiración).
- PROD en Internet: RECHAZADO hasta cerrar ACC-01 (TLS + HSTS + cookie Secure). Sin TLS la excepción EXC-01 no es aceptable.

## Controles verificados
R01, R02, R03, R04, R06, R07, R10, R12, R13, R15, R16, R17, R18, R19, R24, R25, R26, R27, R29, R32, R33, R34, R39 (estado CUMPLE en 02_Matriz)

## Hallazgos críticos
Ninguno abierto (PNK-01, PNK-02 corregidos y verificados)

## Hallazgos altos
Ninguno abierto en el código. Riesgo alto de infraestructura: TM-14 (sin TLS), hasta cerrar EXC-01

## Riesgo residual
Bajo-Medio (ver 08_Plan §2)

## Excepciones
EXC-01 TLS/HSTS · EXC-02 Gestor de secretos · EXC-03 MFA

## Evidencias
tests/resultados_pruebas.txt · tests/evidencia/* · docs/01-08 · sbom.cdx.json

## Aprobadores
[Propietario de la aplicación] · [Responsable de seguridad] · [Docente / evaluador]
```

### Privacy Gates
P1 y P2: CUMPLE PARCIAL · P3: CUMPLE · P4: NO VERIFICADO. Detalle en `06_Privacidad §7`.
