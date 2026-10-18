# ---------------------------------------------------------------------------
# Imagen de la interfaz (Nuxt).
# ---------------------------------------------------------------------------

FROM node:22.14-alpine AS construccion

WORKDIR /app

COPY web/package.json web/package-lock.json* ./
RUN npm ci --no-audit --no-fund

COPY web/ ./
RUN npm run build

# ---------------------------------------------------------------------------
# Imagen final
# ---------------------------------------------------------------------------
FROM node:22.14-alpine AS final

WORKDIR /app
ENV NODE_ENV=production
ENV NITRO_PORT=3000
ENV NITRO_HOST=0.0.0.0

COPY --from=construccion /app/.output ./.output

USER node

EXPOSE 3000

# La interfaz no guarda datos de expedientes en disco: el estado vive en la
# sesión del servidor.
CMD ["node", ".output/server/index.mjs"]