# ---- Base ------------------------------------------------------------------
FROM node:22-alpine AS base
WORKDIR /app
ENV NODE_ENV=production
# tini para manejo correcto de señales (apagado ordenado)
RUN apk add --no-cache tini

# ---- Dependencias ----------------------------------------------------------
FROM base AS deps
COPY package.json package-lock.json* ./
# Si hay lockfile usa npm ci (reproducible); si no, npm install
RUN if [ -f package-lock.json ]; then npm ci --omit=dev; else npm install --omit=dev; fi

# ---- Runtime ---------------------------------------------------------------
FROM base AS runtime
ENV PORT=3000
COPY --from=deps /app/node_modules ./node_modules
COPY package.json ./
COPY src ./src
COPY public ./public
COPY scripts ./scripts

# Ejecutar como usuario sin privilegios (el 'node' ya existe en la imagen)
USER node
EXPOSE 3000

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
  CMD node scripts/healthcheck.js

ENTRYPOINT ["/sbin/tini","--"]
CMD ["node","src/server.js"]
