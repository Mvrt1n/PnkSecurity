# Flujo de trabajo Git — PNK Carta Digital (3ª evaluación)

Repositorio dedicado a la 3ª evaluación (stack Node/Express/PostgreSQL). El proyecto
PHP de la 2ª evaluación queda en su propio repositorio como historial.

## Ramas y su relación con los 3 ambientes

| Rama | Ambiente | Propósito |
|------|----------|-----------|
| `develop` | **Desarrollo** | Integración de nuevas funcionalidades. Base del trabajo diario. |
| `qa` | **QA** | Validación/pruebas antes de producción. Recibe merges desde `develop`. |
| `main` | **Producción** | Código estable y desplegable. Recibe merges desde `qa`. |

Promoción del código: **`develop` → `qa` → `main`** (nunca al revés).

```
feature/* ──▶ develop ──▶ qa ──▶ main ──▶ despliegue (AWS)
```

## Reglas

- Cada funcionalidad nueva se trabaja en una rama `feature/<nombre>` desde `develop`.
- Se integra a `develop` con Pull Request (revisión).
- Cuando `develop` es estable → PR a `qa` → se corren pruebas/seguridad.
- Si QA aprueba → PR de `qa` a `main` → se despliega a producción.
- **Nunca** se commitean `.env` reales ni secretos (ver `.gitignore`). Solo se versionan
  `env/.env.development` (credenciales desechables de laboratorio) y los `*.example`.

## Comandos típicos

```bash
# nueva funcionalidad
git checkout develop && git pull
git checkout -b feature/mi-cambio
# ...trabajo...
git commit -am "feat: mi cambio"
git push -u origin feature/mi-cambio      # abrir PR hacia develop

# promover a QA
git checkout qa && git merge --no-ff develop && git push

# promover a producción
git checkout main && git merge --no-ff qa && git push
```

## Protección recomendada (en GitHub)
- Proteger `main` y `qa`: exigir PR + al menos una revisión, y que pasen los checks de CI
  (lint, `npm audit`, `npm run smoke`).
