# Privacidad y Protección de Datos — PNK Security

> **Regla temporal (skill §3.1.1).** Fecha del análisis: **19-10-2026**, anterior al **01-12-2026**, fecha en que
> entran en vigencia las modificaciones principales de la Ley 21.719 (publicada el 13-12-2024).
> Por lo tanto:
> - **Hoy aplica la Ley 19.628 vigente.**
> - La **Ley 21.719 se evalúa como adecuación (readiness)**. Sus obligaciones **no se presentan como vigentes**
>   antes de esa fecha.
>
> Este documento es un mapeo técnico y operativo, **no una opinión jurídica**. La aplicabilidad y el artículo exacto
> deben validarse contra el texto legal vigente a la fecha del tratamiento (BCN / Diario Oficial), y contra los
> reglamentos e instrucciones de la autoridad.

## 1. Inventario de tratamientos (RAT)

| # | Tratamiento | Finalidad | Datos | Titulares | Base de licitud (a validar) | Retención propuesta | Destinatarios / terceros |
|---|---|---|---|---|---|---|---|
| T1 | Cuentas de usuario | Autenticación para publicar comentarios | Nombre, correo, hash de contraseña, estado | Usuarios registrados (personas naturales) | Consentimiento del titular al crear la cuenta (Ley 19.628 art. 4) — **[validar]** | Mientras la cuenta esté activa; eliminar al darla de baja | Ninguno |
| T2 | Comentarios públicos | Opiniones sobre el restaurante | Nombre visible + texto libre (puede incluir datos que el titular escriba) | Usuarios registrados | Consentimiento (publicación informada en el formulario) — **[validar]** | Hasta que el titular lo elimine o se cierre la cuenta | Público en general (se publica) |
| T3 | Registros de seguridad | Prevenir accesos no autorizados y detectar incidentes | IP, fecha/hora, correo seudonimizado (HMAC), id de usuario, evento | Visitantes y usuarios | Interés en la seguridad / deber de cuidado (art. 11) — **[validar fundamento]** | 180 días (`logs/`) | Ninguno |
| T4 | Intentos de login | Anti fuerza bruta | IP, correo seudonimizado, fecha | Visitantes | Ídem T3 | 30 días (`herramientas/purgar_retencion.php`) | Ninguno |
| T5 | Datos de restaurantes | Mostrar la carta | RUT, nombre, teléfono, correo y dirección del local | Principalmente personas jurídicas. **Si el local es de una persona natural, su RUT y datos son datos personales** | Relación contractual con el restaurante — **[validar]** | Mientras dure el servicio | Público (se publica teléfono, correo y dirección; el **RUT no se publica**) |
| T6 | Carrito | Mostrar productos seleccionados | Ids de productos, en la sesión | Visitantes | No identifica al titular (sesión anónima) | Duración de la sesión | Ninguno |
| T7 | Carga de fuentes | Tipografía | IP y user-agent del visitante enviados a Google | Visitantes | **[validar]** | Fuera del control del responsable | **Google (posible transferencia internacional)** |

**Datos sensibles:** no se tratan datos de salud, biométricos ni de menores por diseño. **Pero** el campo de
comentario es texto libre y un titular podría escribir datos sensibles. Se mitiga con el aviso en el formulario y
con la posibilidad de eliminar el comentario. **Volumen:** bajo, típico de una aplicación de laboratorio.
**Ambientes:** en `tests/` se usan sólo datos sintéticos (`@example.com`).

**Plantilla por tratamiento (skill §3.4)** — ejemplo para T1:

