# Threat Model (STRIDE) — PNK Security

## 1. Diagrama de flujo de datos y fronteras de confianza

```text
            FB1: Internet (no confiable)                 FB2: Servidor web            FB3: Datos
 ┌───────────────────────┐   HTTP(S)   ┌──────────────────────────────────┐   PDO    ┌──────────────┐
 │ Comensal (anónimo)    │────────────▶│ Apache + PHP                     │─────────▶│ MySQL        │
 │ Usuario registrado    │◀────────────│  index.php / mostrar_carrito.php │◀─────────│ pnk_security │
 │ Atacante              │  HTML/JSON  │  carrito.php (AJAX)              │ pnk_app  │ (usuario de  │
 └──────────┬────────────┘             │  grcomentarios.php               │ (mín.    │  mínimo      │
            │                          │  setup/procesalogin.php          │ privil.) │  privilegio) │
            │ IP, user-agent           │  setup/cerrar_sesion.php         │          └──────────────┘
            ▼                          │  Sesión PHP (archivos)           │
 ┌───────────────────────┐             │  logs/security.log (fuera webroot)│
 │ Google Fonts (tercero,│             │  config/config.local.php (secretos)│
 │ posible extranjero)   │             └──────────────────────────────────┘
 └───────────────────────┘                  ▲ CLI (administrador): herramientas/*.php
```

**Activos:** credenciales (hashes), correos, sesiones, comentarios, integridad de la carta y los precios,
disponibilidad del servicio y logs.

## 2. Amenazas (STRIDE)

| ID | STRIDE | Amenaza | Componente | Riesgo v1 | Control v2 (ASC / ASVS v5.0.0) | Riesgo residual |
|---|---|---|---|---|---|---|
| TM-01 | S | Bypass de login por SQLi | procesalogin | Crítico | ASC-10 / 1.2.4 | Bajo |
| TM-02 | S | Fuerza bruta / credential stuffing | procesalogin | Alto | ASC-07 / 6.3.1 | Medio (sin MFA) |
| TM-03 | S | Fijación o robo de sesión | sesiones | Alto | ASC-09 / 7.2.4, 3.3.4 | Medio hasta tener TLS |
| TM-04 | S | Suplantación del autor de un comentario | grcomentarios | Alto | ASC-08 / 8.2.3 | Bajo |
| TM-05 | T | Inyección SQL que modifica datos | todos los endpoints | Crítico | ASC-10 / 1.2.4 + `pnk_app` sin UPDATE en la carta | Bajo |
| TM-06 | T | CSRF (comentarios, carrito, login/logout) | formularios | Medio | ASC-09 / 3.5.1 | Bajo |
| TM-07 | T | Manipulación del precio en el carrito | carrito | Bajo | Precio siempre desde la BD | Bajo |
| TM-08 | R | Imposibilidad de investigar incidentes | — | Alto | ASC-27 / 16.3.x | Medio (logs locales) |
| TM-09 | I | Extracción de la BD por SQLi | index, carrito | Crítico | ASC-10 / 1.2.4 | Bajo |
| TM-10 | I | Contraseñas en claro | BD | Alto | ASC-12 / 11.4.2 | Bajo |
| TM-11 | I | XSS almacenado o reflejado que roba la sesión | index, mostrar_carrito | Alto | ASC-10 / 1.2.1, 3.4.3; HttpOnly | Bajo |
| TM-12 | I | Errores con rutas o SQL | todos | Medio | ASC-28 / 16.5.1 | Bajo |
| TM-13 | I | Lectura de config, logs o scripts SQL por HTTP | despliegue | Alto | `.htaccess`, fuera del webroot / 13.4.x | Bajo-Medio (verificar en Apache) |
| TM-14 | I | Sniffing sin TLS | red | Alto | ASC-06 / 12.2.1 | **Alto hasta tener TLS (EXC-01)** |
| TM-15 | I | Transferencia de IP a terceros | Google Fonts | Bajo | PRIV-04 | Bajo-Medio |
| TM-16 | D | Bloqueo masivo de cuentas por el lockout por IP | procesalogin | — (introducido por el control) | Límite por IP (30) más alto que por cuenta (5) | Bajo |
| TM-17 | D | Inflado de sesiones o carrito | carrito | Bajo | Máximo 50 items | Bajo |
| TM-18 | E | SQLi ejecutada como `root` | setup | Crítico | ASC-13 / 13.2.2, 13.2.3 | Bajo |
| TM-19 | E | Comentar sin autenticarse / IDOR al eliminar | grcomentarios | Alto | ASC-08 / 8.2.1, 8.2.2 | Bajo |
| TM-20 | E | Explotación de librerías vulnerables | frontend | Medio | ASC-15 / 15.2.1 | Bajo |

## 3. Riesgos residuales registrados

TM-02 (sin MFA), TM-03 y TM-14 (dependen de TLS), TM-08 (sin SIEM) y TM-15 (fuentes de terceros). Tratamiento en
`08_Plan_Remediacion.md`.

**Mapeo:** ISO 27001 A.5.8, A.8.25, A.8.26, A.8.27 · ASC-04, ASC-05, ASC-06 · ASVS V15 y capítulos aplicables.
