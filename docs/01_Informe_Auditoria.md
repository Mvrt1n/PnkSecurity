# Informe de Revisión de Código Seguro — PNK Security

| Campo | Valor |
|---|---|
| Aplicación | PNK Security — carta digital de restaurantes (QR), carrito, login y comentarios |
| Versión revisada | v1 (entregada en `pnkSecurity_1.zip`, fecha de BD 2020-11-05) |
| Versión corregida | v2 (este paquete) |
| Fecha del análisis | 19-10-2026 |
| Tipo de revisión | Revisión manual de código + SAST por patrones + SCA manual + pruebas automatizadas locales |
| Marcos | ISO/IEC 27001:2022 · ISO/IEC 27034 (ONF/ASMP/ANF/ASC) · OWASP ASVS v5.0.0 · Ley 19.628 / Ley 21.719 |
| Autor | Martín Díaz (con asistencia de Claude) |

> **Alcance y autorización (skill §46).** Sólo se analizó el código y la base de datos entregados. Las pruebas
> dinámicas se ejecutaron **únicamente en un entorno local aislado** (`php -S 127.0.0.1` + SQLite con datos
> sintéticos). **No** se hicieron pruebas activas contra ningún servidor, dominio ni base real. Para un DAST o
> pentest sobre un ambiente desplegado se necesita una autorización formal con alcance, ventana y responsables.

---

## 1. Resumen ejecutivo

La versión v1 **no es apta para producción**. Tiene dos vulnerabilidades **críticas** de inyección SQL. Una de
ellas permite **entrar sin conocer la contraseña** y la otra permite **leer la base de datos completa** sin
autenticarse, incluidas las contraseñas, que se guardan **en texto plano**. A esto se suman XSS almacenado y
reflejado, ausencia de CSRF, una conexión a MySQL como `root` sin contraseña y escrita en el código, y librerías
con CVE conocidos.

| Severidad | v1 (abiertos) | v2 (tras corrección) |
|---|---|---|
| Crítica | 2 | 0 |
| Alta | 5 | 0 |
| Media | 7 | 0 en código · 2 con dependencia de infraestructura (PNK-08 Secure, PNK-14 TLS) |
| Baja | 3 | 0 |
| **Total** | **17** | — |

La v2 corrige los 17 hallazgos en código. **24/24 pruebas automatizadas pasan** (`tests/resultados_pruebas.txt`)
y el SAST por patrones baja de **51 a 1** coincidencias; la que queda es `session_destroy()` dentro del
logout, que es su uso legítimo. **Quedan pendientes controles que no dependen del código** (TLS en el servidor,
gestor de secretos, MFA, DAST en QA, completar el aviso de privacidad). Por eso el Security Gate de producción
queda **APROBADO CON EXCEPCIÓN** sólo para un ambiente de laboratorio/QA (ver `07_Security_Gates.md`).

**Decisión requerida:** aprobar las excepciones EXC-01 a EXC-03 o desplegar los controles de infraestructura
antes de exponer la aplicación a Internet.

## 2. Tecnologías detectadas

| Componente | v1 | v2 |
|---|---|---|
| Lenguaje | PHP (procedural, sin framework) usando `mysqli` | PHP ≥ 8.1 con PDO, `declare(strict_types=1)` |
| Base de datos | MySQL 8.0.17 (dump MySQL-Front), BD en `latin1` | MySQL 8 · `utf8mb4` · usuario `pnk_app` con mínimo privilegio |
| Frontend | Bootstrap 4.1.3, jQuery 3.2.1, Owl Carousel 2.3.4, Magnific Popup 1.1.0, nice-select, plantilla con plugins sin uso | Bootstrap 4.6.2, jQuery 3.7.1, Owl Carousel 2.3.4 (sólo lo usado) |
| Servidor | Apache (XAMPP, inferido) | Apache + `.htaccess` de endurecimiento |
| Terceros | Google Fonts (CSS `@import`) | Igual; documentado como transferencia (PRIV-04) |