```text
Tratamiento: Cuentas de usuario
Finalidad: Autenticar usuarios para publicar comentarios
Categoría de datos: Identificación y contacto (nombre, correo), credencial (hash)
Titulares: Usuarios registrados
Responsable: [Razón social del operador del sitio]
Encargado/Subencargado: [Proveedor de hosting, si aplica]
Base de licitud: [Consentimiento — validar]
Fuente de la base jurídica: Ley 19.628 art. 4 (vigente); desde 01-12-2026, art. 13 del texto modificado — validar
Retención: Mientras la cuenta esté activa
Transferencias: Ninguna, salvo el hosting (validar ubicación)
Controles: ASC-07, ASC-11, ASC-12 (bcrypt), ASC-08
Evidencia: procesalogin.php, T02/T06, este RAT
```

## 2. Hallazgos de privacidad

### [PRIV-01] Contraseñas y correos expuestos por SQLi y por almacenamiento en claro (v1)

**Riesgo:** Alto · **Tratamiento:** T1 · **Datos afectados:** nombre, correo, contraseña · **Titulares:** usuarios registrados · **Sistema:** BD `pnk_security`

**Situación observada.** En v1, PNK-02 permitía extraer a cualquier persona la tabla `usuarios` con las contraseñas
en texto plano (PNK-03).

**Impacto para los titulares.** Toma de control de sus cuentas aquí y en otros servicios donde reutilicen la
contraseña.

**Requisito legal relacionado.**
- Ley 19.628 (vigente): art. 11, deber de cuidado con la debida diligencia de los datos almacenados.
- Ley 21.719 (readiness): art. 14 quinquies, deber de adoptar medidas de seguridad adecuadas.

**Mapeo técnico.** ISO 27001: A.5.34, A.8.24, A.8.28 · ASC-11, ASC-12 · ASVS: v5.0.0-11.4.2, v5.0.0-1.2.4

**Recomendación / estado.** Corregido en v2 (bcrypt + parametrización). Si la v1 estuvo expuesta, tratarlo como
un **posible incidente** (ver §6): forzar el cambio de contraseñas y evaluar si hay que comunicarlo.

**Evidencia de cierre.** T01, T02 y T09, y la columna `password` con hashes `$2y$12$…`.
**Riesgo residual:** Bajo.

### [PRIV-02] Sin aviso de privacidad ni información al titular

**Riesgo:** Medio · **Tratamiento:** T1, T2, T3, T7

**Situación.** v1 no informaba qué datos se recolectan, para qué ni que el nombre se publica junto al comentario.

**Requisito.** Ley 19.628 (vigente): art. 4, información al titular sobre el propósito al pedir su
consentimiento. Ley 21.719 (readiness): principio de transparencia e información (art. 3).

**Mapeo.** ISO 27001: A.5.34 · ASC-03 · ASVS: v5.0.0-14.1.1

**Recomendación / estado.** v2 agrega `privacidad.php`, enlazado desde el pie de página y desde el formulario de
comentarios. **Pendiente:** el responsable debe completar los campos `[ENTRE CORCHETES]` (identidad, base de
licitud, contacto) y validarlos jurídicamente.

**Evidencia de cierre.** Aviso publicado y revisado por quien corresponda.

### [PRIV-03] El ejercicio de derechos dependía de procesos manuales no trazables

**Riesgo:** Medio · **Tratamiento:** T1, T2

**Situación.** En v1 no había forma de eliminar comentarios ni cuentas.

**Requisito.** Ley 19.628 (vigente): art. 12, derechos de información, modificación, cancelación y bloqueo. Ley
21.719 (readiness): acceso, rectificación, supresión, oposición, portabilidad y bloqueo. Validar plazos vigentes.

**Mapeo.** ISO 27001: A.5.34, A.8.10 · ASC-11 · ASVS: v5.0.0-8.2.2 (propiedad), v5.0.0-14.2.7 (L3, retención)

**Estado.** **Cumple parcial.**
- El titular puede eliminar sus propios comentarios con control de propiedad (T15/T16).
- **Pendiente:** autoservicio de acceso/exportación (portabilidad), rectificación del nombre y baja de la cuenta.
  Mientras tanto, procedimiento manual con registro: solicitud al correo del aviso → verificar identidad →
  ejecutar → registrar en `logs/` → responder. Ver ACC-09.

