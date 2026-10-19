# ANF — Application Normative Framework · PNK Security (ISO/IEC 27034)

> Los identificadores `ASC-xx` son la **taxonomía corporativa propuesta** por el skill. No son numeración oficial
> de ISO/IEC 27034.

## 1. ONF de referencia (marco organizacional mínimo)

En el entregable original no había un ONF. Se propone como referencia mínima para este proyecto:

| Elemento ONF | Referencia en este paquete | Estado |
|---|---|---|
| Política / estándar de desarrollo seguro | Este ANF + `04_Catalogo_ASC.md` | Propuesto |
| Estándar de codificación | Reglas de `setup/setup.php`: PDO preparado, `e()`, `entero_positivo()`, `exigir_post_csrf()` | Implementado |
| Metodología de riesgos | Severidad según skill §22 + STRIDE (`05_Threat_Model.md`) | Aplicado |
| Criterios de pruebas y aceptación | `07_Security_Gates.md`, `tests/` | Aplicado |
| Reglas de excepción | `08_Plan_Remediacion.md §4` | Aplicado |
| Requisitos de privacidad | `06_Privacidad_Ley19628_21719.md` | Aplicado |
| Herramientas autorizadas | PHP lint, `tests/sast_basico.php`, `tests/pruebas_seguridad.php`, Playwright. Recomendado: Semgrep, OWASP ZAP | Parcial |
| Roles y responsabilidades | RACI en `08_Plan_Remediacion.md §5` | Propuesto |

## 2. Contexto de la aplicación

| Campo | Valor |
|---|---|
| Negocio | Carta digital por código QR para restaurantes: ver productos, armar una lista para pedirle al garzón y comentar |
| Regulatorio | Ley 19.628 (vigente); Ley 21.719 (readiness, vigencia 01-12-2026). Sin regulación sectorial identificada |
| Tecnológico | PHP 8.x + PDO, MySQL 8, Apache, Bootstrap 4.6.2, jQuery 3.7.1 |
| Actores | Visitante anónimo (comensal), usuario registrado (comentarista), administrador de BD/hosting |
| Roles en la app | `anónimo`: ver la carta y usar el carrito. `usuario`: además comentar y eliminar sus propios comentarios. No hay rol admin web |
| Datos | Ver la clasificación en §3 |
| Exposición | Pública en Internet (se accede por QR) |
| Ciclo de vida | Proyecto académico (Laboratorio de Programación Segura I) → posible uso real |

## 3. Clasificación de datos

| Dato | Clasificación | Protección requerida |
|---|---|---|
| Hash de contraseña | Confidencial / credencial | bcrypt coste 12; nunca en logs ni en respuestas; acceso sólo desde login |
| Correo del usuario | Personal / confidencial | No se publica; seudonimizado en logs |
| Nombre del usuario | Personal / público por decisión del titular (en comentarios) | Aviso al publicar; supresión |
| Comentarios | Público, pero puede contener datos personales | Escape de salida; supresión por el titular |
| IP / logs | Personal (indirecto) / interno | Fuera del webroot, retención de 180 días |
| Carta, precios, datos del local | Público | Integridad (sólo lectura para `pnk_app`) |
| RUT del restaurante | Interno (personal si es persona natural) | No se publica ni se consulta desde la app |

## 4. Reglas de autorización (ASVS v5.0.0-8.1.1)

| Función | Anónimo | Usuario | Regla de datos |
|---|---|---|---|
| Ver carta (`index.php`, `mostrar_carrito.php`) | ✔ | ✔ | Sólo restaurantes, cartas, categorías e items no eliminados y visibles |
| Carrito (`carrito.php`) | ✔ | ✔ | Sólo su propia sesión; sólo items visibles; máximo 50 |
| Login / logout | ✔ | ✔ | POST + CSRF |
| Comentar (`grcomentarios.php` crear) | ✖ (403) | ✔ | Autor = sesión; restaurante existente |
| Eliminar comentario | ✖ | ✔ sólo los propios | `usuario_id = sesión` |
| Crear usuarios / migrar / purgar | ✖ | ✖ | Sólo CLI con credenciales administrativas |

## 5. Nivel de riesgo y nivel objetivo de confianza

- **Perfil del skill (§9): B — riesgo medio/alto.** Tiene exposición pública, autenticación y datos personales.
  No maneja salud, finanzas ni datos sensibles a gran escala.
- **Objetivo: OWASP ASVS v5.0.0 Nivel 2.** Se aplican además algunos requisitos L3 de bajo costo (6.3.8, 16.5.4).
  **No se justifica L3 completo.** Habría que reevaluarlo si se incorporan pagos o pedidos con identificación.
- Estado actual frente a L2: **parcial**. Ver brechas en `02_Matriz_Cumplimiento.md` (MFA, TLS, gestor de
  secretos, logs centralizados).

## 6. ASMP — proceso aplicado

| Etapa ISO/IEC 27034 | Actividad realizada | Salida |
|---|---|---|
| 1. Identificar requisitos y entorno | Revisión de código, BD y superficie de ataque | §2-§4, Informe §2-§3 |
| 2. Evaluar riesgos | STRIDE y severidad de hallazgos | `05_Threat_Model.md`, Informe §4 |
| 3. Crear y mantener el ANF | Este documento + selección de ASC | `04_Catalogo_ASC.md` |
| 4. Aprovisionar y operar | Código v2, scripts de BD, config por ambiente, herramientas CLI | Paquete v2 |
| 5. Auditar | Pruebas automatizadas, SAST, Security Gates | `tests/`, `07_Security_Gates.md` |

## 7. ASC seleccionados

ASC-01, 02, 03, 04, 05, 06, 07, 08, 09, 10, 11, 12, 13, 14, 15, 17, 18, 20, 22, 24, 27, 28, 29, 31 y 32 (detalle en
`04_Catalogo_ASC.md`).
**No seleccionados por ahora:** ASC-16, ASC-25 y ASC-26 (no hay repositorio ni pipeline entregados, quedan como
pendiente ACC-05), ASC-19 y ASC-21 (no hay ambiente autorizado, ACC-02), ASC-23 (no hay ambientes formales) y
ASC-30 (no hay desarrollo de terceros. Hosting y Google Fonts se tratan en privacidad).

## 8. Excepciones y riesgos residuales

Ver `08_Plan_Remediacion.md §2 y §4` (EXC-01 TLS, EXC-02 gestor de secretos, EXC-03 MFA).

## 9. Revisión

Revisar el ANF cuando cambie la arquitectura (pagos, panel de administración, carga de imágenes), cuando haya un
incidente o, como mínimo, una vez al año. Próxima revisión: [fecha].
