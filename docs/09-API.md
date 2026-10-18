# 09 - API

Prefijo: `/api/v1`. Todas las rutas de negocio viven en el grupo `web`, que
aplica CSRF y el middleware `ResolverTenant`.

---

## 1. Convenciones

| Tema | Decisión |
|---|---|
| Identificadores | UUID en todo. Nunca enteros. |
| Fechas | `AAAA-MM-DD`. |
| Marcas de tiempo | ISO 8601. |
| Respuesta de error | `{ "message": "..." }` con el código HTTP correspondiente. |
| Denegado | **404, no 403.** Un 403 confirmaría que el recurso existe. |
| Paginación | `?page=` y `?per_page=`. |
| Idioma de los mensajes | Español de Chile. |

---

## 2. Identidad

| Método | Ruta | Descripción |
|---|---|---|
| `GET` | `/auth/estudios` | Estudios disponibles, desde la vista pública. **Sin sesión.** |
| `GET` | `/auth/entrar/{slug}` | Redirige al proveedor de identidad. |
| `GET` | `/auth/callback` | Callback del proveedor. |
| `POST` | `/auth/salir` | Cierra sesión. |
| `GET` | `/auth/yo` | Usuario autenticado y su rol. |

### `GET /auth/estudios`

Devuelve **tres columnas** (`slug`, `nombre`) de los estudios activos. No expone
correos, ni nombres de abogados, ni nada de clientes o causas.

> **Riesgo residual:** un `slug` se puede enumerar antes de autenticarse, porque
> es público. Un atacante puede descubrir que el Estudio Bravo usa esta
> aplicación. No puede ver más que su nombre. Ver
> [03](03-MULTITENANCY-Y-ROLES.md).

### `GET /auth/callback`

Si la verificación del token falla, **lanza excepción**. No se crea una sesión a
medias ni se acepta un token sin comprobar.

---

## 3. Tablero

| Método | Ruta | Descripción |
|---|---|---|
| `GET` | `/tablero` | Expedientes de la cartera, con el conteo de alertas. |
| `GET` | `/plazos` | Plazos de la cartera, con el nivel ya evaluado. |

La respuesta de `/plazos` trae `nivel` y `resumen`. El resumen **nunca** dice
"plazo legal": dice *"la fecha que se registró ya pasó"* o *"faltan 5 días para la
fecha registrada"*.

---

## 4. Expedientes

| Método | Ruta | Descripción |
|---|---|---|
| `GET` | `/expedientes` | Lista, filtrada por cartera. Paginado. |
| `POST` | `/expedientes` | Abre un expediente. |
| `GET` | `/expedientes/{id}` | Detalle: plazos, documentos, partes, horas, incidencias. **Auditado.** |
| `PUT` | `/expedientes/{id}` | Edita. |
| `POST` | `/expedientes/{id}/anular` | **Anula**, con motivo. No borra. |

### `POST /expedientes`

```json
{
  "titulo": "Despido sin carta de aviso",
  "numero_causa": "C-1234-2026-4521",
  "materia_id": "uuid",
  "abogado_responsable_id": "uuid",
  "cliente_id": "uuid",
  "fecha_inicio": "2026-04-08",
  "detalle": "texto libre",
  "partes": [
    { "cliente_id": "uuid", "rol": "demandante", "es_externo": false },
    { "cliente_id": "uuid", "rol": "demandado",  "es_externo": true  }
  ]
}
```

El `folio` lo genera la aplicación y no se acepta desde el formulario. El
**número de causa lo escribe la persona**: no se genera, nunca.

---

## 5. Plazos

| Método | Ruta | Descripción |
|---|---|---|
| `GET` | `/expedientes/{id}/plazos` | Plazos del expediente, con nivel y color. |
| `POST` | `/expedientes/{id}/plazos` | Registra un plazo. |
| `GET` | `/expedientes/{id}/plazos/{plazo}` | Un plazo. |
| `POST` | `/expedientes/{id}/plazos/{plazo}/cumplir` | Marca como cumplido. |

### `POST /expedientes/{id}/plazos`

```json
{
  "titulo": "Presentar prueba documental",
  "fecha_vencimiento": "2026-11-14",
  "texto_respaldo": "Fecha fijada por el tribunal en la resolución del 2026-10-30."
}
```

