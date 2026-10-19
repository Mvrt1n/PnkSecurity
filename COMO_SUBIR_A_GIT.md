# Cómo subir este proyecto a GitHub/GitLab

Este archivo ya es un repositorio Git con dos ramas:
- `main`    → proyecto original (código vulnerable)
- `mejoras` → versión corregida + informe + pruebas

## Pasos (desde esta carpeta descomprimida)

1. Crea un repositorio VACÍO en GitHub/GitLab (sin README).
2. En una terminal, dentro de esta carpeta:

```bash
git remote add origin https://github.com/TU_USUARIO/pnk-security.git
git push -u origin main
git push origin mejoras
```

3. Listo: en GitHub verás las dos ramas. La rama `mejoras` es la de las correcciones.

> Si Git te pide identidad: 
> git config user.name "Martín Díaz"
> git config user.email "martin.diaz24@inacapmail.cl"
