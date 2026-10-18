# 02 - Modelo de datos

Dieciséis tablas. Este documento explica qué es cada una y, más importante,
**qué NO está en ninguna**.

---

## 1. El mapa

```
estudios (sin RLS)
  │
  ├── usuarios
  ├── abogados
  ├── configuracion_estudio          ← los días de aviso. NO plazos legales
  ├── materias
  ├── tipos_documento
  ├── estados_expediente
  │
  ├── clientes
  │     │
  │     └── expediente_partes        ← EL ROL DE LA CAUSA ESTÁ AQUÍ
  │           │
  ├── expedientes  ◄── EL CENTRO
  │     │
  │     ├── expediente_partes
  │     ├── plazos                   ← fecha escrita + respaldo inmutable
  │     ├── documentos
  │     │     ├── documento_versiones
  │     │     └── incidencias_documentales
  │     ├── tareas
  │     └── incidencias_documentales
  │
  └── bitacora_accesos               ← solo agregar

sessions (sin RLS)
```

---

## 2. Tablas con datos de negocio

Todas llevan `tenant_id` y todas tienen Row Level Security, salvo `estudios` y
`sessions`, que se explica más abajo.

| Tabla | Qué guarda | Aparece en `docs/03` |
|---|---|---|
| `usuarios` | Persona que entra, con su rol en el estudio | sí |
| `abogados` | Ficha del abogado, con su cuenta y su cartera | sí |
| `configuracion_estudio` | Días de aviso, zona horaria, moneda | sí |
| `materias` | Catálogo del estudio | sí |
| `tipos_documento` | Catálogo del estudio | sí |
| `estados_expediente` | Catálogo del estudio, con su máquina de estados | sí |
| `clientes` | Padrón de personas | sí |
| `expedientes` | La causa | sí |
| `expediente_partes` | Persona + causa + rol | sí |
| `plazos` | Fecha escrita y respaldo inmutable | sí |
| `documentos` | Identidad estable de un documento | sí |
| `documento_versiones` | Cada copia recibida | sí |
| `incidencias_documentales` | Lo que NO cuadró | sí |
| `tareas` | Horas imputables | sí |
| `bitacora_accesos` | quién abrió qué | sí |

---

## 3. Lo que NO está en la base, y por qué

Esto es lo más importante del documento.

### No hay ningún plazo legal en ninguna tabla

No hay columna `dias_plazo`. No hay `plazo_dias`. No hay ninguna cantidad de
días que se multiplique contra una fecha. No hay un valor por omisión.

La tabla `configuracion_estudio` tiene `dias_alerta_plazo` y
`dias_alerta_critica`, y **no son plazos**: son la alarma interna que el socio
eligió. Son un parámetro operativo, no una regla jurídica, y se usan únicamente
para decidir **cuándo avisar**, nunca para derivar una fecha.

### No hay columnas que calculen plazos

Si alguien más adelante agrega una columna `fecha_vencimiento_calculada` o una
función `calcularVencimiento()`, está rompiendo el proyecto. La fecha la
escribe una persona.

---

## 4. `expedientes`: el centro

| Columna | Tipo | Nota |
|---|---|---|
| `id` | uuid | No hay enteros auto-incrementales. Un `id` adivinable permite probar `/expedientes/41` hasta dar con uno real. |
| `folio` | string | **Lo genera la aplicación.** Interno, no significa nada fuera de acá. |
| `numero_causa` | string nullable | **Lo escribe una persona.** Puede traer letras, guiones y barras: por eso es texto y no entero. |
| `cliente_id` | uuid nullable | |
| `materia_id` | uuid | |
| `abogado_responsable_id` | uuid nullable | **Define la cartera.** |
| `estado_id` | uuid nullable | Apunta al catálogo del estudio. |
| `titulo`, `detalle` | | |
| `fecha_inicio`, `fecha_cierre` | date | |

Único sobre `(tenant_id, folio)`.

**Lo que no tiene:** una columna de estado calculada a mano. El estado vive en
`estados_expediente`, y las transiciones las declara el estudio.

---

## 5. `expediente_partes`: el rol es de la relación

Esta tabla existe por una razón, y no es un detalle de modelado.

Una misma persona puede ser **demandada** en una causa y **cliente** del estudio
en otra. Si el rol fuera una columna de `clientes`, habría que duplicar a la
persona con dos filas y resolver a mano cuál es cuál, o elegir un rol y perder
el otro.

Con esta tabla, una sola fila de `clientes` participa en diez expedientes con
diez roles distintos.

| Columna | Nota |
|---|---|
| `expediente_id` + `cliente_id` + `rol` | Único junto. Una persona tiene un rol por expediente. |
| `es_externo` | Persona ajena al estudio: la contraparte o el tercero. |

**Lo que el sistema no sabe sobre estos roles:** no sabe qué implican en
derecho, no valida que una demanda tenga un demandado, no comprueba que los roles
sean coherentes entre sí. Los registra y permite filtrar por ellos.

---

## 6. `plazos`: la tabla de la advertencia

| Columna | Nota |
|---|---|
| `fecha_vencimiento` | **La escribió el abogado.** No se deriva de nada. |
| `texto_respaldo` | **Obligatoria desde la base.** Mínimo 30 caracteres en el formulario. |
| `estado` | `pendiente` \| `cumplido` \| `vencido` |
| `dias_alerta` | Snapshot del número operativo del estudio **al crear el plazo**. |
| `dias_alerta_critica` | Ídem. |
| `vencido_detectado_at` | Cuándo lo vio el vigilante. Solo se escribe una vez. |
| `cumplido_at`, `cumplido_por_usuario_id` | Quién lo marcó y cuándo. |
| `respaldo_copiado_al_expediente` | Copia del respaldo, con su fecha de copia. |

