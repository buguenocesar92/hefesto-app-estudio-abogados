# 08 - Respaldos

Un respaldo que no se ha **restaurado** no es un respaldo: es una carpeta con
copias que nadie miró.

---

## 1. Qué hay que respaldar, y por qué

| Qué | Dónde | Por qué importa |
|---|---|---|
| **Base de datos** | Volumen `postgres-*-datos` | Todo lo estructurado: expedientes, plazos, clientes, documentos, horas, bitácora. |
| **Documentos** | Volumen `documentos-*` | Los archivos. La base solo tiene la huella y la ruta. |
| **Configuración** | `.env.prod` | Días de aviso, clave de la aplicación, rutas. |
| **Imágenes** | Registro de contenedores | Para reconstruir la misma versión. |
| **Base de Authentik** | Volumen `authentik-*-postgres-datos` | Identidades y aplicaciones OAuth. |

### Por qué los documentos son la parte crítica

En la base hay un `sha256` de cada documento y una ruta. **Si se pierde el
archivo y se conserva la base, lo que queda es un resumen criptográfico**: se
puede probar que un documento no cambió, pero no se puede abrir.

Y la razón por la que no se puede "regenerar" es que **el sistema nunca tuvo el
documento**: llegó desde el correo o desde el papel, y se copió. No hay de dónde
recuperarlo.

---

## 2. Respaldo de la base de datos

```bash
docker compose -f deploy/docker-compose.prod.yml --env-file .env.prod \
    exec -T postgres \
    pg_dump --format=custom --file=- --no-owner --no-acl \
    estudio_abogados \
    > respaldos/estudio-$(date +%Y%m%d-%H%M).dump
```

O en el host, con el puerto publicado (solo en desarrollo):

```bash
pg_dump -h localhost -p 5432 -U estudio_app -d estudio_abogados \
    --format=custom --no-owner --no-acl \
    -f respaldos/estudio-$(date +%Y%m%d-%H%M).dump
```

### Formato `custom` y no SQL plano

El formato comprimido de PostgreSQL (`-Fc`) permite restaurar tablas
seleccionadas y es más rápido de comprimir. Un `pg_dump` plano de una base con
millones de horas imputadas produce un archivo enorme.

### Nota sobre la Row Level Security

`pg_dump` incluye los **datos de todos los estudios**, no solo del que se está
respaldando: es un respaldo del servidor, no de un tenant.

Si se necesita un respaldo de un solo estudio, hay que filtrar por
`tenant_id` y hacerlo con una consulta, no con `pg_dump`. **Ese respaldo no
restituye la base completa** y solo sirve para análisis.

---

## 3. Respaldo de los documentos

```bash
docker run --rm \
    -v estudio-abogados-prod_documentos-prod:/origen:ro \
    -v "$PWD/respaldos/documentos":/destino \
    alpine:3.21 \
    tar czf /destino/documentos-$(date +%Y%m%d-%H%M).tar.gz -C /origen .
```

Los documentos **cambian poco**: se puede respaldar incrementalmente con
`rsync`, o cada certain día, según lo que el estudio decida.

> Los documentos son datos personales protegidos por la Ley 19.628. El respaldo
> tiene la misma sensibilidad que la base, y debe estar cifrado en el destino.

---

## 4. Restaurar

### La prueba que hay que hacer

```bash
# 1. Base de datos.
docker compose -f deploy/docker-compose.prod.yml --env-file .env.prod \
    exec -T postgres \
    pg_restore --clean --if-exists --no-owner --no-acl \
    --dbname=estudio_abogados_prueba < respaldos/estudio-20261017-0300.dump

# 2. Verificar el aislamiento EN LA BASE RESTAURADA.
docker compose -f deploy/docker-compose.prod.yml --env-file .env.prod \
    run --rm -e DB_DATABASE=estudio_abogados_prueba api \
    php artisan estudio:verificar-aislamiento

# 3. Documentos, al volumen de prueba.
```

### Lo que hay que comprobar después de restaurar

- [ ] El conteo de expedientes, plazos, documentos y clientes coincide.
- [ ] `estudio:verificar-aislamiento` sale con código 0.
- [ ] Un expediente se abre y muestra sus plazos y sus documentos.
- [ ] Los disparadores siguen activos: un `UPDATE` de un `texto_respaldo` falla.
- [ ] La bitácora sigue sin admitir `UPDATE` ni `DELETE`.
- [ ] Un documento se descarga y su huella coincide con la de la base.

**Si alguno de estos falla, el respaldo no sirve.**

---

## 5. Periodicidad

| Qué | Cada cuánto | Cuánto se guarda |
|---|---|---|
| Base de datos | Todos los días | 30 diarios + 12 mensuales |
| Documentos | Cada 3 días, o al cerrar un expediente | 90 días |
| `.env.prod` | Cada vez que cambia | 12 |
| Imágenes | Cada versión | Las que se usan en producción |

**Las copias antiguas no se borran automáticamente sin revisarlas.** Un respaldo
del que nadie comprobó si se puede leer es un respaldo que no existe.

---

## 6. Dónde se guarda el respaldo

- **Cifrado en reposo.** Contiene nombres de clientes y causas.
- **En un lugar distinto al del servidor.** Si el respaldo está en el mismo disco
  que la base, no protege del incendio.
- **Con control de acceso:** solo quien tenga el rol de administrador.
- **Con copia de las copias.** Un respaldo en un solo disco no es un respaldo.

> La Ley 19.628 no distingue entre el dato y su copia. Un respaldo sin cifrar es
> una segunda base de datos de datos personales, sin las protecciones que tiene
> la original.

---

## 7. Restauración ante desastres

El orden importa.

1. **Levantar PostgreSQL** y restaurar la base.
2. **Verificar el aislamiento** antes de que entre nadie.
3. **Levantar la API** y revisar los registros.
4. **Montar el volumen de documentos.**
5. **Comprobar** que las huellas de los documentos coinciden con las de la base.
6. **Recién entonces**, levantar la interfaz al público.

> Un expediente sin su documento es un expediente incompleto, y un sistema que
> muestra "vigente" sobre un archivo que no está es peor que uno que muestra el
> hueco. Si hay discrepancias entre las huellas, **esa es la información que hay
> que mirar primero**: significa que los documentos cambiaron desde el respaldo.

---

## 8. Lo que no se respalda, y por qué

| No se respalda | Por qué |
|---|---|
| El estado de `vendor/` y `node_modules/` | Se reconstruyen desde `composer.lock` y `package-lock.json`. |
| `storage/logs/` | Los registros no son evidencia; la bitácora de la base sí. |
| `storage/framework/cache/` | Caché. |
| Redis | Caché y colas. El trabajo pendiente se vuelve a encolar. |
| Contenedores | Se reconstruyen desde el `Dockerfile`. |

---

## 9. Lo que falta

- **No hay respaldo automático configurado.** Hay instrucciones, no un
  `cron`.
- **No hay copia en frío.** El respaldo vive en el mismo servidor que la base.
- **No hay verificación automática de que el respaldo se pueda restaurar.**
- **No hay política de retención.** Cuánto tiempo se guarda un expediente
  archivado es una decisión pendiente del estudio, y la Ley 19.628 pide que los
  datos no se conserven para siempre.

---

Siguiente: [09 API](09-API.md).