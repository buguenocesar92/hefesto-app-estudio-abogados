# 04 - Instalación

Cómo se levanta la aplicación.

> **Antes de seguir: en el entorno donde se escribió esto no había Docker, ni PHP,
> ni Composer.** El código está escrito y **sin ejecutar**. Ver
> [10](10-PRUEBAS-Y-ESTADO.md), que separa lo probado de lo no probado sin
> adornos.

---

## 1. Requisitos

| Componente | Versión fijada | Nota |
|---|---|---|
| Docker | 24 o superior | |
| Docker Compose | v2 | El `docker compose`, sin guion. |
| Node.js | 22.14 | Solo para construir la interfaz. |
| PHP | 8.3 | Solo si se corre sin contenedor. |

**Todas las versiones de imagen están fijadas a propósito.** Una imagen que se
mueve sola hace que una instalación que funcionaba ayer falle hoy sin que nadie
haya tocado nada.

---

## 2. Puesta en marcha en desarrollo

```bash
cd apps/estudio-abogados

# 2.1 Copiar la plantilla de variables. NO contiene ningún valor real.
cp .env.example .env

# 2.2 Generar los secretos.
#     APP_KEY:
php -r "echo base64_encode(random_bytes(48)), PHP_EOL;"
#     AUTHENTIK_SECRET_KEY:
openssl rand -base64 60 | tr -d '\n'; echo
#     AUTHENTIK_BOOTSTRAP__TOKEN:
openssl rand -hex 32
#     Y una contraseña para DB_PASSWORD, otra para
#     AUTHENTIK_POSTGRESQL__PASSWORD y otra para
#     AUTHENTIK_BOOTSTRAP__PASSWORD.

# 2.3 Poner esos valores en el .env.

# 2.4 Levantar.
docker compose -f deploy/docker-compose.dev.yml --env-file .env up -d

# 2.5 Ver qué pasó.
docker compose -f deploy/docker-compose.dev.yml --env-file .env logs -f api
```

### Qué expone el entorno de desarrollo

| Servicio | Dirección |
|---|---|
| Interfaz | http://localhost:3000 |
| API | http://localhost:8000 |
| PostgreSQL | localhost:5432 |
| Authentik | http://localhost:9000 |

Pública puertos para poder abrir un cliente de base de datos y ver los registros
sin entrar por SSH. **En producción no se hace.**

---

## 3. Migraciones y semilla

Dentro del contenedor:

```bash
docker compose -f deploy/docker-compose.dev.yml --env-file .env \
    exec api php artisan migrate --force

docker compose -f deploy/docker-compose.dev.yml --env-file .env \
    exec api php artisan db:seed --force
```

La semilla carga un estudio de ejemplo, con expedientes, plazos, documentos y
horas registradas. Los plazos de la semilla llevan **fechas escritas a mano** y
un texto de respaldo que corresponde a cada fecha: no hay ningún cálculo
involucrado. Ver `EstudioDemoSeeder`.

### Crear el primer usuario

El proveedor de identidad entrega el `subject`. Hay que dar de alta la cuenta:

```bash
docker compose -f deploy/docker-compose.dev.yml --env-file .env \
    exec api php artisan tinker
```

```php
App\Models\Usuario::create([
    'tenant_id'  => App\Models\Estudio::where('slug', 'estudio-demo')->value('id'),
    'subject'    => 'authentik|<el sub del token que entrega Authentik>',
    'correo'     => 'socio@estudio-demo.cl',
    'nombre'     => 'Nombre Apellido',
    'rol'        => 'socio',
]);
```

**Una cuenta que no está dada de alta no entra.** El sistema no crea cuentas
solas: un estudio abierto al mundo no es un estudio.

---

## 4. Verificar el aislamiento ANTES de usar el sistema

Este comando **no es un aviso: es una condición de despliegue**. Sale con
código distinto de cero si algo está mal.

```bash
docker compose -f deploy/docker-compose.dev.yml --env-file .env \
    exec api php artisan estudio:verificar-aislamiento
```

Comprueba que el rol de la base no sea superusuario ni tenga `BYPASSRLS`, que
todas las tablas tengan RLS activado y forzado con su política, y que la función
de contexto siga fallando cerrada.

**Si falla, no recibe personas.** Ver [03](03-MULTITENANCY-Y-ROLES.md).

---

## 5. Correr el vigilante de plazos a mano

El vigilante corre solo una vez al día. Para probarlo:

```bash
# Todos los estudios activos.
docker compose -f deploy/docker-compose.dev.yml --env-file .env \
    exec api php artisan estudio:vigilar-plazos

# Un estudio, con una fecha de referencia.
docker compose -f deploy/docker-compose.dev.yml --env-file .env \
    exec api php artisan estudio:vigilar-plazos --estudio=estudio-demo --fecha=2026-10-17
```