## 3. Superficie de ataque

| Punto de entrada | Método | Parámetros | Autenticación | Riesgo v1 |
|---|---|---|---|---|
| `index.php` | GET | `id` | No | SQLi (×6 consultas), XSS reflejado |
| `mostrar_carrito.php` | GET | `id`, `keyid` | No | SQLi, XSS reflejado |
| `carrito.php` | POST (AJAX) | `op`, `iditems`, `pos` | No | SQLi, CSRF, lógica |
| `setup/procesalogin.php` | POST | `frmusuario`, `frmpassword` | — | SQLi (bypass de autenticación) |
| `grcomentarios.php` | POST | `usuario`, `comentario` + `$_SESSION['id']` | No (sólo se ocultaba el formulario) | SQLi, XSS almacenado, suplantación |
| `setup/cerrar_sesion.php` | GET | — | — | CSRF de logout |
| `Script_BD/*.sql`, archivos sin uso | GET | — | No | Divulgación si se publica la carpeta completa |

Fronteras de confianza y flujos: ver `05_Threat_Model.md`.

---

## 4. Hallazgos

Las IDs ASVS se citan contra el **texto oficial de OWASP ASVS v5.0.0** (tag `v5.0.0_release`, repositorio
OWASP/ASVS). Los controles ISO se citan por número y título del Anexo A. Los `ASC-xx` son la **taxonomía
corporativa** del skill y **no** son numeración oficial de ISO/IEC 27034.

### [PNK-01] Inyección SQL en el login que permite saltarse la autenticación

**Severidad:** Crítica · **Estado:** Corregido (v2) · **Componente:** `setup/procesalogin.php` · **Ambiente:** todos

**Descripción.** El correo y la contraseña se concatenan directamente en el SQL.

```php
// v1 — setup/procesalogin.php:5
$sql="select * from usuarios where email='".$_POST['frmusuario']."' and password='".$_POST['frmpassword']."'";
```

**Escenario de explotación.** Con el usuario `' OR '1'='1' -- ` la consulta queda
`... WHERE email='' OR '1'='1' -- ' and password=...`, que devuelve la primera fila (Administrador). La app
inicia sesión con ese registro sin conocer ninguna credencial.

**Impacto.** Suplantación de cualquier cuenta, incluida la de administrador. Confidencialidad e integridad
comprometidas.

**Causa raíz.** SQL dinámico concatenado, sin parametrización.

**Corrección (v2).**

```php
$usuario = consulta(
    "SELECT Id, nombre, password FROM usuarios WHERE email = :email AND estado = '1'",
    [':email' => $email]
)->fetch();
$valida = password_verify($password, $hash);   // la contraseña nunca va al SQL
```

`conectar()` usa `PDO::ATTR_EMULATE_PREPARES => false`, así que la parametrización ocurre de verdad en el servidor
MySQL.

**Mapeo.** ISO 27001: A.8.28, A.8.5, A.8.29 · ASC-07, ASC-10, ASC-14 · ASVS: v5.0.0-1.2.4, v5.0.0-6.3.8 (L3; ver T08)

**Evidencia de cierre:** pruebas T01 y T08 (`tests/resultados_pruebas.txt`) y `tests/evidencia/sast_corregido.txt`.
**Criterio de cierre:** el payload no autentica y no queda SQL concatenado en el login. ✔

---

### [PNK-02] Inyección SQL en parámetros públicos (`id`, `keyid`, `iditems`, sesión)

**Severidad:** Crítica · **Estado:** Corregido · **Componentes:** `index.php` (6 consultas), `mostrar_carrito.php`, `carrito.php`, `grcomentarios.php`

**Descripción.** `$_GET['id']` se concatena sin comillas en consultas numéricas:

