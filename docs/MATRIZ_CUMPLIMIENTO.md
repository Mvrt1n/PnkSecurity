# Matriz de Cumplimiento — Tercera Evaluación

**Proyecto:** PnkSecurity · Carta Digital — INACAP La Serena
**Autores:** Martín Díaz y Sujey Ramos

Estados: **Implementado** · **Parcial** · **Pendiente** · **No aplicable (NA)**.
Criterio: no se marca *Implementado* algo que sólo está documentado o propuesto.

| # | Requerimiento (rúbrica) | Implementación realizada | Archivo / recurso | Estado | Evidencia | Observaciones |
|---|---|---|---|---|---|---|
| 1 | Frontend | Vistas EJS + estáticos, desacopladas de la lógica | `src/views/`, `public/` | Implementado | Render de `/carta/:id`, `/` | Server-side |
| 2 | Backend | Express con rutas/controladores/servicios | `src/` | Implementado | App arranca y sirve endpoints | — |
| 3 | Node.js | Runtime Node 22 | `Dockerfile`, `package.json` | Implementado | `node:22-alpine` | — |
| 4 | Express | Framework HTTP + middlewares | `src/app.js` | Implementado | Middlewares seguridad/CSRF | — |
| 5 | PostgreSQL | Esquema + datos portados a PG 16 | `db/init/01_schema.sql`, `02_seed.sql` | Implementado | Tablas y datos de la Carta | Pruebas de BD ejecutadas |
| 6 | MVC | models/views/controllers + services/middlewares | `src/models`, `src/controllers`, `src/views` | Implementado | Estructura de carpetas | — |
| 7 | Docker | Dockerfile multi-etapa + Compose | `Dockerfile`, `docker-compose*.yml` | Implementado | Build de imagen | Usuario no-root, healthcheck |
| 8 | Tres ambientes | Dev / QA / Prod con config propia | `docker-compose.override/qa/prod.yml`, `env/` | Implementado | Proyectos `-p` separados | Credenciales independientes |
| 9 | Git (rama nueva) | Rama `feature/tercera-evaluacion` | repositorio | Implementado | `git log` de la rama | Separada de main |
| 10 | AWS RDS PostgreSQL | RDS privado, cifrado, backups | `deploy/cloudformation/pnk-infra.yaml` (`Database`) | Parcial | Template validado | **Falta provisionar** (requiere tu autorización, genera costo) |
| 11 | AWS Auto Scaling | LaunchTemplate + ASG + política CPU | `pnk-infra.yaml` (`AutoScalingGroup`, `CpuScalingPolicy`) | Parcial | Template validado | Falta provisionar |
| 12 | AWS Load Balancer | ALB + Target Group + Listener | `pnk-infra.yaml` (`LoadBalancer`, `TargetGroup`, `HttpListener`) | Parcial | Template validado | Falta provisionar |
| 13 | CloudFormation (IaC) | Template YAML completo | `deploy/cloudformation/pnk-infra.yaml` | Implementado | 23 recursos, YAML válido | Mecanismo principal de IaC |
| 14 | Script despliegue App | `deploy-app.sh` (valida + `cloudformation deploy`) | `deploy/deploy-app.sh` | Implementado | `bash -n` OK | Automatiza AWS por código |
| 15 | Script despliegue BD | `deploy-db.sh` (schema/seed/rol) | `deploy/deploy-db.sh` | Implementado | `bash -n` OK | Equivale al user-data |
| 16 | Cloudflare DNS/HTTPS | Procedimiento manual documentado | `docs/MANUAL_IMPLEMENTACION.md` §14-16, `docs/DESPLIEGUE.md` | Implementado (doc) | Pasos y valores exactos | Config manual por diseño |
| 17 | IA | Servicio de recomendaciones (clave backend) | `src/services/iaService.js`, `src/controllers/iaController.js` | Implementado | Fallback local si no hay key | API key sólo backend |
| 18 | Documentación PDF | Manual 25 secciones | `docs/MANUAL_IMPLEMENTACION.md` → PDF | Implementado | PDF generado | — |
| 19 | Manual / procedimiento de implementación | Incluido en el manual | `docs/MANUAL_IMPLEMENTACION.md` | Implementado | §7-21 | — |
| 20 | Variables de entorno | Secretos vía env/params, nunca en Git | `env/*.example`, `.gitignore` | Implementado | `.env` reales ignorados | — |
| 21 | Seguridad PHP2Shell | Verificación y documentación de CVEs | `docs/SEGURIDAD_PHP2Shell.md` | Implementado | CVE-2026-109618/19 | Proyecto migrado no usa PHP |
| 22 | Pruebas de funcionamiento | Smoke + pruebas de BD | `scripts/smoke_test.js` | Parcial | BD verificada | E2E HTTP se corre en EC2/local con `npm` |
| 23 | Matriz de cumplimiento | Esta tabla | `docs/MATRIZ_CUMPLIMIENTO.md` | Implementado | Este documento | — |
| 24 | Registro de modificaciones y seguridad | Historial de commits + §23 del manual | `git log`, manual §23 | Implementado | Commits temáticos | — |

## Resumen

- **Implementado:** la totalidad del software (migración, MVC, Docker, 3 ambientes,
  IA, seguridad), la Infraestructura como Código (CloudFormation), los scripts de
  despliegue, la rama Git, la documentación y la matriz.
- **Parcial (provisión AWS real):** RDS, Auto Scaling y Load Balancer están
  **definidos y validados por código**, listos para desplegar. Falta **ejecutar**
  la provisión en la cuenta AWS, que **genera costo** y por eso requiere tu
  autorización explícita (regla del punto 11 de la rúbrica).
- **E2E HTTP:** se ejecuta en el entorno con `npm` y red (instancia EC2 o tu
  equipo), mostrando salida real; no se declara exitoso sin ejecutarse.