### [PRIV-04] Transferencia de IP de visitantes a Google Fonts

**Riesgo:** Bajo-Medio · **Tratamiento:** T7

**Situación.** `css/style.css` importa fuentes desde `fonts.googleapis.com`. Cada visita envía la IP y el
user-agent del visitante a Google, posiblemente fuera de Chile, sin que el sitio lo necesite para funcionar.

**Requisito.** Ley 21.719 (readiness): Título V, transferencias internacionales (art. 27 y siguientes), y
minimización/proporcionalidad (art. 3). No asumir que usar un servicio extranjero es jurídicamente neutro.

**Mapeo.** ISO 27001: A.5.23, A.5.34 · ASC-03, ASC-30 · ASVS: v5.0.0-14.2.3, v5.0.0-3.6.1 (L3)

**Recomendación.** **Alojar las fuentes localmente** (descargar los `.woff2`, servirlas desde `/fonts` y quitar
los dominios de Google de la CSP). Así desaparece la transferencia. Estado: **pendiente** (ACC-06), informado en
el aviso de privacidad.

### [PRIV-05] Sin retención ni eliminación definidas

**Riesgo:** Bajo · **Tratamiento:** T3, T4

**Situación.** v1 no tenía logs. v2 los introduce, así que hay que limitar cuánto tiempo se guardan.

**Requisito.** Ley 19.628: art. 6, eliminación de datos caducos o sin fundamento, y art. 9, finalidad. Ley 21.719
(readiness): proporcionalidad (art. 3) y protección por defecto (art. 14 quáter).

**Mapeo.** ISO 27001: A.8.10 · ASC-11 · ASVS: v5.0.0-14.2.7

**Estado.** `herramientas/purgar_retencion.php` elimina los intentos de login con más de 30 días. **Pendiente:**
rotación de `logs/security.log` a 180 días (logrotate o similar).

## 3. Privacidad desde el diseño y por defecto (art. 14 quáter, readiness)

| Control | Implementación v2 |
|---|---|
| Minimización | El carrito no se asocia a la identidad. El RUT del restaurante no se consulta ni se publica. Los logs no guardan el correo en claro |
| Seudonimización | `seudonimo()` = HMAC-SHA256 con pepper configurable. **No es anonimización**: quien tiene el pepper puede reidentificar |
| Mínimo privilegio | Usuario de BD `pnk_app`. Cada quien sólo borra sus propios comentarios |
| Defaults protectores | `APP_ENV=prod` por defecto, errores ocultos, cookie HttpOnly/SameSite, rechazo de `root` |
| Ambientes no productivos | Pruebas sólo con datos sintéticos. El seed v2 no incluye cuentas |
| Retención | Purga de `login_intentos` a 30 días |

## 4. EIPD (art. 15 ter, readiness)

**Conclusión preliminar: no se identifica hoy un supuesto que obligue a una EIPD.** No hay decisiones
automatizadas ni perfilamiento, no hay tratamiento masivo, no hay monitoreo de zonas públicas y no hay datos
sensibles por diseño. **Reevaluar** si se agregan pedidos con pago, geolocalización, analítica de comportamiento o
cuentas para menores.

```text
EIPD-ID: EIPD-PNK-00 (evaluación de necesidad)
Tratamiento: T1–T7
Volumen: bajo
Riesgos para titulares: toma de cuentas (mitigado), publicación involuntaria de datos en comentarios (aviso + supresión)
Resultado: EIPD no requerida a la fecha; revisar ante cambios
Aprobación: [Responsable]   Revisión: [fecha + 12 meses o ante cambio relevante]
```

## 5. Encargados y DPD