**Los dos últimos campos son obligatorios.** No hay campo de días de plazo.

| Error | Causa |
|---|---|
| `422` *"Escriba la fecha de vencimiento. El sistema no la calcula."* | Falta la fecha. |
| `422` *"Escriba por qué vence en esa fecha. Ese texto no se podrá modificar después."* | Falta o es muy corto el respaldo. |

Al guardar, el respaldo se copia al expediente como **texto inmutable** y los
días de aviso del estudio se copian al plazo.

---

## 6. Documentos

| Método | Ruta | Descripción |
|---|---|---|
| `GET` | `/expedientes/{id}/documentos` | Documentos con sus versiones. |
| `POST` | `/expedientes/{id}/documentos` | **Recibe una copia y detecta la colisión.** |
| `GET` | `/expedientes/{id}/documentos/{documento}/descargar` | Descarga. **Auditado.** |
| `GET` | `/expedientes/{id}/incidencias` | Incidencias documentales. |
| `POST` | `/incidencias/{id}/revisar` | Marca una incidencia como revisada. |

### `POST /expedientes/{id}/documentos`

`multipart/form-data`:

| Campo | Regla | Nota |
|---|---|---|
| `tipo_documento_id` | opcional, UUID existente | |
| `clave_natural` | **obligatoria**, máx. 160 | Cómo identifica el estudio a este documento. |
| `titulo` | **obligatoria**, máx. 200 | |
| `version_declarada` | **obligatoria**, entero 1–9999 | |
| `fecha_documento` | opcional, `AAAA-MM-DD` | |
| `archivo` | **obligatoria**, máx. 50 MB | |
| `huella_sha256` | **se elimina del formulario** | La calcula el servidor. |

### La respuesta, según lo que pasó

| Código | `resultado` | Qué pasó |
|---|---|---|
| `201` | `nuevo` | Primera vez que llega este documento. |
| `201` | `version_nueva` | Llegó una versión posterior. |
| `200` | `duplicado_exacto` | Es la misma copia. No se agregó nada. |
| `200` | `colision_version` | **Contenido distinto con versión que no corresponde.** |
| `200` | `nuevo` + incidencia | Se guardó, pero el mismo contenido ya estaba en otra causa. |

La respuesta trae siempre:

```json
{
  "resultado": "colision_version",
  "resultado_legible": "Colisión de versión",
  "explicacion": "La copia difiere de la versión 1 vigente (huella 4f2a1c9e77b0 contra 8b3d5e1a2f60) pero trae la versión 1. Un documento no cambia de contenido sin cambiar de versión, así que hay dos copias distintas del mismo documento y el sistema no sabe cuál es la buena. NO se sobrescribió nada: revise el archivador físico y decida.",
  "se_guardo_alguna_version": false,
  "documento": { "...": "..." },
  "incidencia": { "severidad": "alta", "...": "..." }
}
```

> El código de respuesta dice si se guardó algo: `201` cuando se agregó una
> versión, `200` cuando no se agregó nada pero quedó constancia. La pantalla no
> puede mentir en ninguno de los dos casos.

---

## 7. Clientes y partes

| Método | Ruta | Descripción |
|---|---|---|
| `GET` | `/clientes` | Lista, filtrada por cartera. |
| `POST` | `/clientes` | Alta. |
| `GET` | `/clientes/{id}` | Detalle con sus expedientes y roles. |
| `PUT` | `/clientes/{id}` | Edita. |
| `POST` | `/expedientes/{id}/partes/{cliente}` | **Vincula una persona a una causa, con su rol.** |

```
POST /expedientes/{id}/partes/{cliente}

{
  "rol": "demandado",
  "es_externo": true,
  "notas": "Contraparte."
}
```

El rol se pide **acá**, en la relación. La misma persona puede quedar como
`demandado` en una causa y `demandante` en otra.

---

## 8. Tareas y horas

| Método | Ruta | Descripción |
|---|---|---|
| `GET` | `/expedientes/{id}/tareas` | Horas del expediente. |
| `POST` | `/expedientes/{id}/tareas` | Registra horas. |
| `GET` | `/expedientes/{id}/tareas/resumen` | Totales y horas no cobradas. |
| `POST` | `/tareas/{id}/facturar` | Registra lo ya facturado por el estudio. |