```php
// v1 — index.php:10
"... WHERE restautantes.id = ".$key." AND restautantes.eliminado IS NULL";
// v1 — carrito.php:19
"select id, nombre, precio from items where id=".$_POST['iditems'];
// v1 — grcomentarios.php:6  (id_restaurante sale de $_SESSION['id'], que a su vez es $_GET['id'])
"INSERT INTO comentarios SET usuario='".$_POST['usuario']."',comentario='".$_POST['comentario']."',id_restaurante=".$_SESSION['id'];
```

**Escenario.** `index.php?id=1 UNION SELECT ...` (consulta de 9 columnas) permite extraer `usuarios.email` y
`usuarios.password`, que están **en texto plano** (PNK-03), **sin autenticarse**. En `grcomentarios.php` el valor
contaminado se guarda en la sesión y se reutiliza más tarde: es una inyección de segundo orden.

**Impacto.** Divulgación total de la base de datos, incluidos datos personales. En el caso del `INSERT`, además,
manipulación de datos.

**Corrección.** Todas las consultas pasan por `consulta($sql, $params)` con placeholders. Los identificadores se
validan con lista blanca (`entero_positivo()`: sólo `^[1-9][0-9]{0,9}$`) y un id inválido o de un restaurante
eliminado responde **404**. El restaurante del comentario ya no se toma de la sesión: viaja en el formulario, se
valida y se verifica que exista.

**Mapeo.** ISO 27001: A.8.28, A.8.26 · ASC-10, ASC-14 · ASVS: v5.0.0-1.2.4, v5.0.0-2.2.1, v5.0.0-2.2.2

**Evidencia:** T09, T10, T18. **Criterio:** cero consultas concatenadas y payloads rechazados. ✔

---

### [PNK-03] Contraseñas en texto plano y cuentas por defecto

**Severidad:** Alta · **Estado:** Corregido · **Componente:** `Script_BD/pnk_security.sql`, login

**Descripción.** `usuarios.password` guarda las contraseñas tal cual (`admin01`, `alondra01`), la comparación se
hace en el propio SQL y el script crea una cuenta por defecto `admin@gmail.com`.

**Impacto.** Cualquier lectura de la BD (PNK-02, un respaldo o un DBA) expone todas las credenciales, que además
pueden reutilizarse en otros servicios (credential stuffing).

**Corrección.**
- `password_hash()` con bcrypt de coste 12 (`HASH_OPCIONES`) y `password_verify()`, con re-hash transparente.
- El script v2 **no crea cuentas**. `herramientas/crear_usuario.php` (sólo CLI) pide la contraseña sin eco y
  exige largo de 8 a 128 caracteres y que no esté en una lista de contraseñas comunes.
- Para una BD existente: `Script_BD/03_migracion_v1_a_v2.sql` + `herramientas/migrar_passwords.php`, y
  deshabilitar `admin@gmail.com`.
- Hash ficticio cuando el usuario no existe, para igualar los tiempos de respuesta.

**Mapeo.** ISO 27001: A.5.17, A.8.5, A.8.24 · ASC-07, ASC-12 · ASVS: v5.0.0-11.4.2, v5.0.0-6.3.2, v5.0.0-6.2.1

**Evidencia:** T02 y T06, la columna `password` con hash y el seed sin cuentas.
**Riesgo residual:** las contraseñas v1 deben considerarse comprometidas, así que hay que forzar su cambio (ACC-04).

---

### [PNK-04] Credenciales de base de datos `root` sin contraseña escritas en el código

**Severidad:** Alta · **Estado:** Corregido (código) / Pendiente (infraestructura) · **Componente:** `setup/setup.php:5`

```php
$con=mysqli_connect("localhost","root","","pnk_security");   // v1
```

**Impacto.** Cualquier SQLi se ejecuta con privilegios de superusuario (DROP, FILE, creación de usuarios) y el
secreto queda en el repositorio.