- **Hosting:** si un proveedor externo aloja la app, es un **encargado**. Hay que documentar instrucciones,
  seguridad, confidencialidad, incidentes, devolución y supresión (art. 15 bis, readiness; ISO A.5.19-A.5.23).
- **DPD:** no se afirma que sea obligatorio. Corresponde al responsable revisar las reglas sectoriales y
  contractuales, y considerarlo si adopta el Modelo de Prevención de Infracciones (art. 49 y siguientes) según su
  reglamento vigente.

## 6. Gestión de vulneraciones (art. 14 sexies, readiness)

Si la v1 estuvo publicada en Internet, sus vulnerabilidades permitían extraer datos personales. **No se afirma
un plazo de 72 horas.** El régimen chileno modificado habla de reportar **sin dilaciones indebidas** cuando
concurre el supuesto legal, y los plazos específicos deben validarse con el reglamento y las instrucciones
vigentes de la Agencia.

```text
ID incidente: [INC-AAAA-NN]
Fecha/hora detección:            Fecha/hora ocurrencia estimada:
Datos afectados: [p. ej. tabla usuarios: nombre, correo, contraseña en claro]
Titulares: [n.º aproximado]      Sistemas: [URL/servidor]
Causa: SQLi PNK-01/PNK-02 (v1)   Riesgo: [alto si hubo acceso a contraseñas]
Contención: desplegar v2, invalidar sesiones, forzar cambio de contraseñas
Notificación: [Agencia / titulares, según el supuesto legal vigente]   Fecha notificación:
Medidas correctivas: v2 + controles ACC-01…ACC-10
Responsable:                     Cierre:
```

Evidencia disponible para la investigación: los logs v2 (`logs/security.log`, en UTC).

## 7. Privacy Gates

| Gate | Verificación | Resultado |
|---|---|---|
| **P1 Requisitos** | Finalidad, categorías, titulares, retención y transferencias identificadas (RAT §1) | **CUMPLE PARCIAL** — falta identificar al responsable y validar la base de licitud |
| **P2 Diseño** | Privacy by design/default, control de acceso, seudonimización, retención y eliminación (§3) | **CUMPLE PARCIAL** — falta autoservicio de acceso/portabilidad y fuentes locales |
| **P3 Desarrollo/QA** | Sin datos productivos en pruebas, logs sin datos en claro, autorización, borrado (T15, T16, T22) | **CUMPLE** |
| **P4 Producción** | Aviso completado, contratos con hosting, procedimiento de incidentes y responsable operativo | **NO VERIFICADO** — no hay evidencia del ambiente productivo |

## 8. Mapeo resumido

| Requisito | Ley 19.628 (vigente) | Ley 21.719 (readiness) | ISO 27001 | ASC | ASVS v5.0.0 |
|---|---|---|---|---|---|
| Información / transparencia | art. 4 | art. 3 | A.5.34 | ASC-03 | 14.1.1 |
| Finalidad | art. 9 | art. 3 | A.5.34, A.8.26 | ASC-03 | 14.1.2 |
| Seguridad / deber de cuidado | art. 11 | art. 14 quinquies | A.8.24-A.8.29 | ASC-04, ASC-12, ASC-20 | 1.2.4, 11.4.2, 7.2.4 |
| Derechos | art. 12 | derechos del titular (validar artículos) | A.5.34 | ASC-11 | 8.2.2 |
| Eliminación de datos caducos | art. 6 | art. 3, art. 14 quáter | A.8.10 | ASC-11 | 14.2.7 |
| Diseño y por defecto | — | art. 14 quáter | A.8.25-A.8.28 | ASC-03, ASC-06 | 13.x, 14.x |
| Vulneraciones | — | art. 14 sexies | A.5.24-A.5.28 | ASC-27, ASC-31 | 16.3.x |
| Transferencia internacional | — | art. 27 y ss. | A.5.23 | ASC-30 | 14.2.3 |
| EIPD | — | art. 15 ter | A.5.8 | ASC-04 | — |