### `GET /expedientes/{id}/tareas/resumen`

```json
{
  "minutos_imputados": 650,
  "horas_imputadas": 10.83,
  "minutos_facturables": 600,
  "minutos_facturados": 240,
  "horas_no_cobradas": 6.0
}
```

> `/facturar` **no emite nada**. Registra lo que el estudio ya emitió por su
> cuenta. Esta aplicación no timbra ni factura documentos tributarios.

---

## 9. Configuración

| Método | Ruta | Descripción |
|---|---|---|
| `GET` | `/configuracion` | Días de aviso, moneda, catálogos. **Solo administrador.** |
| `PUT` | `/configuracion` | Cambia los días de aviso. |
| `GET` | `/estudio` | Identidad del estudio. |

```json
{ "dias_alerta_plazo": 7, "dias_alerta_critica": 2 }
```

Para dejar de marcar: `{ "dias_alerta_plazo": null, "dias_alerta_critica": null }`.

Si se manda la ventana crítica sin la de aviso, la API responde `422`: una
ventana crítica sin ventana de aviso no tiene sentido y no se deja guardar.

---

## 10. Salud

```
GET /api/salud

{ "estado": "ok", "nota": "Este sistema no calcula plazos legales ni interpreta la ley." }
```

Sin sesión y sin transacción. Si tocara una tabla protegida, la política RLS
lanzaría excepción: el aislamiento falla cerrado.

---

## 11. LAS RUTAS QUE NO EXISTEN

Lo más importante de este documento.

### No hay ninguna ruta `DELETE` en toda la aplicación

Ni expedientes, ni clientes, ni documentos, ni versiones, ni plazos, ni tareas.

```
DELETE /expedientes/{id}                       NO EXISTE
DELETE /expedientes/{id}/documentos/{doc}      NO EXISTE
DELETE /documentos/{id}/versiones/{v}          NO EXISTE
DELETE /expedientes/{id}/plazos/{plazo}        NO EXISTE
DELETE /clientes/{id}                          NO EXISTE
```

Y no hay ningún método `destroy()` en ningún controlador.

**Para qué:**

| En vez de | Se usa |
|---|---|
| Borrar un expediente | `POST /expedientes/{id}/anular`, con motivo. |
| Borrar un documento mal incorporado | Anularlo, con su motivo. |
| Borrar un plazo que se pasó | Marcarlo cumplido. |

La razón es la misma en los tres casos: en un estudio, la pregunta *"¿qué versión
del contrato tenía en marzo?"* tiene que tener respuesta, y la pregunta *"¿por
qué este documento ya no está?"* se responde con una anotación de anulación, no
con un hueco.

> **Una prueba vigila que esas rutas no aparezcan.** Es la única forma de revisar
> una ausencia: `api/tests/Feature/Documentos/DocumentosNoSeBoranTest.php`.

### No hay ruta para cambiar el respaldo ni la fecha de un plazo

```
PUT /expedientes/{id}/plazos/{plazo}                       NO EXISTE
POST /expedientes/{id}/plazos/{plazo}/corregir             NO EXISTE
```

El texto de respaldo y la fecha de vencimiento son inmutables, y la garantía está
en un **disparador de la base de datos**, no en el código. Si la fecha estaba
mal, se anula el plazo y se registra uno nuevo: quedan los dos, con sus
respaldos.

### No hay ruta que genere números de causa

El `folio` interno sí lo genera la aplicación, porque no significa nada fuera de
acá. El **número de causa no**, porque lo pone el tribunal.

### No hay ruta que emita documentos tributarios

No hay emisión, ni timbra, ni envío. Hay un registro de lo que el estudio ya
emitió por su cuenta.

### No hay ruta que calcule plazos

En ninguna parte de la API hay un parámetro que derive una fecha de otra, ni una
cantidad de días que se multiplique. Los únicos números de días que hay son de
alarma interna, y sirven para decidir **cuándo avisar**, nunca para derivar una
fecha.

---

Siguiente: [10 Pruebas y estado](10-PRUEBAS-Y-ESTADO.md).