**Corrección.** La configuración se lee de variables de entorno o de `config/config.local.php`, que está **fuera
del webroot** y en `.gitignore`. `conectar()` **se niega a conectar** como `root` o con contraseña vacía. Se agrega
`Script_BD/02_usuario_app.sql` con un usuario `pnk_app` que sólo tiene SELECT/INSERT/DELETE en las tablas que
necesita y `UPDATE(password)` en `usuarios`.

**Mapeo.** ISO 27001: A.8.2, A.8.24, A.8.9 · ASC-13 · ASVS: v5.0.0-13.2.2, v5.0.0-13.2.3, v5.0.0-13.3.1 (gestor de secretos: **pendiente**, EXC-02)

**Evidencia:** T24 y `02_usuario_app.sql`.

---

### [PNK-05] XSS almacenado en comentarios y en datos de la base

**Severidad:** Alta · **Estado:** Corregido · **Componente:** `index.php:414-416` (v1)

```php
<b><?php echo $datoscomentarios['usuario'];?></b><br><?php echo $datoscomentarios['comentario'];?>   // v1
```

**Escenario.** Un comentario con `<script>…</script>` se ejecuta en el navegador de cada visitante de la carta.
La cookie de sesión de v1 no tenía `HttpOnly`, así que el script podía robarla. Lo mismo vale para nombres y
descripciones de platos que vengan de la BD.

**Corrección.** Función `e()` (`htmlspecialchars` con `ENT_QUOTES | ENT_SUBSTITUTE`, UTF-8) aplicada a **toda**
salida dinámica, `nl2br(e(...))` para los comentarios, CSP `script-src 'self'` como defensa en profundidad, y
`.text()` en vez de `.html()` en el JS.

**Mapeo.** ISO 27001: A.8.28 · ASC-10 · ASVS: v5.0.0-1.2.1, v5.0.0-3.2.2, v5.0.0-3.4.3

**Evidencia:** T13, T20 y la captura `tests/evidencia/captura_app_corregida.png`, donde el payload aparece como texto.

---

### [PNK-06] Control de acceso roto en comentarios (sin autenticación, suplantación de autor)

**Severidad:** Alta · **Estado:** Corregido · **Componente:** `grcomentarios.php`

**Descripción.** El formulario sólo se **ocultaba** en el frontend cuando no había sesión, pero el endpoint no
verificaba la autenticación. El autor (`usuario`) se tomaba del formulario, así que cualquiera podía publicar
como "Administrador".

**Corrección.** Se verifica la sesión en el servidor (403 si no hay). El autor y su `usuario_id` se toman de la
sesión. Se agrega la eliminación del propio comentario, con control de propiedad
`WHERE Id = :id AND usuario_id = :u`, que evita IDOR y además da soporte técnico al derecho de supresión.

**Mapeo.** ISO 27001: A.5.15, A.8.3 · ASC-08 · ASVS: v5.0.0-8.2.1, v5.0.0-8.2.2, v5.0.0-8.3.1, v5.0.0-16.3.2

**Evidencia:** T12, T14, T15 y T16.

---

### [PNK-07] Ausencia de protección CSRF

**Severidad:** Media · **Estado:** Corregido · **Componentes:** login, logout (GET), comentarios, carrito

**Escenario.** Un sitio externo podía enviar formularios a nombre de la víctima: publicar comentarios, vaciar
el carrito, forzar un login con la cuenta del atacante o cerrar la sesión con una simple imagen.

**Corrección.** Token sincronizado por sesión de 256 bits (`random_bytes`) comparado con `hash_equals`. En el
carrito viaja en la cabecera `X-CSRF-Token`. Todas las operaciones que cambian estado exigen **POST** (405 en
otro caso) y la cookie usa `SameSite=Lax`.

**Mapeo.** ISO 27001: A.8.28 · ASC-09, ASC-29 · ASVS: v5.0.0-3.5.1, v5.0.0-3.5.3, v5.0.0-3.3.2

**Evidencia:** T04, T05 y T17.

---

### [PNK-08] Gestión de sesión deficiente

