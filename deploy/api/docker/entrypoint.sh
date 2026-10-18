#!/bin/sh
# ---------------------------------------------------------------------------
# Entrada del contenedor de la API.
# ---------------------------------------------------------------------------
# El punto de este script es hacer FALLAR el despliegue con un mensaje claro
# cuando algo quedó mal configurado, en vez de levantar un contenedor que
# responde con errores 500 a todas partes.
#
# Lo que comprueba:
#   1. que APP_KEY esté definida
#   2. que la API pueda conectarse a PostgreSQL
#   3. AVISA sobre el aislamiento entre estudios
#
# POR QUÉ EL 3 ES UN AVISO Y NO UN BLOQUEO
# ------------------------------------------
# Si se hiciera en cada arranque, un contenedor que arranca con la base de
# datos todavía levantándose caería en bucle. Además, el aislamiento hay que
# correrlo en cada DESPLIEGUE y antes de dar por buena una migración, no en cada
# arranque. Ver docs/04.
#
# IMPORTANTE: este script no emite ni valida nada legal. Solo comprueba que la
# infraestructura esté en pie.
# ---------------------------------------------------------------------------

set -e

cd /var/www/html

echo "[entrada] PHP $(php -v | head -n 1)"

# ---------------------------------------------------------------------------
# 1. Configuración mínima
# ---------------------------------------------------------------------------
if [ -z "${APP_KEY}" ]; then
    echo "[entrada] ERROR: APP_KEY no está definida."
    echo "[entrada] Genere una con:  docker compose run --rm api php artisan key:generate --show"
    exit 1
fi

# ---------------------------------------------------------------------------
# 2. Espera a la base de datos y aplicación de migraciones
# ---------------------------------------------------------------------------
echo "[entrada] esperando a PostgreSQL..."
intentos=0
hasta=30

while [ "$intentos" -lt "$hasta" ]; do
    if php -r 'exit(@pg_connect("host=".(getenv("DB_HOST") ?: "127.0.0.1")." port=".(getenv("DB_PORT") ?: "5432")." dbname=".getenv("DB_DATABASE")." user=".getenv("DB_USERNAME")." connect_timeout=2")) ? 0 : 1);'; then
        echo "[entrada] PostgreSQL responde."
        break
    fi

    intentos=$((intentos + 1))
    echo "[entrada] todavía no responde (intento $intentos de $hasta)"
    sleep 2
done

if [ "$intentos" -ge "$hasta" ]; then
    echo "[entrada] ERROR: PostgreSQL no respondió a tiempo."
    exit 1
fi

if [ "${ESTUDIO_CORRER_MIGRACIONES:-false}" = "true" ]; then
    echo "[entrada] aplicando migraciones..."
    php artisan migrate --force --no-interaction
    php artisan config:cache
    php artisan route:cache
fi

# ---------------------------------------------------------------------------
# 3. Aviso sobre el aislamiento entre estudios (no bloqueante)
# ---------------------------------------------------------------------------
echo "[entrada] revisando el aislamiento entre estudios..."
if php artisan estudio:verificar-aislamiento; then
    echo "[entrada] aislamiento verificado."
else
    echo "[entrada] AVISO: el aislamiento entre estudios NO está bien puesto."
    echo "[entrada] La aplicación puede arrancar, pero no debe recibir personas hasta corregirlo."
    echo "[entrada] Si un estudio ve expedientes de otro, hay datos de clientes y causas expuestos."
    echo "[entrada] Corrija antes de continuar. Ver docs/03."
fi

# ---------------------------------------------------------------------------
# 4. Aviso sobre los plazos, sin tocar la base de datos
# ---------------------------------------------------------------------------
# Esto NO calcula ningún plazo legal: solo recuerda que las fechas las escribe
# el abogado y que el sistema solo las vigila.
echo "[entrada] Este sistema no calcula plazos legales ni acredita su cumplimiento."
echo "[entrada] Las fechas las ingressa cada abogado. El sistema solo las vigila."

exec "$@"