### Las tres columnas que no existen

| No existe | Por qué |
|---|---|
| `dias_plazo` | Sería el número que multiplica. Ese número depende de la norma, y la norma cambia. |
| `fecha_inicio_plazo` | No hay de dónde derivar nada. |
| `fecha_vencimiento_calculada` | Misma razón. |

### El disparador que protege el respaldo

`2026_10_17_000018_inmovilizar_respaldo_plazo` define un disparador `BEFORE
UPDATE` que **lanza excepción** si alguien intenta cambiar `texto_respaldo` o
`fecha_vencimiento`.

Está en la base y no en el código a propósito: una regla en un Policy protege
mientras nadie se salte la aplicación. Una regla en la base protege también
cuando alguien entra por un cliente SQL, cuando un respaldo se restaura y
cuando un error de aplicación intenta escribir.

Si la fecha estaba mal, se **anula** el plazo y se registra otro, con su propio
respaldo. Queda el rastro de los dos.

### Por qué `dias_alerta` es un snapshot

Porque si se usara el valor actual de la configuración, cambiar el número del
estudio reescribiría el pasado de todos los plazos: un plazo creado en enero
quedaría marcado según lo que el socio decidió en junio.

---

## 7. `documentos` y `documento_versiones`

### `documentos`: la identidad estable

| Columna | Nota |
|---|---|
| `clave_natural` | Cómo identifica el estudio a este documento. **No puede ser nula.** |
| `huella_sha256` | Resumen del contenido de la versión vigente. |
| `version_actual` | Qué versión se considera vigente. |
| `ruta_archivo` | **Relativa** dentro de `DOCUMENTOS_PATH`. |
| `estado` | `vigente` \| `sustituido` \| `anulado` |

Único sobre `(expediente_id, clave_natural)`. Esa restricción es la que hace
imposible que dos documentos distintos compartan clave en una misma causa, y por
eso la colisión se puede detectar siempre.

**Por qué la ruta es relativa:** una ruta absoluta cambiaría al mover el volumen,
y el respaldo dejaría de encontrar los archivos.

### `documento_versiones`: cada copia, sin excepción

Un documento que cambió tres veces tiene tres filas acá. **Nunca se borra una
versión anterior**: queda `no_vigente` y se sigue descargando.

`resultado_recepcion` documenta qué decidió el sistema con cada copia, incluso
con las que no se guardaron:

| Valor | Qué pasó |
|---|---|
| `nuevo` | Primera vez que llega este documento. |
| `version_nueva` | Llegó una versión posterior; se agregó. |
| `duplicado_exacto` | Misma huella que la vigente. No se agregó nada. |
| `colision_version` | Contenido distinto con versión que no corresponde. **No se agregó nada.** |

Único sobre `(documento_id, version)`. Esa restricción es la que vuelve imposible
guardar en silencio dos contenidos distintos declarados como la misma versión.

---

## 8. `incidencias_documentales`: el registro de lo que no encajó

| Tipo | Severidad | Qué significa |
|---|---|---|
| `colision_version` | **alta** | Dos contenidos distintos con la misma versión. Grave. |
| `duplicado_otro_expediente` | media | Este documento ya estaba en otra causa. |
| `duplicado_exacto` | baja | Llegó otra copia idéntica. |

`expediente_conflictivo_id` apunta a la OTRA causa donde estaba el mismo
contenido. **No se toca esa causa**: se avisa y decide una persona.

---

## 9. `tareas`: horas, no facturación

`minutos_imputados` se **escriben**. El sistema no calcula minutos: convierte a
horas la interfaz, que es una división.

`minutos_facturados` separa lo PRESENTADO de lo FACTURADO, pero es un estado de
cuenta interno: **esta aplicación no emite documentos tributarios** y no habrá
código que lo haga. `numero_factura` es lo que el estudio ya emitió por su
cuenta.

El máximo de 1440 minutos (24 horas) no es una regla del estudio: es para que un
error de tipeo, un año en vez de un día, no se cuele como si fuera real.

---

## 10. `bitacora_accesos`: solo agregar

La bitácora de accesos responde a la pregunta que más importa en un estudio:
**"¿quién abrió este expediente?"**.

- No hay ruta de actualización ni de borrado en toda la aplicación.
- `2026_10_17_000019_bloquear_borrado_bitacora` deja la garantía **en la base**,
  con un disparador que rechaza `UPDATE` y `DELETE`.

Una auditoría que se puede editar no es una auditoría.

---

## 11. Las dos tablas sin Row Level Security

| Tabla | Por qué |
|---|---|
| `estudios` | Se consulta durante el inicio de sesión, cuando todavía no hay contexto. Se compensa con la vista `vw_estudios_publicos`, que expone tres columnas y solo estudios activos. |
| `sessions` | Es la sesión de Laravel; se resuelve antes de saber quién es la persona. No tiene `tenant_id` que filtrar. |

El riesgo residual está anotado con todas las letras en [03](03-MULTITENANCY-Y-ROLES.md).

---

## 12. Identificadores

Todos los UUID, en todas las tablas. Ninguna tabla de negocio usa enteros
auto-incrementales.

En una tabla con datos de clientes y causas, un `id` adivinable permite recorrer
el sistema probando `/expedientes/41`, `/expedientes/42`... hasta dar con uno
real. Un UUID no se puede recorrer.

---

Siguiente: [03 Multitenancy y roles](03-MULTITENANCY-Y-ROLES.md).