**Severidad:** Media · **Estado:** Corregido · **Componentes:** todos los `session_start()`

**Descripción.** No se regeneraba el ID de sesión al autenticar (fijación de sesión). La cookie salía sin
`HttpOnly`/`SameSite` y sin `Secure`. No había expiración por inactividad ni absoluta, y el logout no borraba
la cookie.

**Corrección.** `iniciar_sesion_segura()` activa `use_strict_mode`, HttpOnly, SameSite=Lax y Secure, con prefijo
`__Host-` cuando hay HTTPS. Expira a los 30 minutos de inactividad o a las 8 horas en total. Se llama a
`session_regenerate_id(true)` en el login, y el logout destruye la sesión y la cookie.

**Mapeo.** ISO 27001: A.8.5 · ASC-09 · ASVS: v5.0.0-7.2.4, v5.0.0-7.3.1, v5.0.0-7.3.2, v5.0.0-7.4.1, v5.0.0-3.3.1, v5.0.0-3.3.4

**Evidencia:** T02, T03 y T05. **Pendiente:** `Secure` depende de que el servidor tenga TLS (EXC-01).

---

### [PNK-09] Login sin controles anti-automatización

**Severidad:** Media · **Estado:** Corregido · **Componente:** `procesalogin.php`

**Corrección.** Tabla `login_intentos` que guarda el correo **seudonimizado** (HMAC) y la IP. Bloquea durante
15 minutos tras 5 fallos por cuenta o 30 por IP. El límite por IP es más alto a propósito, para no bloquear a
todos los clientes que comparten el Wi-Fi o el NAT de un restaurante. El mensaje de error es genérico.

**Mapeo.** ISO 27001: A.8.5 · ASC-07 · ASVS: v5.0.0-6.3.1, v5.0.0-6.1.1 (documentado aquí), v5.0.0-2.4.1

**Evidencia:** T07 y T08.

---

### [PNK-10] Cuentas inactivas pueden autenticarse

**Severidad:** Media · **Estado:** Corregido. El campo `estado` existía pero el login lo ignoraba. Ahora la
consulta exige `estado = '1'`.
**Mapeo.** ISO 27001: A.5.18 · ASC-07, ASC-08 · ASVS: v5.0.0-7.4.2 · **Evidencia:** T06.

---

### [PNK-11] Fallas de lógica en el carrito

**Severidad:** Baja · **Estado:** Corregido · **Componente:** `carrito.php`

"Eliminar todo" llamaba a `session_destroy()`, lo que **cerraba la sesión del usuario**. Además se podían agregar
productos inexistentes u ocultos (`visible=0`), las posiciones eran predecibles y el carrito no tenía límite.
**Corrección:** vaciar sólo `$_SESSION['carrito']`, validar que el item sea visible y no esté eliminado, usar
posiciones aleatorias, un máximo de 50 items, el precio siempre desde el servidor y respuesta JSON con
`Content-Type` correcto.
**Mapeo.** ISO 27001: A.8.28 · ASC-14 · ASVS: v5.0.0-2.3.1, v5.0.0-2.3.2, v5.0.0-2.4.1, v5.0.0-4.1.1 · **Evidencia:** T18 y T19.

---

### [PNK-12] XSS reflejado en `id` / `keyid`

**Severidad:** Alta (la cookie de v1 no tenía HttpOnly, así que permitía robar la sesión) · **Estado:** Corregido

```php
<a href="mostrar_carrito.php?keyid=<?php echo $_GET['id'];?>">            // v1 index.php:89
<a class="volver" href="index.php?id=<?php echo $_GET['keyid'];?>">        // v1 mostrar_carrito.php:61
```

**Corrección.** El valor se valida como entero antes de cualquier uso. En las URLs sólo se imprime el entero ya
validado y el resto se escapa.
**Mapeo.** ISO 27001: A.8.28 · ASC-10 · ASVS: v5.0.0-1.2.1, v5.0.0-1.2.2 · **Evidencia:** T11.