Dice cuántos plazos revisó, cuántos marcó y cuántos no tocó porque no tienen
días de aviso configurados.

---

## 6. Instalación en producción

### 6.1 Preparar

```bash
cp .env.example .env.prod
```

Y rellenar **todo**, con secretos generados nuevos. Ningún valor de
`.env.example` es real.

Además hay que definir, en `.env.prod`:

```bash
API_IMAGE=hefesto/estudio-abogados-api:1.0.0
WEB_IMAGE=hefesto/estudio-abogados-web:1.0.0
```

### 6.2 Construir y subir las imágenes

```bash
docker build -f deploy/api/Dockerfile -t hefesto/estudio-abogados-api:1.0.0 .
docker build -f deploy/web/Dockerfile -t hefesto/estudio-abogados-web:1.0.0 .

docker push hefesto/estudio-abogados-api:1.0.0
docker push hefesto/estudio-abogados-web:1.0.0
```

El compose de producción usa imágenes construidas, no `build`. Así el despliegue
es reproducible y no depende de que el código fuente esté en el servidor.

### 6.3 Levantar

```bash
docker compose -f deploy/docker-compose.prod.yml --env-file .env.prod up -d
```

### 6.4 El orden importa

```bash
# 1. Verificar el aislamiento ANTES de migrar.
docker compose -f deploy/docker-compose.prod.yml --env-file .env.prod \
    run --rm api php artisan estudio:verificar-aislamiento

# 2. Migrar.
docker compose -f deploy/docker-compose.prod.yml --env-file .env.prod \
    run --rm api php artisan migrate --force

# 3. Verificar OTRA VEZ, después de migrar.
docker compose -f deploy/docker-compose.prod.yml --env-file .env.prod \
    run --rm api php artisan estudio:verificar-aislamiento

# 4. Optimizar.
docker compose -f deploy/docker-compose.prod.yml --env-file .env.prod \
    run --rm api sh -c "php artisan config:cache && php artisan route:cache"
```

Si el paso 3 falla, la migración dejó algo sin política: **no siga**. Vuelva a
revisar el paso 1.

---

## 7. Cómo corre el vigilante en producción

En un contenedor aparte, el servicio `vigilante` del compose de producción:

```yaml
entrypoint: ["/bin/sh", "-c"]
command:
  - >
    while true; do
      php artisan schedule:run --no-interaction;
      sleep 300;
    done
```

Está aparte y no dentro de la API porque es una tarea de mantenimiento y no
debería competir por recursos con las peticiones de personas.

---

## 8. Problemas frecuentes

| Síntoma | Causa probable | Qué hacer |
|---|---|---|
| "No hay contexto de estudio en la conexión" | Una consulta se ejecutó fuera del middleware | Revisar que la ruta pase por `ResolverTenant`. Ver [03](03-MULTITENANCY-Y-ROLES.md). |
| `estudio:verificar-aislamiento` dice que el rol es superusuario | El compose creó el rol como superusuario | Usar un rol con `NOSUPERUSER` y sin `BYPASSRLS`. |
| No se marca ningún plazo | El estudio no configuró días de aviso | Configurarlos en la pantalla de configuración, o dejar que no se marque. Es el comportamiento correcto. |
| "El texto de respaldo de un plazo no se puede modificar" | El disparador de la base funcionando | Es lo esperado. Anule el plazo y registre otro. Ver [02](02-MODELO-DE-DATOS.md). |
| La colisión de versión aparece mucho | Se están subiendo copias con el número de versión mal puesto | Revisar el procedimiento de recepción de documentos. Ver [01](01-ARQUITECTURA.md). |
| El documento no se descarga | La ruta no existe o apunta fuera del volumen | Revisar `DOCUMENTOS_PATH` y los respaldos. Ver [08](08-RESPALDOS.md). |

---

## 9. Límites de tamaño

| Elemento | Límite | Dónde se configura |
|---|---|---|
| Archivo de documento | 50 MB (51200 KB) | `RecibirDocumentoRequest` |
| Subida por petición | 64 MB | `php.ini` y `nginx.conf` |
| Tarea imputada | 1440 minutos (24 horas) | `TareaController::store` |
| Texto de respaldo | 4000 caracteres | `RegistrarPlazoRequest` |
| Partes por expediente | 40 | `AbrirExpedienteRequest` |

El límite de 24 horas no es una regla del estudio: es para que un error de
teipeo —un año en vez de un día— no se cuele como si fuera real.

---

Siguiente: [05 Configuración](05-CONFIGURACION.md).