---

### [PNK-13] Componentes de terceros con vulnerabilidades conocidas

**Severidad:** Media · **Estado:** Corregido · Detalle completo en el SBOM (`docs/sbom.cdx.json`) y en `08_Plan_Remediacion.md §3`.

| Componente | v1 | Problema | v2 |
|---|---|---|---|
| jQuery | 3.2.1 | CVE-2019-11358 (prototype pollution, corregido en 3.4.0); CVE-2020-11022 / CVE-2020-11023 (XSS en manipulación de HTML, corregidos en 3.5.0) | 3.7.1 (SHA-256 verificado contra el SRI oficial) |
| Bootstrap | 4.1.3 | CVE-2019-8331 (XSS en tooltip/popover, corregido en 4.3.1). La rama 4.x terminó su soporte en enero de 2023 | 4.6.2 (SHA-384 verificado). Migrar a 5.x queda como mejora (ACC-07) |
| Magnific Popup, nice-select, ajaxchimp, jquery.form, validate, skrollr, countdown, fontawesome, linericon, flat-icon, nouislider, counter-up | varias | Sin uso o sin mantenimiento; amplían la superficie de ataque | Eliminados |

> Los CVE deben revalidarse contra NVD/GitHub Advisory al momento de la auditoría formal.

**Mapeo.** ISO 27001: A.8.8, A.5.21 · ASC-15, ASC-22 · ASVS: v5.0.0-15.1.1, v5.0.0-15.1.2, v5.0.0-15.2.1, v5.0.0-15.2.3

---

### [PNK-14] Sin cabeceras de seguridad ni HTTPS

**Severidad:** Media · **Estado:** Corregido (cabeceras) / Pendiente (TLS en el servidor, EXC-01)

**Corrección.** `cabeceras_seguridad()` envía CSP (`default-src 'self'; script-src 'self'; frame-ancestors 'none'; …`),
`X-Content-Type-Options: nosniff`, `Referrer-Policy`, `Cross-Origin-Opener-Policy`, `Permissions-Policy`,
`X-Frame-Options`, `Cache-Control: no-store` y HSTS de 1 año cuando hay HTTPS. Además quita `X-Powered-By`.
Se eliminó todo el JS inline para que la CSP no necesite `'unsafe-inline'` en scripts.
**Riesgo residual:** `style-src 'unsafe-inline'`, porque la plantilla y Owl Carousel usan estilos inline.

**Mapeo.** ISO 27001: A.8.20, A.8.24 · ASC-06 · ASVS: v5.0.0-3.4.1, v5.0.0-3.4.3 a 3.4.6, v5.0.0-3.4.8, v5.0.0-12.2.1, v5.0.0-14.3.2 · **Evidencia:** T21.

---

### [PNK-15] Ausencia de logging de seguridad

**Severidad:** Media · **Estado:** Corregido

**Corrección.** `log_seguridad()` escribe en JSON Lines con fecha UTC, evento, IP, ruta y usuario. Los
`json_encode` evitan la inyección de logs. El archivo queda fuera del webroot y el correo se guarda
seudonimizado. Eventos registrados: `login_exitoso`, `login_fallido`, `login_bloqueado`, `logout`,
`sesion_expirada`, `csrf_invalido`, `authz_denegada`, `validacion_fallida`, `error_no_controlado`,
`usuario_creado`, `purga_retencion`.
**Pendiente:** enviar los logs a un sistema separado (ASVS v5.0.0-16.4.3), lo que corresponde a infraestructura.

**Mapeo.** ISO 27001: A.8.15, A.8.16 · ASC-27, ASC-31 · ASVS: v5.0.0-16.2.1, v5.0.0-16.2.2, v5.0.0-16.2.5, v5.0.0-16.3.1 a 16.3.3, v5.0.0-16.4.1 · **Evidencia:** T22 y `tests/evidencia/log_seguridad_ejemplo.jsonl`.

---

### [PNK-16] Manejo de errores y divulgación de información

**Severidad:** Baja · **Estado:** Corregido

En v1, cuando el `id` era inválido aparecían warnings y errores de `mysqli` con rutas internas (si
`display_errors` estaba activo, que es lo habitual en XAMPP). Además había un *off-by-one* en
`for($i=0;$i<=sizeof(...))` y llamadas a `utf8_encode()`, obsoleto desde PHP 8.2.
**Corrección.** `display_errors=0` en producción y un manejador global que muestra un mensaje genérico y deja el
detalle en el log. Se corrigieron el bucle y la codificación (conexión en `utf8mb4`).
**Mapeo.** ISO 27001: A.8.28 · ASC-28 · ASVS: v5.0.0-16.5.1, v5.0.0-16.5.4, v5.0.0-13.4.2 · **Evidencia:** T23.

---

### [PNK-17] Archivos innecesarios publicables

**Severidad:** Baja · **Estado:** Corregido

Había `.DS_Store`, una carpeta `vendors/flat-icon/New folder` duplicada, `css/style.map`, JS de plantilla sin
uso y el script SQL (con credenciales en claro) junto al código. **Corrección:** se eliminaron los archivos
sobrantes. `.htaccess` con `Options -Indexes` bloquea archivos ocultos, `.sql`, `.log`, `.md` y `.git`, y deniega
el acceso a `config/`, `logs/`, `Script_BD/`, `herramientas/`, `tests/` y `docs/` para el caso de que se copie
la carpeta completa a `htdocs`. Además, `imagenes/` no ejecuta scripts.
**Mapeo.** ISO 27001: A.8.9, A.8.31 · ASC-06, ASC-26 · ASVS: v5.0.0-13.4.1, v5.0.0-13.4.3, v5.0.0-13.4.7, v5.0.0-15.2.3

---

Los hallazgos de privacidad (PRIV-01 a PRIV-05) están en `06_Privacidad_Ley19628_21719.md`.

## 5. Pruebas ejecutadas y no ejecutadas

| Ejecutado | Herramienta | Resultado / evidencia |
|---|---|---|
| Revisión manual completa del código propio (7 PHP, 2 JS, 1 SQL) | — | Este informe |
| SAST por patrones (antes y después) | `tests/sast_basico.php` | 51 → 1 (`tests/evidencia/`) |
| Pruebas funcionales de seguridad (24 casos) | `tests/pruebas_seguridad.php` | 24/24 PASA |
| Prueba en navegador (Chromium headless): carta, carrito, login, comentario con payload | Playwright | Funciona y sin violaciones de CSP (`captura_app_corregida.png`) |
| SCA manual + verificación de integridad (SHA-256/384 vs SRI oficial) | openssl | Ver PNK-13 y el SBOM |

| No ejecutado | Motivo | Próximo paso |
|---|---|---|
| DAST (p. ej. OWASP ZAP) y pentest | Sin un ambiente desplegado autorizado (§46) | Ejecutarlo en QA con autorización escrita |
| SAST con herramienta completa (Semgrep/SonarQube) | Sin acceso a sus registros en este entorno | Integrarlo al pipeline (ACC-05) |
| Pruebas sobre MySQL real | Se validó con SQLite. Las consultas son SQL estándar y parametrizadas | Smoke test en QA con MySQL 8 |
| Verificación de `.htaccess` | El servidor embebido de PHP no lo aplica | Verificarlo en Apache (checklist en el Gate 4) |

**Este informe no garantiza la ausencia de vulnerabilidades.** Cubre el código entregado y las pruebas
descritas.

## 6. Riesgo residual

Ver `08_Plan_Remediacion.md §2`. En resumen: el riesgo técnico de la aplicación baja de **Crítico** a **Bajo-Medio**.
Lo que queda depende de infraestructura (TLS, gestor de secretos, logs centralizados, MFA para cuentas
administrativas) y de que el responsable complete la parte documental de privacidad.
